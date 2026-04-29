<?php

/**
 * Denial Analytics — analyze denial patterns by payer, reason code, and trend.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once "../../../../globals.php";

use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Core\Header;
use OpenEMR\Modules\ClaimRevConnector\Compat\CsrfHelper;
use OpenEMR\Modules\ClaimRevConnector\DenialAnalyticsService;

$tab = "denial_analytics";

if (!AclMain::aclCheckCore('acct', 'bill')) {
    AccessDeniedHelper::denyWithTemplate(
        "ACL check failed for acct/bill: ClaimRev Connect - Denial Analytics",
        xl("ClaimRev Connect - Denial Analytics")
    );
}

// CSV export
if (isset($_POST['export_csv']) && CsrfHelper::verifyCsrfToken($_POST['csrf_token'] ?? '', 'denials')) {
    $data = DenialAnalyticsService::getAnalytics($_POST);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="denial_analytics_' . date('Y-m-d') . '.csv"');
    file_put_contents('php://output', DenialAnalyticsService::toCsv($data['byReason']));
    exit;
}

$csrfToken = CsrfHelper::collectCsrfToken('denials');
$data = null;
$searched = false;

// Default: auto-run on page load with last 12 months
$filters = $_POST;
if (empty($filters['dateStart'])) {
    $filters['dateStart'] = date('Y-m-d', strtotime('-12 months'));
}
if (empty($filters['dateEnd'])) {
    $filters['dateEnd'] = date('Y-m-d');
}

if (!empty($_POST) && isset($_POST['SubmitButton'])) {
    $searched = true;
    $data = DenialAnalyticsService::getAnalytics($filters);
} elseif (empty($_POST)) {
    // Auto-run on first load
    $searched = true;
    $data = DenialAnalyticsService::getAnalytics($filters);
}
?>

<html>
    <head>
        <title><?php echo xlt("ClaimRev Connect - Denial Analytics"); ?></title>
        <?php Header::setupHeader(); ?>
        <style>
            .denial-table th, .denial-table td { font-size: 0.85em; padding: 5px 8px; }
            .section-title { font-size: 0.9em; font-weight: 600; color: #555; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; margin-top: 16px; }
            .summary-cards .card { min-width: 120px; }
            .summary-cards .card-body { padding: 10px; text-align: center; }
            .summary-cards h5 { margin: 0; }
            .summary-cards small { color: #666; }
            .reason-bar { height: 8px; border-radius: 4px; background: #e9ecef; overflow: hidden; }
            .reason-bar-fill { height: 100%; border-radius: 4px; background: #dc3545; }
            .trend-cell { font-size: 0.8em; text-align: center; }
            .carc-desc { font-size: 0.75em; color: #888; }
        </style>
    </head>
    <body class="body_top">
        <div class="container-fluid">
            <?php require '../templates/navbar.php'; ?>
            <form method="post" action="denial_analytics.php" id="denialForm">
                <input type="hidden" name="csrf_token" value="<?php echo attr($csrfToken); ?>"/>
                <div class="card mt-3">
                    <div class="card-header">
                        <?php echo xlt("Denial & Adjustment Analytics"); ?>
                    </div>
                    <div class="card-body pb-0">
                        <div class="form-row">
                            <div class="form-group col-md-2">
                                <label for="dateStart"><?php echo xlt("From"); ?></label>
                                <input type="date" class="form-control form-control-sm" id="dateStart" name="dateStart" value="<?php echo attr($filters['dateStart']); ?>"/>
                            </div>
                            <div class="form-group col-md-2">
                                <label for="dateEnd"><?php echo xlt("To"); ?></label>
                                <input type="date" class="form-control form-control-sm" id="dateEnd" name="dateEnd" value="<?php echo attr($filters['dateEnd']); ?>"/>
                            </div>
                            <div class="form-group col-md-3">
                                <label for="payerName"><?php echo xlt("Payer"); ?></label>
                                <input type="text" class="form-control form-control-sm" id="payerName" name="payerName" value="<?php echo isset($_POST['payerName']) ? attr($_POST['payerName']) : ''; ?>"/>
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <button type="submit" name="SubmitButton" class="btn btn-primary btn-sm btn-block"><?php echo xlt("Analyze"); ?></button>
                            </div>
                            <?php if ($data !== null) { ?>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <button type="submit" name="export_csv" value="1" class="btn btn-outline-secondary btn-sm btn-block"><i class="fa fa-download"></i> <?php echo xlt("Export CSV"); ?></button>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </form>

        <?php if ($data !== null) { ?>
            <?php $summary = $data['summary']; ?>

            <!-- Summary cards -->
            <div class="d-flex summary-cards mt-3 mb-2" style="gap: 10px;">
                <div class="card">
                    <div class="card-body">
                        <h5><?php echo text($summary['totalAdjustments']); ?></h5>
                        <small><?php echo xlt("Total Adjustments"); ?></small>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5 class="text-danger">$<?php echo text(number_format($summary['totalAmount'], 0)); ?></h5>
                        <small><?php echo xlt("Total Adjusted"); ?></small>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5><?php echo text($summary['affectedEncounters']); ?></h5>
                        <small><?php echo xlt("Encounters"); ?></small>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h5><?php echo text($summary['payerCount']); ?></h5>
                        <small><?php echo xlt("Payers"); ?></small>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- By Reason Code -->
                <div class="col-md-7">
                    <div class="section-title"><?php echo xlt("Top Adjustment Reasons"); ?></div>
                    <table class="table table-sm table-bordered denial-table">
                        <thead class="thead-light">
                            <tr>
                                <th><?php echo xlt("Reason"); ?></th>
                                <th><?php echo xlt("CARC"); ?></th>
                                <th class="text-right"><?php echo xlt("Count"); ?></th>
                                <th class="text-right"><?php echo xlt("Amount"); ?></th>
                                <th style="width:100px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $maxCount = !empty($data['byReason']) ? max(array_column($data['byReason'], 'count')) : 1;
                            foreach ($data['byReason'] as $r) {
                                $pct = ($r['count'] / max($maxCount, 1)) * 100;
                            ?>
                            <tr>
                                <td>
                                    <?php echo text($r['reason']); ?>
                                    <?php if ($r['carcDescription'] !== '') { ?>
                                        <br/><span class="carc-desc"><?php echo text($r['carcDescription']); ?></span>
                                    <?php } ?>
                                </td>
                                <td><?php echo $r['carcCode'] !== '' ? text($r['carcCode']) : '<span class="text-muted">—</span>'; ?></td>
                                <td class="text-right"><?php echo text($r['count']); ?></td>
                                <td class="text-right">$<?php echo text(number_format($r['totalAmount'], 2)); ?></td>
                                <td>
                                    <div class="reason-bar">
                                        <div class="reason-bar-fill" style="width:<?php echo attr(round($pct)); ?>%;"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <!-- By Payer -->
                <div class="col-md-5">
                    <div class="section-title"><?php echo xlt("By Payer"); ?></div>
                    <table class="table table-sm table-bordered denial-table">
                        <thead class="thead-light">
                            <tr>
                                <th><?php echo xlt("Payer"); ?></th>
                                <th class="text-right"><?php echo xlt("Adj"); ?></th>
                                <th class="text-right"><?php echo xlt("Amount"); ?></th>
                                <th class="text-right"><?php echo xlt("Enc"); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['byPayer'] as $p) { ?>
                            <tr>
                                <td><?php echo text($p['payerName']); ?></td>
                                <td class="text-right"><?php echo text($p['count']); ?></td>
                                <td class="text-right">$<?php echo text(number_format($p['totalAmount'], 2)); ?></td>
                                <td class="text-right"><?php echo text($p['encounterCount']); ?></td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>

                    <!-- Monthly Trend -->
                    <div class="section-title"><?php echo xlt("Monthly Trend"); ?></div>
                    <?php if (!empty($data['byMonth'])) { ?>
                        <?php $maxMonthCount = max(array_column($data['byMonth'], 'count')); ?>
                        <table class="table table-sm table-bordered denial-table">
                            <thead class="thead-light">
                                <tr>
                                    <th><?php echo xlt("Month"); ?></th>
                                    <th class="text-right"><?php echo xlt("Count"); ?></th>
                                    <th class="text-right"><?php echo xlt("Amount"); ?></th>
                                    <th style="width:80px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['byMonth'] as $m) {
                                    $pct = ($m['count'] / max($maxMonthCount, 1)) * 100;
                                ?>
                                <tr>
                                    <td><?php echo text($m['month']); ?></td>
                                    <td class="text-right"><?php echo text($m['count']); ?></td>
                                    <td class="text-right">$<?php echo text(number_format($m['totalAmount'], 2)); ?></td>
                                    <td>
                                        <div class="reason-bar">
                                            <div class="reason-bar-fill" style="width:<?php echo attr(round($pct)); ?>%; background:#6c757d;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <div class="text-muted"><?php echo xlt("No monthly data"); ?></div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        </div>
    </body>
</html>
