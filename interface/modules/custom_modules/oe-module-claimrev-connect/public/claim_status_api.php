<?php

/**
 * AJAX endpoint for Claim Status Dashboard actions.
 *
 * Handles timeline loading, status checks, sync, and manual notes.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Modules\ClaimRevConnector\ClaimTrackingService;
use OpenEMR\Modules\ClaimRevConnector\Compat\CsrfHelper;

header('Content-Type: application/json');

if (!AclMain::aclCheckCore('acct', 'bill')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!CsrfHelper::verifyCsrfToken($_POST['csrf_token'] ?? '', 'claim_status')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'get_timeline':
        $pid = (int) ($_POST['pid'] ?? 0);
        $encounter = (int) ($_POST['encounter'] ?? 0);
        $payerType = (int) ($_POST['payer_type'] ?? 0);

        if ($pid <= 0 || $encounter <= 0) {
            echo json_encode(['error' => 'Invalid pid/encounter']);
            exit;
        }

        $timeline = ClaimTrackingService::getClaimTimeline($pid, $encounter, $payerType);
        $record = ClaimTrackingService::getClaimRecord($pid, $encounter, $payerType ?: 1);
        echo json_encode(['timeline' => $timeline, 'record' => $record]);
        break;

    case 'check_status':
        $pid = (int) ($_POST['pid'] ?? 0);
        $encounter = (int) ($_POST['encounter'] ?? 0);
        $payerType = (int) ($_POST['payer_type'] ?? 1);

        if ($pid <= 0 || $encounter <= 0) {
            echo json_encode(['error' => 'Invalid pid/encounter']);
            exit;
        }

        $result = ClaimTrackingService::checkStatus276($pid, $encounter, $payerType);
        echo json_encode($result);
        break;

    case 'batch_sync':
        $pcnsJson = $_POST['pcns'] ?? '';
        $pcns = json_decode((string) $pcnsJson, true);

        if (!is_array($pcns) || empty($pcns)) {
            echo json_encode(['error' => 'Invalid PCN list']);
            exit;
        }

        $result = ClaimTrackingService::batchSyncFromClaimRev($pcns);
        echo json_encode($result);
        break;

    case 'add_note':
        $pid = (int) ($_POST['pid'] ?? 0);
        $encounter = (int) ($_POST['encounter'] ?? 0);
        $payerType = (int) ($_POST['payer_type'] ?? 1);
        $noteText = trim($_POST['note_text'] ?? '');

        if ($pid <= 0 || $encounter <= 0 || $noteText === '') {
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }

        $eventId = ClaimTrackingService::logEvent(
            $pid,
            $encounter,
            $payerType,
            ClaimTrackingService::EVENT_MANUAL_NOTE,
            ClaimTrackingService::SOURCE_USER,
            detailText: $noteText,
        );

        echo json_encode(['success' => true, 'event_id' => $eventId]);
        break;

    case 'get_stats':
        $stats = ClaimTrackingService::getDashboardStats($_POST);
        echo json_encode($stats);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
        break;
}
