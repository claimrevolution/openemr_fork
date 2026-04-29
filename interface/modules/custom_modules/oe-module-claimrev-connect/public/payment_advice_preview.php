<?php

/**
 * AJAX endpoint: preview what a payment advice posting would do.
 *
 * Accepts POST with the full payment data JSON (from search results)
 * and returns a preview of what would be posted to OpenEMR.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ClaimRevConnector\Compat\CsrfHelper;
use OpenEMR\Modules\ClaimRevConnector\PaymentAdvicePostingService;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!CsrfHelper::verifyCsrfToken($_POST['csrf_token'] ?? '', 'payment_advice')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$paymentDataJson = $_POST['paymentData'] ?? '';
$paymentData = json_decode((string) $paymentDataJson, true);

if (!is_array($paymentData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payment data']);
    exit;
}

$preview = PaymentAdvicePostingService::preview($paymentData);

// If already posted, include the actual posting details
if ($preview['alreadyPosted']) {
    $paymentAdviceId = $paymentData['paymentAdviceId'] ?? '';
    $postingDetails = PaymentAdvicePostingService::getPostingDetails(
        $paymentAdviceId,
        $preview['pid'],
        $preview['encounter']
    );
    $preview['postingDetails'] = $postingDetails;
}

echo json_encode($preview);
