<?php

/**
 * Storage for the module's ZITADEL service-user key file and its cached access token.
 *
 * Both live encrypted in the singleton row of mod_dorn_auth, on the pattern of the
 * ClaimRev Connect module's ZitadelKeyStore. The key file never leaves the database
 * unencrypted; only its key id, user id and expiry are shown on the Setup tab.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Dorn;

use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Database\QueryUtils;
use SensitiveParameter;

final class ZitadelKeyStore
{
    private const TABLE = 'mod_dorn_auth';

    /** Refresh this long before the token expires, so a request never goes out with a token about to lapse. */
    private const TOKEN_REFRESH_MARGIN_SECONDS = 600;

    /** Same definition as table.sql; created here too for installs that upgraded without re-running the SQL. */
    private const CREATE_TABLE = "CREATE TABLE IF NOT EXISTS `mod_dorn_auth` ("
        . "`id` TINYINT(1) PRIMARY KEY DEFAULT 1, "
        . "`zitadel_key` TEXT DEFAULT NULL, "
        . "`zitadel_key_id` VARCHAR(64) NOT NULL DEFAULT '', "
        . "`zitadel_user_id` VARCHAR(64) NOT NULL DEFAULT '', "
        . "`zitadel_key_expires` VARCHAR(40) NOT NULL DEFAULT '', "
        . "`access_token` TEXT DEFAULT NULL, "
        . "`access_token_expires_at` DATETIME DEFAULT NULL, "
        . "`updated_at` DATETIME DEFAULT NULL"
        . ") ENGINE=InnoDB";

    /**
     * Key id, service user id, expiry and save time of the stored key, or null when none is stored.
     *
     * @return array{keyId: string, userId: string, keyExpires: string, updatedAt: string}|null
     */
    public static function getKeyInfo(): ?array
    {
        try {
            if (!QueryUtils::existsTable(self::TABLE)) {
                return null;
            }
            $row = QueryUtils::querySingleRow(
                "SELECT zitadel_key_id, zitadel_user_id, zitadel_key_expires, updated_at FROM `" . self::TABLE . "` "
                . "WHERE id = 1 AND zitadel_key IS NOT NULL AND zitadel_key <> ''"
            );
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($row)) {
            return null;
        }
        return [
            'keyId' => (string) ($row['zitadel_key_id'] ?? ''),
            'userId' => (string) ($row['zitadel_user_id'] ?? ''),
            'keyExpires' => (string) ($row['zitadel_key_expires'] ?? ''),
            'updatedAt' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @return array{keyId: string, key: string, userId: string, expirationDate: string}
     * @throws DornAuthenticationException when no usable key is stored
     */
    public static function getKeyFile(): array
    {
        if (self::getKeyInfo() === null) {
            throw new DornAuthenticationException('No ZITADEL key is configured. Add one on the DORN Setup tab.');
        }
        $encrypted = QueryUtils::fetchSingleValue(
            "SELECT zitadel_key FROM `" . self::TABLE . "` WHERE id = 1",
            'zitadel_key'
        );
        $json = is_string($encrypted) ? ServiceContainer::getCrypto()->decryptFromDatabase($encrypted) : false;
        if (!is_string($json) || $json === '') {
            throw new DornAuthenticationException('The stored ZITADEL key could not be decrypted.');
        }
        try {
            return ZitadelAuth::parseKeyFile($json);
        } catch (\InvalidArgumentException $e) {
            throw new DornAuthenticationException('The stored ZITADEL key is invalid: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @throws \InvalidArgumentException when the content is not a usable key file
     */
    public static function saveKeyFile(#[SensitiveParameter] string $json): void
    {
        $key = ZitadelAuth::parseKeyFile($json);
        QueryUtils::sqlStatementThrowException(self::CREATE_TABLE);

        $encrypted = ServiceContainer::getCrypto()->encryptForDatabase(trim($json));
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO `" . self::TABLE . "` (id, zitadel_key, zitadel_key_id, zitadel_user_id, zitadel_key_expires, access_token, access_token_expires_at, updated_at) "
            . "VALUES (1, ?, ?, ?, ?, NULL, NULL, NOW()) "
            . "ON DUPLICATE KEY UPDATE zitadel_key = VALUES(zitadel_key), zitadel_key_id = VALUES(zitadel_key_id), "
            . "zitadel_user_id = VALUES(zitadel_user_id), zitadel_key_expires = VALUES(zitadel_key_expires), "
            . "access_token = NULL, access_token_expires_at = NULL, updated_at = NOW()",
            [$encrypted, $key['keyId'], $key['userId'], $key['expirationDate']]
        );
    }

    public static function removeKey(): void
    {
        if (!QueryUtils::existsTable(self::TABLE)) {
            return;
        }
        QueryUtils::sqlStatementThrowException(
            "UPDATE `" . self::TABLE . "` SET zitadel_key = NULL, zitadel_key_id = '', zitadel_user_id = '', "
            . "zitadel_key_expires = '', access_token = NULL, access_token_expires_at = NULL, updated_at = NOW() WHERE id = 1"
        );
    }

    /** Clears only the cached token, e.g. after the environment changes. */
    public static function clearToken(): void
    {
        try {
            QueryUtils::sqlStatementThrowException(
                "UPDATE `" . self::TABLE . "` SET access_token = NULL, access_token_expires_at = NULL WHERE id = 1"
            );
        } catch (\Throwable) {
            // Nothing cached.
        }
    }

    public static function getCachedToken(): ?string
    {
        try {
            $row = QueryUtils::querySingleRow(
                "SELECT access_token FROM `" . self::TABLE . "` WHERE id = 1 AND access_token IS NOT NULL "
                . "AND access_token_expires_at > DATE_ADD(NOW(), INTERVAL " . self::TOKEN_REFRESH_MARGIN_SECONDS . " SECOND)"
            );
            if (!is_array($row) || !is_string($row['access_token'] ?? null) || $row['access_token'] === '') {
                return null;
            }
            $token = ServiceContainer::getCrypto()->decryptFromDatabase($row['access_token']);
            return is_string($token) && $token !== '' ? $token : null;
        } catch (\Throwable) {
            // A cache miss only costs a token request.
            return null;
        }
    }

    public static function saveToken(#[SensitiveParameter] string $token, int $expiresInSeconds): void
    {
        try {
            QueryUtils::sqlStatementThrowException(
                "UPDATE `" . self::TABLE . "` SET access_token = ?, access_token_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE id = 1",
                [ServiceContainer::getCrypto()->encryptForDatabase($token), max(0, $expiresInSeconds)]
            );
        } catch (\Throwable) {
            // Caching is an optimisation; the token in hand is still good.
        }
    }
}
