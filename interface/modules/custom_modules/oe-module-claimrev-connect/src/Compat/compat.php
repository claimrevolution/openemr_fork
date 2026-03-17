<?php

/**
 * Compatibility shims for running the ClaimRev module on OpenEMR 7.x.
 *
 * OpenEMR 8.x introduced OEGlobalsBag and BC\ServiceContainer. This file
 * provides minimal stand-ins so the module works on both 7.x and 8.x without
 * any changes to the rest of the codebase.
 *
 * Must be included AFTER globals.php and BEFORE any module code that
 * references OEGlobalsBag or ServiceContainer.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// OEGlobalsBag shim — wraps $GLOBALS with the same API the module uses
if (!class_exists('OpenEMR\Core\OEGlobalsBag', false)) {
    // Also check autoloader in case it just hasn't been loaded yet
    if (!class_exists('OpenEMR\Core\OEGlobalsBag')) {
        require_once __DIR__ . '/OEGlobalsBagShim.php';
        class_alias('OpenEMR\Modules\ClaimRevConnector\Compat\OEGlobalsBagShim', 'OpenEMR\Core\OEGlobalsBag');
    }
}

// ServiceContainer shim — returns CryptoGen directly
if (!class_exists('OpenEMR\BC\ServiceContainer', false)) {
    if (!class_exists('OpenEMR\BC\ServiceContainer')) {
        require_once __DIR__ . '/ServiceContainerShim.php';
        class_alias('OpenEMR\Modules\ClaimRevConnector\Compat\ServiceContainerShim', 'OpenEMR\BC\ServiceContainer');
    }
}
