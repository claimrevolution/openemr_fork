<?php

/**
 * Signing in to the DORN API failed: no ZITADEL key, a bad key, or ZITADEL refused it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Dorn;

class DornAuthenticationException extends \RuntimeException
{
}
