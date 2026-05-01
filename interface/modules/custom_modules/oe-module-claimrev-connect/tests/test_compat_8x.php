<?php

/**
 * Test that compat shims are a no-op on 8.x and module classes work.
 *
 * Run inside Docker:
 *   php /var/www/localhost/htdocs/openemr/interface/modules/custom_modules/oe-module-claimrev-connect/tests/test_compat_8x.php
 */

$_GET['site'] = 'default';
$ignoreAuth = 1;
// Built at runtime so PHPStan doesn't try to resolve the literal path on
// developer machines (this script only runs inside the OpenEMR container).
$openemrRoot = getenv('OPENEMR_ROOT') ?: '/var/www/localhost/htdocs/openemr';
$globalsPath = $openemrRoot . '/interface/globals.php';
if (!file_exists($globalsPath)) {
    fwrite(STDERR, "This integration test must be run inside the OpenEMR Docker container.\n");
    fwrite(STDERR, "Expected: $globalsPath\n");
    exit(1);
}
require_once $globalsPath;
require_once dirname(__DIR__) . '/src/Compat/compat.php';

$bag = \OpenEMR\Core\OEGlobalsBag::getInstance();

// Register module autoloader (on 8.x this is done by the bootstrap, on 7.x test we do it manually)
$classLoader = new \OpenEMR\Core\ModulesClassLoader($bag->getString('fileroot'));
$classLoader->registerNamespaceIfNotExists('OpenEMR\\Modules\\ClaimRevConnector\\', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'src');

echo "=== Integration Test ===\n\n";

// On 8.x, real classes should be loaded — compat.php should be a no-op
$rc = new \ReflectionClass(\OpenEMR\Core\OEGlobalsBag::class);
$isShim = str_contains((string) $rc->getFileName(), 'Compat');
echo "OEGlobalsBag: " . ($isShim ? "SHIM (7.x mode)" : "NATIVE (8.x)") . " — " . $rc->getFileName() . "\n";

$rc2 = new \ReflectionClass(\OpenEMR\BC\ServiceContainer::class);
$isShim2 = str_contains((string) $rc2->getFileName(), 'Compat');
echo "ServiceContainer: " . ($isShim2 ? "SHIM (7.x mode)" : "NATIVE (8.x)") . " — " . $rc2->getFileName() . "\n";

// Test GlobalConfig
$gc = new \OpenEMR\Modules\ClaimRevConnector\GlobalConfig($GLOBALS);
echo "GlobalConfig: " . ($gc->isConfigured() ? "configured" : "not configured") . "\n";

// Test CryptoGen
$crypto = \OpenEMR\BC\ServiceContainer::getCrypto();
echo "CryptoGen: " . $crypto::class . "\n";
echo "  decryptStandard() exists: " . (method_exists($crypto, 'decryptStandard') ? 'yes' : 'no') . "\n";

// Test Bootstrap
$kernel = $bag->getKernel();
$bootstrap = new \OpenEMR\Modules\ClaimRevConnector\Bootstrap($kernel->getEventDispatcher());
echo "Bootstrap: v" . \OpenEMR\Modules\ClaimRevConnector\Bootstrap::MODULE_VERSION . "\n";

echo "\n=== All OK ===\n";
