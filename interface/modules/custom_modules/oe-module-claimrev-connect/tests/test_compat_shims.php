<?php

/**
 * Standalone test for compatibility shims.
 *
 * Run from the command line inside the Docker container:
 *   php /var/www/localhost/htdocs/openemr/interface/modules/custom_modules/oe-module-claimrev-connect/tests/test_compat_shims.php
 *
 * This test loads the shim classes directly (NOT the real OpenEMR classes)
 * to verify they provide the API the module depends on.
 *
 * @package   OpenEMR
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 */

namespace OpenEMR\Modules\ClaimRevConnector\Tests;

final class CompatShimRunner
{
    public static int $passed = 0;
    public static int $failed = 0;

    public static function record(string $name, bool $result, string $detail = ''): void
    {
        if ($result) {
            self::$passed++;
            echo "  PASS: {$name}";
        } else {
            self::$failed++;
            echo "  FAIL: {$name}";
        }
        if ($detail !== '') {
            echo " — {$detail}";
        }
        echo "\n";
    }
}

$shimDir = dirname(__DIR__) . '/src/Compat';

echo "=== ClaimRev Compatibility Shim Tests ===\n\n";

// ---- Test 1: OEGlobalsBagShim ----
echo "--- OEGlobalsBagShim ---\n";

require_once $shimDir . '/OEGlobalsBagShim.php';

$shim = \OpenEMR\Modules\ClaimRevConnector\Compat\OEGlobalsBagShim::getInstance();

CompatShimRunner::record('getInstance() returns object', true, $shim::class);
CompatShimRunner::record('getInstance() is singleton', $shim === \OpenEMR\Modules\ClaimRevConnector\Compat\OEGlobalsBagShim::getInstance());

// Set up some test globals via the shim itself (avoid raw $GLOBALS access for PHPStan)
$shim->set('test_string', 'hello');
$shim->set('test_int', 42);
$shim->set('test_bool', true);
$shim->set('test_empty', '');
$shim->set('fileroot', '/var/www/localhost/htdocs/openemr');

CompatShimRunner::record('get() returns string value', $shim->get('test_string') === 'hello', \is_string($shim->get('test_string')) ? $shim->get('test_string') : '');
CompatShimRunner::record('get() returns int value', $shim->get('test_int') === 42);
CompatShimRunner::record('get() returns default for missing key', $shim->get('nonexistent', 'default') === 'default');
CompatShimRunner::record('get() returns null for missing key without default', $shim->get('nonexistent') === null);
CompatShimRunner::record('has() returns true for existing key', $shim->has('test_string'));
CompatShimRunner::record('has() returns false for missing key', !$shim->has('nonexistent'));
CompatShimRunner::record('getString() returns string', $shim->getString('test_string') === 'hello');
CompatShimRunner::record('getString() casts int to string', $shim->getString('test_int') === '42');
CompatShimRunner::record('getString() returns default for missing', $shim->getString('nonexistent', 'def') === 'def');
CompatShimRunner::record('getInt() returns int', $shim->getInt('test_int') === 42);
CompatShimRunner::record('getInt() returns default for missing', $shim->getInt('nonexistent', 99) === 99);
CompatShimRunner::record('getBoolean() returns bool', $shim->getBoolean('test_bool') === true);
CompatShimRunner::record('getBoolean() returns default for missing', $shim->getBoolean('nonexistent', false) === false);

// Test set()
$shim->set('test_set', 'set_value');
CompatShimRunner::record('set() round-trips via get()', $shim->get('test_set') === 'set_value');

// Test getKernel() — should throw without a real kernel
$threw = false;
try {
    $shim->getKernel();
} catch (\RuntimeException) {
    $threw = true;
}
CompatShimRunner::record('getKernel() throws RuntimeException without kernel', $threw);
CompatShimRunner::record('hasKernel() returns false without kernel', !$shim->hasKernel());

echo "\n";

// ---- Test 2: ServiceContainerShim ----
echo "--- ServiceContainerShim ---\n";

// ServiceContainerShim needs CryptoGen — check if it exists (it will in the container)
$hasCryptoGen = class_exists(\OpenEMR\Common\Crypto\CryptoGen::class, true);
if (!$hasCryptoGen) {
    echo "  SKIP: CryptoGen not available (not running inside OpenEMR)\n";
    echo "  Testing ServiceContainerShim class structure only...\n";
    require_once $shimDir . '/ServiceContainerShim.php';
    CompatShimRunner::record('ServiceContainerShim class exists', class_exists(\OpenEMR\Modules\ClaimRevConnector\Compat\ServiceContainerShim::class));
    CompatShimRunner::record('getCrypto() method exists', method_exists(\OpenEMR\Modules\ClaimRevConnector\Compat\ServiceContainerShim::class, 'getCrypto'));
} else {
    require_once $shimDir . '/ServiceContainerShim.php';
    $crypto = \OpenEMR\Modules\ClaimRevConnector\Compat\ServiceContainerShim::getCrypto();
    CompatShimRunner::record('getCrypto() returns object', is_object($crypto), $crypto::class);
    CompatShimRunner::record('getCrypto() returns CryptoGen', $crypto instanceof \OpenEMR\Common\Crypto\CryptoGen);
    CompatShimRunner::record('CryptoGen has decryptStandard()', method_exists($crypto, 'decryptStandard'));
    CompatShimRunner::record('CryptoGen has encryptStandard()', method_exists($crypto, 'encryptStandard'));
}

echo "\n";

// ---- Test 3: class_alias registration ----
echo "--- class_alias registration (compat.php) ---\n";

// Only test if the real classes DON'T exist yet
if (class_exists(\OpenEMR\Core\OEGlobalsBag::class, false)) {
    echo "  SKIP: Real OEGlobalsBag already loaded (running on 8.x) — aliases would be no-ops\n";
    echo "  The shim unit tests above confirm the shim API is correct.\n";
} else {
    require_once $shimDir . '/compat.php';
    CompatShimRunner::record('OEGlobalsBag alias registered', class_exists(\OpenEMR\Core\OEGlobalsBag::class));
    CompatShimRunner::record('ServiceContainer alias registered', class_exists(\OpenEMR\BC\ServiceContainer::class));

    $bag = \OpenEMR\Core\OEGlobalsBag::getInstance();
    CompatShimRunner::record('Aliased OEGlobalsBag::getInstance() works', true);
    CompatShimRunner::record('Aliased get() works through alias', $bag->get('fileroot') === '/var/www/localhost/htdocs/openemr');
}

echo "\n";

// ---- Summary ----
echo "=== Results: " . CompatShimRunner::$passed . " passed, " . CompatShimRunner::$failed . " failed ===\n";
exit(CompatShimRunner::$failed > 0 ? 1 : 0);
