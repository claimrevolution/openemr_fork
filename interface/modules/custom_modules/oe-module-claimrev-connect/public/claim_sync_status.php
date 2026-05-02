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

declare(strict_types=1);

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ClaimRevConnector\ClaimStatusSyncService;
use OpenEMR\Modules\ClaimRevConnector\CsrfHelper;
use OpenEMR\Modules\ClaimRevConnector\ModuleInput;
use OpenEMR\Modules\ClaimRevConnector\TypeCoerce;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!CsrfHelper::verifyCsrfToken(ModuleInput::postString('csrf_token'), 'claims')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

$claimDataJson = ModuleInput::postString('claimData');
$decoded = json_decode($claimDataJson, true);

if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid claim data']);
    exit;
}

$claimData = [
    'patientControlNumber' => TypeCoerce::asString($decoded['patientControlNumber'] ?? ''),
    'statusId' => TypeCoerce::asInt($decoded['statusId'] ?? 0),
    'statusName' => TypeCoerce::asString($decoded['statusName'] ?? ''),
    'payerAcceptanceStatusId' => TypeCoerce::asInt($decoded['payerAcceptanceStatusId'] ?? 0),
    'payerAcceptanceStatusName' => TypeCoerce::asString($decoded['payerAcceptanceStatusName'] ?? ''),
    'errorMessage' => TypeCoerce::asString($decoded['errorMessage'] ?? ''),
];

$result = ClaimStatusSyncService::syncStatus($claimData);
echo json_encode($result);
