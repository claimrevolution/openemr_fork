<?php

/**
 * AJAX endpoint: post payment advice(s) to OpenEMR.
 *
 * Supports single post (paymentData) and batch post (paymentDataList).
 * All requests are duplicate-checked before posting.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ClaimRevConnector\CsrfHelper;
use OpenEMR\Modules\ClaimRevConnector\ModuleInput;
use OpenEMR\Modules\ClaimRevConnector\PaymentAdvicePostingService;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!CsrfHelper::verifyCsrfToken(ModuleInput::postString('csrf_token'), 'payment_advice')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$mode = ModuleInput::postString('mode', 'single');
$skipMarkWorked = ModuleInput::postExists('testMode');

if ($mode === 'batch') {
    $paymentDataListJson = ModuleInput::postString('paymentDataList');
    $paymentDataList = json_decode($paymentDataListJson, true);

    if (!is_array($paymentDataList)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payment data list']);
        exit;
    }

    $result = PaymentAdvicePostingService::batchPost($paymentDataList, $skipMarkWorked);
    echo json_encode($result);
} else {
    $paymentDataJson = ModuleInput::postString('paymentData');
    $paymentData = json_decode($paymentDataJson, true);

    if (!is_array($paymentData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payment data']);
        exit;
    }

    $approved = ModuleInput::postExists('approved');
    $result = PaymentAdvicePostingService::post($paymentData, $skipMarkWorked, $approved);
    echo json_encode($result);
}
