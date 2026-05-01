<?php

/**
 * Compatibility check — verifies the module can run on this OpenEMR version.
 *
 * Hit this page in the browser to see if OEGlobalsBag, ServiceContainer,
 * and all module dependencies resolve correctly. Safe to run on any version.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";
require_once dirname(__DIR__) . '/src/Compat/compat.php';

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('admin', 'manage_modules')) {
    echo "Access denied — requires admin/manage_modules ACL.";
    exit;
}

$tab = "connectivity";
$checks = [];

// 1. OEGlobalsBag
$check = ['name' => 'OEGlobalsBag class exists', 'pass' => false, 'detail' => ''];
try {
    $exists = class_exists(\OpenEMR\Core\OEGlobalsBag::class);
    $check['pass'] = $exists;
    if ($exists) {
        $rc = new ReflectionClass(\OpenEMR\Core\OEGlobalsBag::class);
        $isShim = str_contains($rc->getFileName(), 'Compat');
        $check['detail'] = $isShim ? 'Using ClaimRev shim (7.x mode)' : 'Using native OpenEMR class (8.x)';
    }
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 2. OEGlobalsBag::getInstance()
$check = ['name' => 'OEGlobalsBag::getInstance()', 'pass' => false, 'detail' => ''];
try {
    $bag = \OpenEMR\Core\OEGlobalsBag::getInstance();
    $check['pass'] = is_object($bag);
    $check['detail'] = $bag::class;
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 3. OEGlobalsBag->get() works
$check = ['name' => 'OEGlobalsBag->get("fileroot")', 'pass' => false, 'detail' => ''];
try {
    $bag = \OpenEMR\Core\OEGlobalsBag::getInstance();
    $fileroot = $bag->get('fileroot');
    $check['pass'] = !empty($fileroot) && is_dir($fileroot);
    $check['detail'] = $fileroot ?: '(empty)';
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 4. OEGlobalsBag->getKernel()
$check = ['name' => 'OEGlobalsBag->getKernel()', 'pass' => false, 'detail' => ''];
try {
    $bag = \OpenEMR\Core\OEGlobalsBag::getInstance();
    $kernel = $bag->getKernel();
    $check['pass'] = $kernel instanceof \OpenEMR\Core\Kernel;
    $check['detail'] = $kernel::class;
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 5. ServiceContainer
$check = ['name' => 'ServiceContainer class exists', 'pass' => false, 'detail' => ''];
try {
    $exists = class_exists(\OpenEMR\BC\ServiceContainer::class);
    $check['pass'] = $exists;
    if ($exists) {
        $rc = new ReflectionClass(\OpenEMR\BC\ServiceContainer::class);
        $isShim = str_contains($rc->getFileName(), 'Compat');
        $check['detail'] = $isShim ? 'Using ClaimRev shim (7.x mode)' : 'Using native OpenEMR class (8.x)';
    }
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 6. ServiceContainer::getCrypto()
$check = ['name' => 'ServiceContainer::getCrypto()', 'pass' => false, 'detail' => ''];
try {
    $crypto = \OpenEMR\BC\ServiceContainer::getCrypto();
    $check['pass'] = is_object($crypto) && method_exists($crypto, 'decryptStandard');
    $check['detail'] = $crypto::class;
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 7. GlobalConfig instantiation
$check = ['name' => 'GlobalConfig instantiation', 'pass' => false, 'detail' => ''];
try {
    $gc = new \OpenEMR\Modules\ClaimRevConnector\GlobalConfig($GLOBALS);
    $check['pass'] = true;
    $check['detail'] = 'Configured: ' . ($gc->isConfigured() ? 'Yes' : 'No');
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 8. Bootstrap instantiation
$check = ['name' => 'Bootstrap instantiation', 'pass' => false, 'detail' => ''];
try {
    $kernel = \OpenEMR\Core\OEGlobalsBag::getInstance()->getKernel();
    $bootstrap = new \OpenEMR\Modules\ClaimRevConnector\Bootstrap($kernel->getEventDispatcher());
    $check['pass'] = true;
    $check['detail'] = 'Version ' . \OpenEMR\Modules\ClaimRevConnector\Bootstrap::MODULE_VERSION;
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 9. OpenEMR version
$check = ['name' => 'OpenEMR version', 'pass' => true, 'detail' => ''];
try {
    $fileroot = \OpenEMR\Core\OEGlobalsBag::getInstance()->getString('fileroot');
    @include($fileroot . '/version.php');
    $ver = ($v_major ?? '?') . '.' . ($v_minor ?? '?') . '.' . ($v_patch ?? '?') . ($v_tag ?? '');
    $check['detail'] = $ver;
} catch (\RuntimeException | \LogicException $e) {
    $check['detail'] = $e->getMessage();
}
$checks[] = $check;

// 10. PHP version
$checks[] = ['name' => 'PHP version', 'pass' => version_compare(PHP_VERSION, '8.1', '>='), 'detail' => PHP_VERSION];

$allPass = count(array_filter($checks, fn($c) => !$c['pass'])) === 0;
?>
<html>
    <head>
        <title><?php echo xlt("ClaimRev Connect - Compatibility Check"); ?></title>
        <?php Header::setupHeader(); ?>
    </head>
    <body class="body_top">
        <div class="container-fluid">
            <?php require '../templates/navbar.php'; ?>
            <div class="card mt-3">
                <div class="card-header">
                    <?php echo xlt("Compatibility Check"); ?>
                    <?php if ($allPass) { ?>
                        <span class="badge badge-success ml-2"><?php echo xlt("All Passed"); ?></span>
                    <?php } else { ?>
                        <span class="badge badge-danger ml-2"><?php echo xlt("Issues Found"); ?></span>
                    <?php } ?>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width:30px;"></th>
                                <th><?php echo xlt("Check"); ?></th>
                                <th><?php echo xlt("Detail"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checks as $c) { ?>
                            <tr>
                                <td class="text-center">
                                    <?php if ($c['pass']) { ?>
                                        <i class="fa fa-check-circle text-success"></i>
                                    <?php } else { ?>
                                        <i class="fa fa-times-circle text-danger"></i>
                                    <?php } ?>
                                </td>
                                <td><?php echo text($c['name']); ?></td>
                                <td><small><?php echo text($c['detail']); ?></small></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="mt-3 text-muted small">
                <?php echo xlt("If any check shows 'Using ClaimRev shim (7.x mode)', the module is running with compatibility shims. All features should work normally."); ?>
            </div>
        </div>
    </body>
</html>
