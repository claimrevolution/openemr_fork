<?php

/**
 * Test that compat shims are a no-op on 8.x and module classes work.
 *
 * Run inside Docker:
 *   php /var/www/localhost/htdocs/openemr/interface/modules/custom_modules/oe-module-claimrev-connect/tests/test_compat_8x.php
 */

$_GET['site'] = 'default';
$ignoreAuth = 1;
require_once '/var/www/localhost/htdocs/openemr/interface/globals.php';
require_once dirname(__DIR__) . '/src/Compat/compat.php';

echo "=== 8.x Integration Test ===\n\n";

// On 8.x, real classes should be loaded — compat.php should be a no-op
$rc = new ReflectionClass('OpenEMR\Core\OEGlobalsBag');
$isShim = str_contains($rc->getFileName(), 'Compat');
echo "OEGlobalsBag: " . ($isShim ? "SHIM (7.x mode)" : "NATIVE (8.x)") . " — " . $rc->getFileName() . "\n";

$rc2 = new ReflectionClass('OpenEMR\BC\ServiceContainer');
$isShim2 = str_contains($rc2->getFileName(), 'Compat');
echo "ServiceContainer: " . ($isShim2 ? "SHIM (7.x mode)" : "NATIVE (8.x)") . " — " . $rc2->getFileName() . "\n";

// Test GlobalConfig
$gc = new \OpenEMR\Modules\ClaimRevConnector\GlobalConfig($GLOBALS);
echo "GlobalConfig: " . ($gc->isConfigured() ? "configured" : "not configured") . "\n";

// Test CryptoGen
$crypto = \OpenEMR\BC\ServiceContainer::getCrypto();
echo "CryptoGen: " . get_class($crypto) . "\n";
echo "  decryptStandard() exists: " . (method_exists($crypto, 'decryptStandard') ? 'yes' : 'no') . "\n";

// Test Bootstrap
$bootstrap = new \OpenEMR\Modules\ClaimRevConnector\Bootstrap($GLOBALS['kernel']->getEventDispatcher());
echo "Bootstrap: v" . \OpenEMR\Modules\ClaimRevConnector\Bootstrap::MODULE_VERSION . "\n";

echo "\n=== All OK ===\n";
