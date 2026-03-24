<?php

/**
 * AJAX endpoint for Patient Balance queue actions.
 *
 * Handles balance detail, statement logging, history, notes, and stats.
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
use OpenEMR\Modules\ClaimRevConnector\PatientBalanceService;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!CsrfHelper::verifyCsrfToken($_POST['csrf_token'] ?? '', 'patient_balance')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_detail':
        $pid = (int) ($_POST['pid'] ?? 0);
        $encounter = (int) ($_POST['encounter'] ?? 0);

        if ($pid <= 0 || $encounter <= 0) {
            echo json_encode(['error' => 'Invalid pid/encounter']);
            exit;
        }

        $detail = PatientBalanceService::getBalanceDetail($pid, $encounter);
        $history = PatientBalanceService::getStatementHistory($pid, $encounter);
        echo json_encode(['detail' => $detail, 'history' => $history]);
        break;

    case 'log_statement':
        $pid = (int) ($_POST['pid'] ?? 0);
        $encounter = (int) ($_POST['encounter'] ?? 0);
        $method = trim($_POST['method'] ?? 'openemr_print');
        $amount = (float) ($_POST['amount'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($pid <= 0 || $encounter <= 0) {
            echo json_encode(['error' => 'Invalid pid/encounter']);
            exit;
        }

        $allowedMethods = ['openemr_print', 'openemr_email', 'openemr_portal', 'claimrev'];
        if (!in_array($method, $allowedMethods)) {
            $method = 'openemr_print';
        }

        $id = PatientBalanceService::logStatement($pid, $encounter, $method, $amount, $notes);
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'get_history':
        $pid = (int) ($_POST['pid'] ?? 0);
        $encounter = (int) ($_POST['encounter'] ?? 0);

        if ($pid <= 0 || $encounter <= 0) {
            echo json_encode(['error' => 'Invalid pid/encounter']);
            exit;
        }

        $history = PatientBalanceService::getStatementHistory($pid, $encounter);
        echo json_encode(['history' => $history]);
        break;

    case 'add_note':
        $pid = (int) ($_POST['pid'] ?? 0);
        $encounter = (int) ($_POST['encounter'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        if ($pid <= 0 || $encounter <= 0 || $notes === '') {
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }

        $id = PatientBalanceService::logStatement($pid, $encounter, 'openemr_print', 0, $notes);
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'get_stats':
        $stats = PatientBalanceService::getQueueStats($_POST);
        echo json_encode($stats);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        break;
}
