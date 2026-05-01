#!/usr/bin/env bash
#
# Restore the OE 7.x compatibility overlay after a `git merge master`.
#
# Run this from the repository root, while on the release/v7-compat branch,
# IMMEDIATELY AFTER a `git merge master --no-edit`. The merge will have
# propagated master's deletion of `src/Compat/` and replaced
# `src/CsrfHelper.php` with the 8.x-only version. This script:
#
#   1. Restores the four shim files (compat.php + the three *Shim.php files)
#      from the pre-merge release/v7-compat tip (HEAD^).
#   2. Restores the bootstrap entry points (moduleConfig.php and
#      openemr.bootstrap.php) so they `require_once .../Compat/compat.php`.
#   3. Overwrites `src/CsrfHelper.php` with the reflection-based version
#      that adapts to either CsrfUtils signature at runtime. The 8.x-only
#      version coming in from master would fatal on 7.x.
#
# This is idempotent — running it twice in a row produces the same result.
#
# After running, `git status` should show:
#   - 4 files restored under src/Compat/
#   - 2 bootstrap files restored
#   - src/CsrfHelper.php modified
# Review and commit:
#   git add -A interface/modules/custom_modules/oe-module-claimrev-connect/
#   git commit -m "merge master into release/v7-compat + restore overlay"
#
# Long-term maintenance:
#   - Develop normally on master. Every reviewer fix lands there first.
#   - When you want to ship a v7-compatible client release, run:
#       git checkout release/v7-compat
#       git merge master --no-edit
#       ./interface/modules/custom_modules/oe-module-claimrev-connect/tools/v7-overlay-restore.sh
#       git add -A && git commit -m "..."
#       git push
#     then build a tarball from the release/v7-compat tip.
#   - The shim files don't change once committed; subsequent master merges
#     don't touch them (master has no opinion on those paths). Only
#     src/CsrfHelper.php conflicts every merge — and only because master
#     and v7-compat each have their own version of it. This script handles
#     that.

set -euo pipefail

if [[ ! -d .git ]]; then
    echo "ERROR: run from the repository root (where .git lives)." >&2
    exit 1
fi

current_branch="$(git rev-parse --abbrev-ref HEAD)"
if [[ "$current_branch" != "release/v7-compat" ]]; then
    echo "ERROR: expected to be on release/v7-compat (current: $current_branch)." >&2
    exit 1
fi

MOD=interface/modules/custom_modules/oe-module-claimrev-connect

# 1. Restore the shim files from the pre-merge v7-compat tip.
git checkout HEAD^ -- \
    "$MOD/src/Compat/compat.php" \
    "$MOD/src/Compat/OEGlobalsBagShim.php" \
    "$MOD/src/Compat/ServiceContainerShim.php" \
    "$MOD/src/Compat/CryptoInterfaceShim.php"

# 2. Restore the bootstrap files (so they require_once compat.php again).
git checkout HEAD^ -- \
    "$MOD/moduleConfig.php" \
    "$MOD/openemr.bootstrap.php"

# 3. Overwrite src/CsrfHelper.php with the reflection-based 7.x/8.x adapter.
cat > "$MOD/src/CsrfHelper.php" <<'PHP_EOF'
<?php

/**
 * CSRF helper that adapts to either OpenEMR 7.x or 8.x at runtime.
 *
 * OpenEMR 8.x changed CsrfUtils::collectCsrfToken / verifyCsrfToken to take
 * a SessionInterface in the first slot. OpenEMR 7.x used ($subject, ?$session)
 * with the session optional. This helper reflects on the active CsrfUtils
 * signature and dispatches correctly so the same caller code works on both.
 *
 * NOTE: this file lives only on the release/v7-compat branch. The upstream
 * (master) version of src/CsrfHelper.php targets 8.x exclusively. Keep them
 * in sync at the API surface (same class, same namespace, same public method
 * signatures) so callers don't have to know which build they're on.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\ClaimRevConnector;

use OpenEMR\Common\Csrf\CsrfUtils;
use ReflectionMethod;

final class CsrfHelper
{
    /** @var bool|null cached result of signature detection */
    private static ?bool $usesSessionFirst = null;

    private static function usesSessionFirst(): bool
    {
        if (self::$usesSessionFirst === null) {
            $ref = new ReflectionMethod(CsrfUtils::class, 'collectCsrfToken');
            $params = $ref->getParameters();
            $firstParam = $params[0] ?? null;
            if ($firstParam !== null) {
                $type = $firstParam->getType();
                self::$usesSessionFirst = $type instanceof \ReflectionNamedType
                    && !$type->isBuiltin()
                    && $firstParam->getName() === 'session';
            } else {
                self::$usesSessionFirst = false;
            }
        }
        return self::$usesSessionFirst;
    }

    private static function getSession(): mixed
    {
        if (class_exists(\OpenEMR\Common\Session\SessionWrapperFactory::class)) {
            return \OpenEMR\Common\Session\SessionWrapperFactory::getInstance()->getActiveSession();
        }
        return null;
    }

    public static function collectCsrfToken(string $subject = 'default'): string
    {
        if (self::usesSessionFirst()) {
            return CsrfUtils::collectCsrfToken(self::getSession(), $subject);
        }
        return CsrfUtils::collectCsrfToken($subject);
    }

    public static function verifyCsrfToken(string $token, string $subject = 'default'): bool
    {
        if (self::usesSessionFirst()) {
            return CsrfUtils::verifyCsrfToken($token, self::getSession(), $subject);
        }
        return CsrfUtils::verifyCsrfToken($token, $subject);
    }
}
PHP_EOF

echo "v7 overlay restored. Review with:"
echo "  git diff --stat $MOD/"
echo "Then:"
echo "  git add -A $MOD/ && git commit -m \"merge master + restore v7 overlay\""
