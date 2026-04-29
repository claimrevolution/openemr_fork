<?php

/**
 * AJAX endpoint: sync a ClaimRev claim status to OpenEMR.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ClaimRevConnector\ClaimStatusSyncService;
use OpenEMR\Modules\ClaimRevConnector\Compat\CsrfHelper;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!CsrfHelper::verifyCsrfToken($_POST['csrf_token'] ?? '', 'claims')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$claimDataJson = $_POST['claimData'] ?? '';
$claimData = json_decode((string) $claimDataJson, true);

if (!is_array($claimData)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid claim data']);
    exit;
}

$result = ClaimStatusSyncService::syncStatus($claimData);
echo json_encode($result);
