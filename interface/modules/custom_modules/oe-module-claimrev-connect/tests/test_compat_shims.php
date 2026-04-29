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

// Prevent autoloading the real classes — we want to test the shims in isolation
// We do this by loading the shims BEFORE any OpenEMR code

$shimDir = dirname(__DIR__) . '/src/Compat';
$passed = 0;
$failed = 0;

function test(string $name, bool $result, string $detail = ''): void
{
    global $passed, $failed;
    if ($result) {
        $passed++;
        echo "  PASS: {$name}";
    } else {
        $failed++;
        echo "  FAIL: {$name}";
    }
    if ($detail !== '') {
        echo " — {$detail}";
    }
    echo "\n";
}

echo "=== ClaimRev Compatibility Shim Tests ===\n\n";

// ---- Test 1: OEGlobalsBagShim ----
echo "--- OEGlobalsBagShim ---\n";

require_once $shimDir . '/OEGlobalsBagShim.php';

$shim = \OpenEMR\Modules\ClaimRevConnector\Compat\OEGlobalsBagShim::getInstance();

test('getInstance() returns object', is_object($shim), $shim::class);
test('getInstance() is singleton', $shim === \OpenEMR\Modules\ClaimRevConnector\Compat\OEGlobalsBagShim::getInstance());

// Set up some test globals
$GLOBALS['test_string'] = 'hello';
$GLOBALS['test_int'] = 42;
$GLOBALS['test_bool'] = true;
$GLOBALS['test_empty'] = '';
$GLOBALS['fileroot'] = '/var/www/localhost/htdocs/openemr';

test('get() returns string value', $shim->get('test_string') === 'hello', $shim->get('test_string'));
test('get() returns int value', $shim->get('test_int') === 42);
test('get() returns default for missing key', $shim->get('nonexistent', 'default') === 'default');
test('get() returns null for missing key without default', $shim->get('nonexistent') === null);
test('has() returns true for existing key', $shim->has('test_string'));
test('has() returns false for missing key', !$shim->has('nonexistent'));
test('getString() returns string', $shim->getString('test_string') === 'hello');
test('getString() casts int to string', $shim->getString('test_int') === '42');
test('getString() returns default for missing', $shim->getString('nonexistent', 'def') === 'def');
test('getInt() returns int', $shim->getInt('test_int') === 42);
test('getInt() returns default for missing', $shim->getInt('nonexistent', 99) === 99);
test('getBoolean() returns bool', $shim->getBoolean('test_bool') === true);
test('getBoolean() returns default for missing', $shim->getBoolean('nonexistent', false) === false);

// Test set()
$shim->set('test_set', 'set_value');
test('set() updates $GLOBALS', $GLOBALS['test_set'] === 'set_value');
test('set() readable via get()', $shim->get('test_set') === 'set_value');

// Test getKernel() — should throw without a real kernel
$threw = false;
try {
    $shim->getKernel();
} catch (\RuntimeException $e) {
    $threw = true;
}
test('getKernel() throws RuntimeException without kernel', $threw);
test('hasKernel() returns false without kernel', !$shim->hasKernel());

echo "\n";

// ---- Test 2: ServiceContainerShim ----
echo "--- ServiceContainerShim ---\n";

// ServiceContainerShim needs CryptoGen — check if it exists (it will in the container)
$hasCryptoGen = class_exists(\OpenEMR\Common\Crypto\CryptoGen::class, true);
if (!$hasCryptoGen) {
    echo "  SKIP: CryptoGen not available (not running inside OpenEMR)\n";
    echo "  Testing ServiceContainerShim class structure only...\n";
    require_once $shimDir . '/ServiceContainerShim.php';
    test('ServiceContainerShim class exists', class_exists(\OpenEMR\Modules\ClaimRevConnector\Compat\ServiceContainerShim::class));
    test('getCrypto() method exists', method_exists(\OpenEMR\Modules\ClaimRevConnector\Compat\ServiceContainerShim::class, 'getCrypto'));
} else {
    require_once $shimDir . '/ServiceContainerShim.php';
    $crypto = \OpenEMR\Modules\ClaimRevConnector\Compat\ServiceContainerShim::getCrypto();
    test('getCrypto() returns object', is_object($crypto), $crypto::class);
    test('getCrypto() returns CryptoGen', $crypto instanceof \OpenEMR\Common\Crypto\CryptoGen);
    test('CryptoGen has decryptStandard()', method_exists($crypto, 'decryptStandard'));
    test('CryptoGen has encryptStandard()', method_exists($crypto, 'encryptStandard'));
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
    test('OEGlobalsBag alias registered', class_exists(\OpenEMR\Core\OEGlobalsBag::class));
    test('ServiceContainer alias registered', class_exists(\OpenEMR\BC\ServiceContainer::class));

    $bag = \OpenEMR\Core\OEGlobalsBag::getInstance();
    test('Aliased OEGlobalsBag::getInstance() works', is_object($bag));
    test('Aliased get() works through alias', $bag->get('fileroot') === '/var/www/localhost/htdocs/openemr');
}

echo "\n";

// ---- Summary ----
echo "=== Results: {$passed} passed, {$failed} failed ===\n";
exit($failed > 0 ? 1 : 0);
