<?php

/**
 * Nuclear compatibility test — temporarily hides the 8.x-only classes
 * and verifies the module boots using only the shims.
 *
 * This works by:
 * 1. Renaming the real OEGlobalsBag.php and ServiceContainer.php
 * 2. Loading globals.php (which will fail to find them)
 * 3. Loading our compat shims (which fill the gap)
 * 4. Booting the module
 * 5. Restoring the original files
 *
 * Run inside Docker:
 *   php /var/www/localhost/htdocs/openemr/interface/modules/custom_modules/oe-module-claimrev-connect/tests/test_compat_nuclear.php
 */

$openemrRoot = '/var/www/localhost/htdocs/openemr';

$filesToHide = [
    $openemrRoot . '/src/Core/OEGlobalsBag.php',
    $openemrRoot . '/src/BC/ServiceContainer.php',
    $openemrRoot . '/src/Common/Crypto/CryptoInterface.php',
];

$renamed = [];

echo "=== NUCLEAR COMPAT TEST ===\n\n";

// Step 1: Hide the 8.x files
echo "--- Hiding 8.x-only classes ---\n";
foreach ($filesToHide as $file) {
    if (file_exists($file)) {
        $backup = $file . '.bak_test';
        if (rename($file, $backup)) {
            $renamed[$file] = $backup;
            echo "  HIDDEN: " . basename($file) . "\n";
        } else {
            echo "  ERROR: Could not rename " . basename($file) . "\n";
        }
    } else {
        echo "  SKIP: " . basename($file) . " (not found)\n";
    }
}

// Register a shutdown function to ALWAYS restore files, even on fatal error
register_shutdown_function(function () use (&$renamed) {
    echo "\n--- Restoring 8.x classes ---\n";
    foreach ($renamed as $original => $backup) {
        if (file_exists($backup)) {
            rename($backup, $original);
            echo "  RESTORED: " . basename($original) . "\n";
        }
    }
    // Clear opcache so restored files are picked up
    if (function_exists('opcache_reset')) {
        opcache_reset();
    }
    echo "\nFiles restored. Your environment is intact.\n";
});

// Step 2: Load our compat shims BEFORE globals.php
echo "\n--- Loading compat shims ---\n";
require_once dirname(__DIR__) . '/src/Compat/compat.php';

$rc = new ReflectionClass('OpenEMR\Core\OEGlobalsBag');
$isShim = str_contains($rc->getFileName(), 'Compat');
echo "  OEGlobalsBag: " . ($isShim ? "SHIM" : "NATIVE") . " — " . $rc->getFileName() . "\n";

$rc2 = new ReflectionClass('OpenEMR\BC\ServiceContainer');
$isShim2 = str_contains($rc2->getFileName(), 'Compat');
echo "  ServiceContainer: " . ($isShim2 ? "SHIM" : "NATIVE") . " — " . $rc2->getFileName() . "\n";

if (!$isShim || !$isShim2) {
    echo "\n  WARNING: Real classes were already loaded (cached). Restart PHP-FPM or use cli to get clean state.\n";
}

// Step 3: Load OpenEMR globals
echo "\n--- Loading globals.php ---\n";
$_GET['site'] = 'default';
$ignoreAuth = 1;
try {
    require_once $openemrRoot . '/interface/globals.php';
    echo "  globals.php loaded OK\n";
} catch (\Throwable $e) {
    echo "  FAILED: " . $e->getMessage() . "\n";
    echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 4: Test module components
echo "\n--- Testing module components ---\n";

$passed = 0;
$failed = 0;

function test(string $name, bool $result, string $detail = ''): void
{
    global $passed, $failed;
    $status = $result ? 'PASS' : 'FAIL';
    $result ? $passed++ : $failed++;
    echo "  {$status}: {$name}";
    if ($detail !== '') {
        echo " — {$detail}";
    }
    echo "\n";
}

// OEGlobalsBag via shim
$bag = \OpenEMR\Core\OEGlobalsBag::getInstance();
test('OEGlobalsBag::getInstance()', is_object($bag));
test('get(fileroot) returns path', !empty($bag->get('fileroot')), $bag->get('fileroot'));
test('getString(webroot)', is_string($bag->getString('webroot')), $bag->getString('webroot'));
test('getKernel() returns Kernel', $bag->getKernel() instanceof \OpenEMR\Core\Kernel);

// ServiceContainer via shim
$crypto = \OpenEMR\BC\ServiceContainer::getCrypto();
test('getCrypto() returns CryptoGen', $crypto instanceof \OpenEMR\Common\Crypto\CryptoGen, get_class($crypto));

// GlobalConfig
try {
    $gc = new \OpenEMR\Modules\ClaimRevConnector\GlobalConfig($GLOBALS);
    test('GlobalConfig instantiation', true, 'configured=' . ($gc->isConfigured() ? 'yes' : 'no'));
} catch (\Throwable $e) {
    test('GlobalConfig instantiation', false, $e->getMessage());
}

// Bootstrap
try {
    $bootstrap = new \OpenEMR\Modules\ClaimRevConnector\Bootstrap($GLOBALS['kernel']->getEventDispatcher());
    test('Bootstrap instantiation', true, 'v' . \OpenEMR\Modules\ClaimRevConnector\Bootstrap::MODULE_VERSION);
} catch (\Throwable $e) {
    test('Bootstrap instantiation', false, $e->getMessage());
}

// Module class autoloading
test('PatientBalanceService loadable', class_exists('OpenEMR\Modules\ClaimRevConnector\PatientBalanceService'));
test('DashboardService loadable', class_exists('OpenEMR\Modules\ClaimRevConnector\DashboardService'));
test('AgingReportService loadable', class_exists('OpenEMR\Modules\ClaimRevConnector\AgingReportService'));
test('DenialAnalyticsService loadable', class_exists('OpenEMR\Modules\ClaimRevConnector\DenialAnalyticsService'));
test('ClaimRevApi loadable', class_exists('OpenEMR\Modules\ClaimRevConnector\ClaimRevApi'));

echo "\n=== Results: {$passed} passed, {$failed} failed ===\n";
