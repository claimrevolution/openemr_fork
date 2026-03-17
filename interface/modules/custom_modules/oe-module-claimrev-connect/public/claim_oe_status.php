<?php

/**
 * AJAX endpoint: look up OpenEMR claim status for a patient control number.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ClaimRevConnector\PaymentAdvicePage;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$pcn = $_GET['pcn'] ?? '';

if ($pcn === '') {
    echo json_encode(['success' => false, 'message' => 'Missing patient control number']);
    exit;
}

$status = PaymentAdvicePage::getOpenEmrClaimStatus($pcn);

echo json_encode([
    'success' => true,
    'status' => $status,
]);
