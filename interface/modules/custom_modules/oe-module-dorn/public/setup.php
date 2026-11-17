<?php

/**
 * DORN Setup: the ZITADEL service-user key the module signs in to the DORN API with.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once __DIR__ . "/../../../../globals.php";

use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\Header;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\Dorn\Bootstrap;
use OpenEMR\Modules\Dorn\ConnectorApi;
use OpenEMR\Modules\Dorn\DornAuthenticationException;
use OpenEMR\Modules\Dorn\ZitadelKeyStore;

$tab = "setup";

if (!AclMain::aclCheckCore('admin', 'users')) {
    AccessDeniedHelper::denyWithTemplate("ACL check failed for admin/users: DORN Setup", xl("DORN Setup"));
}

$globalsConfig = (new Bootstrap(OEGlobalsBag::getInstance()->getKernel()->getEventDispatcher()))->getGlobalConfig();
$actionMessage = '';
$errorMessage = '';

/** Sign in with the stored key and ask the DORN API which account it belongs to. */
$testConnection = function () use (&$actionMessage, &$errorMessage): void {
    try {
        ZitadelKeyStore::clearToken();
        ConnectorApi::getAccessToken();
    } catch (DornAuthenticationException $e) {
        $errorMessage = xlt("Signing in with the key failed.") . ' ' . text($e->getMessage());
        return;
    }
    $result = ConnectorApi::getAccountNumber();
    if ($result['status'] === 200 && $result['account'] !== '') {
        $actionMessage = xlt("Connected to DORN as account") . ' ' . text($result['account']) . '.';
    } elseif ($result['status'] === 403) {
        $errorMessage = xlt("Signed in, but this service user is not linked to a DORN account. Ask ClaimRev to link it.");
    } elseif ($result['status'] === 401) {
        $errorMessage = xlt("The DORN API refused the sign-in. Check that the key and the environment match (a staging key will not work in production).");
    } else {
        $errorMessage = xlt("Signed in, but the DORN API could not be reached.") . ' ' . xlt("HTTP status") . ' ' . text((string) $result['status']);
    }
};

$session = SessionWrapperFactory::getInstance()->getActiveSession();

if (!empty($_POST)) {
    CsrfUtils::checkCsrfInput(INPUT_POST, $session, dieOnFail: true);
    if (isset($_POST['saveZitadelKey'])) {
        try {
            ZitadelKeyStore::saveKeyFile((string) ($_POST['zitadelKeyJson'] ?? ''));
            $testConnection();
        } catch (\InvalidArgumentException $e) {
            $errorMessage = xlt("The key was not saved.") . ' ' . text($e->getMessage());
        }
    } elseif (isset($_POST['testConnection'])) {
        $testConnection();
    } elseif (isset($_POST['removeZitadelKey'])) {
        ZitadelKeyStore::removeKey();
        $actionMessage = xlt("Key removed. The module cannot reach DORN until a new key is saved.");
    }
}

$keyInfo = ZitadelKeyStore::getKeyInfo();
$csrf = CsrfUtils::collectCsrfToken(session: $session);
?>
<!DOCTYPE html>
<html>
<head>
    <?php Header::setupHeader(); ?>
    <title><?php echo xlt("DORN Setup"); ?></title>
</head>
<body class="body_top">
    <div class="container-fluid">
        <?php require '../templates/navbar.php'; ?>

        <?php if ($actionMessage !== '') { ?>
            <div class="alert alert-success mt-3"><?php echo $actionMessage; ?></div>
        <?php } ?>
        <?php if ($errorMessage !== '') { ?>
            <div class="alert alert-danger mt-3"><?php echo $errorMessage; ?></div>
        <?php } ?>

        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0"><?php echo xlt("DORN Sign-In Key (ZITADEL)"); ?></h6>
            </div>
            <div class="card-body">
                <p class="small text-muted">
                    <?php echo xlt("Environment"); ?>: <?php echo text($globalsConfig->getEnvironment()); ?>
                    &middot; <?php echo xlt("Identity provider"); ?>: <?php echo text($globalsConfig->getZitadelAuthority()); ?>
                    &middot; <?php echo xlt("DORN API"); ?>: <?php echo text((string) $globalsConfig->getApiServer()); ?>
                </p>
                <?php if ($keyInfo !== null) { ?>
                    <p class="text-success mb-2"><i class="fa fa-check"></i> <?php echo xlt("A key is configured. The module signs in to DORN with it."); ?></p>
                    <ul class="small mb-3">
                        <li><?php echo xlt("Key ID"); ?>: <?php echo text($keyInfo['keyId']); ?></li>
                        <li><?php echo xlt("Service User ID"); ?>: <?php echo text($keyInfo['userId']); ?></li>
                        <li><?php echo xlt("Key Expires"); ?>: <?php echo text($keyInfo['keyExpires'] !== '' && !str_starts_with($keyInfo['keyExpires'], '9999') ? $keyInfo['keyExpires'] : xl('Never')); ?></li>
                        <li><?php echo xlt("Saved"); ?>: <?php echo text($keyInfo['updatedAt']); ?></li>
                    </ul>
                    <form method="post" action="setup.php" class="d-inline">
                        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>" />
                        <button type="submit" name="testConnection" class="btn btn-secondary btn-sm"><?php echo xlt("Test Connection"); ?></button>
                    </form>
                    <form method="post" action="setup.php" class="d-inline" onsubmit="return confirm(<?php echo xlj("Remove the key? The module will not be able to reach DORN until a new one is saved."); ?>);">
                        <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>" />
                        <button type="submit" name="removeZitadelKey" class="btn btn-outline-danger btn-sm"><?php echo xlt("Remove Key"); ?></button>
                    </form>
                    <hr />
                <?php } else { ?>
                    <p class="text-warning"><?php echo xlt("No key is configured. The module cannot reach DORN until one is saved."); ?></p>
                <?php } ?>
                <p class="small"><?php echo xlt("ClaimRev provides the key file (JSON) for your service user. It must come from the same environment as above: a staging key will not work in production."); ?></p>
                <form method="post" action="setup.php">
                    <input type="hidden" name="csrf_token_form" value="<?php echo attr($csrf); ?>" />
                    <div class="form-group">
                        <input type="file" class="form-control-file mb-2" id="zitadelKeyFile" accept=".json,application/json" />
                        <textarea class="form-control text-monospace small" id="zitadelKeyJson" name="zitadelKeyJson" rows="4" spellcheck="false" placeholder="<?php echo xla("Choose the key file above, or paste its contents here"); ?>"></textarea>
                    </div>
                    <button type="submit" name="saveZitadelKey" class="btn btn-primary btn-sm"><?php echo $keyInfo !== null ? xlt("Replace Key and Test Connection") : xlt("Save Key and Test Connection"); ?></button>
                </form>
                <script>
                    document.getElementById('zitadelKeyFile').addEventListener('change', function (e) {
                        const file = e.target.files[0];
                        if (!file) {
                            return;
                        }
                        const reader = new FileReader();
                        reader.onload = function () { document.getElementById('zitadelKeyJson').value = reader.result; };
                        reader.readAsText(file);
                    });
                </script>
            </div>
        </div>
    </div>
</body>
</html>
