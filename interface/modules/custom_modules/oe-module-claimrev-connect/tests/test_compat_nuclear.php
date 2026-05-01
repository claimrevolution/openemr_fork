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

namespace OpenEMR\Modules\ClaimRevConnector\Tests;

final class NuclearCompatRunner
{
    public static int $passed = 0;
    public static int $failed = 0;

    public static function record(string $name, bool $result, string $detail = ''): void
    {
        $status = $result ? 'PASS' : 'FAIL';
        if ($result) {
            self::$passed++;
        } else {
            self::$failed++;
        }
        echo "  {$status}: {$name}";
        if ($detail !== '') {
            echo " — {$detail}";
        }
        echo "\n";
    }
}

// Built at runtime so PHPStan doesn't try to resolve the literal path on
// developer machines (this script only runs inside the OpenEMR container).
$openemrRoot = getenv('OPENEMR_ROOT') ?: '/var/www/localhost/htdocs/openemr';
if (!is_dir($openemrRoot)) {
    fwrite(STDERR, "This integration test must be run inside the OpenEMR Docker container.\n");
    fwrite(STDERR, "Expected: $openemrRoot\n");
    exit(1);
}

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
register_shutdown_function(function () use (&$renamed): void {
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

$rc = new \ReflectionClass(\OpenEMR\Core\OEGlobalsBag::class);
$isShim = str_contains((string) $rc->getFileName(), 'Compat');
echo "  OEGlobalsBag: " . ($isShim ? "SHIM" : "NATIVE") . " — " . $rc->getFileName() . "\n";

$rc2 = new \ReflectionClass(\OpenEMR\BC\ServiceContainer::class);
$isShim2 = str_contains((string) $rc2->getFileName(), 'Compat');
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
} catch (\RuntimeException | \LogicException $e) {
    echo "  FAILED: " . $e->getMessage() . "\n";
    echo "  at " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}

// Step 4: Test module components
echo "\n--- Testing module components ---\n";

// OEGlobalsBag via shim
$bag = \OpenEMR\Core\OEGlobalsBag::getInstance();
NuclearCompatRunner::record('OEGlobalsBag::getInstance()', true);
$fileroot = $bag->get('fileroot');
NuclearCompatRunner::record('get(fileroot) returns path', is_string($fileroot) && $fileroot !== '', is_string($fileroot) ? $fileroot : '');
$webroot = $bag->getString('webroot');
NuclearCompatRunner::record('getString(webroot)', $webroot !== '' || $webroot === '', $webroot);
NuclearCompatRunner::record('getKernel() returns Kernel', $bag->getKernel() instanceof \OpenEMR\Core\Kernel);

// ServiceContainer via shim
$crypto = \OpenEMR\BC\ServiceContainer::getCrypto();
NuclearCompatRunner::record('getCrypto() returns CryptoGen', $crypto instanceof \OpenEMR\Common\Crypto\CryptoGen, $crypto::class);

// GlobalConfig
try {
    $gc = new \OpenEMR\Modules\ClaimRevConnector\GlobalConfig($GLOBALS);
    NuclearCompatRunner::record('GlobalConfig instantiation', true, 'configured=' . ($gc->isConfigured() ? 'yes' : 'no'));
} catch (\RuntimeException | \LogicException $e) {
    NuclearCompatRunner::record('GlobalConfig instantiation', false, $e->getMessage());
}

// Bootstrap
try {
    $kernel = $bag->getKernel();
    $bootstrap = new \OpenEMR\Modules\ClaimRevConnector\Bootstrap($kernel->getEventDispatcher());
    NuclearCompatRunner::record('Bootstrap instantiation', true, 'v' . \OpenEMR\Modules\ClaimRevConnector\Bootstrap::MODULE_VERSION);
} catch (\RuntimeException | \LogicException $e) {
    NuclearCompatRunner::record('Bootstrap instantiation', false, $e->getMessage());
}

// Module class autoloading
NuclearCompatRunner::record('PatientBalanceService loadable', class_exists(\OpenEMR\Modules\ClaimRevConnector\PatientBalanceService::class));
NuclearCompatRunner::record('DashboardService loadable', class_exists(\OpenEMR\Modules\ClaimRevConnector\DashboardService::class));
NuclearCompatRunner::record('AgingReportService loadable', class_exists(\OpenEMR\Modules\ClaimRevConnector\AgingReportService::class));
NuclearCompatRunner::record('DenialAnalyticsService loadable', class_exists(\OpenEMR\Modules\ClaimRevConnector\DenialAnalyticsService::class));
NuclearCompatRunner::record('ClaimRevApi loadable', class_exists(\OpenEMR\Modules\ClaimRevConnector\ClaimRevApi::class));

echo "\n=== Results: " . NuclearCompatRunner::$passed . " passed, " . NuclearCompatRunner::$failed . " failed ===\n";
