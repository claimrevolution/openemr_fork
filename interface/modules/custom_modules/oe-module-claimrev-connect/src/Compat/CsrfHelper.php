<?php

/**
 * CSRF compatibility helper for OpenEMR 7.x and 8.x.
 *
 * OpenEMR 8.x changed CsrfUtils::collectCsrfToken and verifyCsrfToken to
 * require a SessionInterface as the first parameter (after $token for verify).
 * OpenEMR 7.x used ($subject, ?$session) ordering with session optional.
 *
 * This helper detects which signature is available and calls correctly.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector\Compat;

use OpenEMR\Common\Csrf\CsrfUtils;
use ReflectionMethod;

class CsrfHelper
{
    /** @var bool|null cached result of signature detection */
    private static $usesSessionFirst = null;

    /**
     * Detect whether CsrfUtils::collectCsrfToken expects SessionInterface as
     * the first parameter (OpenEMR 8.x) or subject string (OpenEMR 7.x).
     */
    private static function usesSessionFirst(): bool
    {
        if (self::$usesSessionFirst === null) {
            $ref = new ReflectionMethod(CsrfUtils::class, 'collectCsrfToken');
            $params = $ref->getParameters();
            $firstParam = $params[0] ?? null;
            if ($firstParam !== null) {
                $type = $firstParam->getType();
                // OpenEMR 8.x: first param is SessionInterface (not a string).
                // Only ReflectionNamedType has isBuiltin(); union/intersection
                // types don't, but a SessionInterface param will always be a
                // single named type.
                self::$usesSessionFirst = $type instanceof \ReflectionNamedType
                    && !$type->isBuiltin()
                    && $firstParam->getName() === 'session';
            } else {
                self::$usesSessionFirst = false;
            }
        }
        return self::$usesSessionFirst;
    }

    /**
     * Get a session object if SessionWrapperFactory is available (OpenEMR 8.x).
     *
     * @return mixed SessionInterface on 8.x, null on 7.x
     */
    private static function getSession()
    {
        if (class_exists(\OpenEMR\Common\Session\SessionWrapperFactory::class)) {
            return \OpenEMR\Common\Session\SessionWrapperFactory::getInstance()->getActiveSession();
        }
        return null;
    }

    /**
     * Collect a CSRF token, compatible with both OpenEMR 7.x and 8.x.
     *
     * @param string $subject CSRF token subject/namespace
     * @return string
     */
    public static function collectCsrfToken($subject = 'default')
    {
        if (self::usesSessionFirst()) {
            $session = self::getSession();
            return CsrfUtils::collectCsrfToken($session, $subject);
        }
        return CsrfUtils::collectCsrfToken($subject);
    }

    /**
     * Verify a CSRF token, compatible with both OpenEMR 7.x and 8.x.
     *
     * @param string $token The token to verify
     * @param string $subject CSRF token subject/namespace
     * @return bool
     */
    public static function verifyCsrfToken($token, $subject = 'default')
    {
        if (self::usesSessionFirst()) {
            $session = self::getSession();
            return CsrfUtils::verifyCsrfToken($token, $session, $subject);
        }
        return CsrfUtils::verifyCsrfToken($token, $subject);
    }
}
