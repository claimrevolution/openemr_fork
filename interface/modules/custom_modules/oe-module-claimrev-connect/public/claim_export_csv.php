<?php

/**
 * AJAX endpoint to export claims search as CSV via ClaimRev API.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ClaimRevConnector\ClaimRevException;
use OpenEMR\Modules\ClaimRevConnector\ClaimsPage;
use OpenEMR\Modules\ClaimRevConnector\CsrfHelper;
use OpenEMR\Modules\ClaimRevConnector\ModuleInput;

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!CsrfHelper::verifyCsrfToken(ModuleInput::postString('csrf_token'), 'claims')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Parse the search form fields the same way claims.php does so the same
// filter set produces the same CSV. Using ModuleInput routes through
// filter_input(INPUT_POST) instead of touching $_POST directly.
$exportFilters = [
    'patFirstName' => ModuleInput::postString('patFirstName'),
    'patLastName' => ModuleInput::postString('patLastName'),
    'patientGender' => ModuleInput::postString('patientGender'),
    'patientBirthDate' => ModuleInput::postString('patientBirthDate'),
    'startDate' => ModuleInput::postString('startDate'),
    'endDate' => ModuleInput::postString('endDate'),
    'serviceDateStart' => ModuleInput::postString('serviceDateStart'),
    'serviceDateEnd' => ModuleInput::postString('serviceDateEnd'),
    'payerName' => ModuleInput::postString('payerName'),
    'payerNumber' => ModuleInput::postString('payerNumber'),
    'payerPaidAmtStart' => ModuleInput::postString('payerPaidAmtStart'),
    'payerPaidAmtEnd' => ModuleInput::postString('payerPaidAmtEnd'),
    'traceNumber' => ModuleInput::postString('traceNumber'),
    'patientControlNumber' => ModuleInput::postString('patientControlNumber'),
    'payerControlNumber' => ModuleInput::postString('payerControlNumber'),
    'billingProviderNpi' => ModuleInput::postString('billingProviderNpi'),
    'errorMessage' => ModuleInput::postString('errorMessage'),
    'statusId' => ModuleInput::postString('statusId'),
    'sortField' => ModuleInput::postString('sortField'),
    'sortDirection' => ModuleInput::postString('sortDirection'),
];

try {
    $result = ClaimsPage::exportCsv($exportFilters);
    $fileText = $result['fileText'] ?? '';
    $fileName = $result['fileName'] ?? 'claims_export.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . attr($fileName) . '"');
    file_put_contents('php://output', $fileText);
} catch (\RuntimeException | \LogicException $t) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to export CSV']);
}
