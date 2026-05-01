<?php

/**
 * Kernel access compatibility helper for OpenEMR 7.x and 8.x.
 *
 * OEGlobalsBag::getKernel() was added after the OpenEMR 8.0.0 release and
 * is not available in any current patch release. This helper uses getKernel()
 * when present and falls back to $GLOBALS['kernel'] otherwise.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector\Compat;

use OpenEMR\Core\OEGlobalsBag;

class KernelHelper
{
    /**
     * Get the OpenEMR Kernel instance, compatible with all versions.
     *
     * @return \OpenEMR\Core\Kernel
     */
    public static function getKernel(): object
    {
        $bag = OEGlobalsBag::getInstance();
        if (method_exists($bag, 'getKernel')) {
            return $bag->getKernel();
        }
        $kernel = $bag->get('kernel');
        if (!is_object($kernel)) {
            throw new \RuntimeException('OpenEMR Kernel not initialized');
        }
        return $kernel;
    }

    /**
     * Get the EventDispatcher from the Kernel.
     *
     * This is the most common use case — shortcut to avoid the two-step call.
     *
     * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    public static function getEventDispatcher(): object
    {
        return self::getKernel()->getEventDispatcher();
    }
}
