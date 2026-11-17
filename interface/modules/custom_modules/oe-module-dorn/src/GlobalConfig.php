<?php

/**
 *
 * @package OpenEMR
 * @link    https://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022-2025 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

 namespace OpenEMR\Modules\Dorn;

use OpenEMR\Services\Globals\GlobalSetting;

class GlobalConfig
{
    public const CONFIG_OPTION_API_URL = 'oe_dorn_api_url';
    public const CONFIG_OPTION_ENVIRONMENT = 'oe_dorn_config_environment';

    /**
     * ZITADEL instance (issuer) and ClaimRev project (the token audience) per environment.
     * Production uses the production instance; Staging and Development use the staging instance,
     * which is the one the development DORN API accepts.
     */
    private const ZITADEL = [
        'P' => ['authority' => 'https://auth.claimrev.com', 'projectId' => '394259562464542723'],
        'S' => ['authority' => 'https://auth-staging.claimrev.com', 'projectId' => '394262572515196931'],
        'D' => ['authority' => 'https://auth-staging.claimrev.com', 'projectId' => '394262572515196931'],
    ];


    public const CONFIG_ENABLE_MENU = "oe_dorn_config_add_menu_button";

    public function __construct(private array $globalsArray)
    {
    }

    /**
     * Returns true if all of the settings have been configured.  Otherwise it returns false.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        // $keys = [self::CONFIG_OPTION_TEXT, self::CONFIG_OPTION_ENCRYPTED];
        // foreach ($keys as $key) {
        //     $value = $this->getGlobalSetting($key);
        //     if (empty($value)) {
        //         return false;
        //     }
        // }
        return true;
    }

    /** P, S or D; anything else is treated as production. */
    public function getEnvironment(): string
    {
        $environment = strtoupper(trim((string) $this->getGlobalSetting(self::CONFIG_OPTION_ENVIRONMENT)));
        return array_key_exists($environment, self::ZITADEL) ? $environment : 'P';
    }

    public function getZitadelAuthority(): string
    {
        return self::ZITADEL[$this->getEnvironment()]['authority'];
    }

    public function getZitadelProjectId(): string
    {
        return self::ZITADEL[$this->getEnvironment()]['projectId'];
    }

    public function getApiServer()
    {
        return $this->getGlobalSetting(self::CONFIG_OPTION_API_URL);
    }

    public function getGlobalSetting($settingKey)
    {
        return $this->globalsArray[$settingKey] ?? null;
    }

    public function getGlobalSettingSectionConfiguration(): array
    {
        $settings = [
            self::CONFIG_OPTION_ENVIRONMENT => [
                'title' => 'ClaimRev Environment (P=Production, S=Staging, D=Development)'
                ,'description' => 'The system you connect to. Sign-in uses the ZITADEL key on the DORN Setup tab, which must come from the same environment.'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => 'P'
            ],
            self::CONFIG_OPTION_API_URL => [
                'title' => 'API URL'
                ,'description' => 'The api system you to connect to'
                ,'type' => GlobalSetting::DATA_TYPE_TEXT
                ,'default' => ''
            ]
            ,self::CONFIG_ENABLE_MENU => [
                'title' => 'Add module menu item'
                ,'description' => 'Adding a menu item to the system (requires logging out and logging in again)'
                ,'type' => GlobalSetting::DATA_TYPE_BOOL
                ,'default' => ''
            ]
        ];//
        return $settings;
    }
}
