<?php

/**
 * Background service that proactively queues eligibility checks for upcoming
 * appointments on configured days of the week.
 *
 * Runs once daily (execute_interval=1440). On each run it checks whether today
 * is a configured sweep day. If so, it looks ahead N days for appointments
 * whose eligibility is missing, stale, or in an error state and queues them
 * for the existing send/receive service to process.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Modules\ClaimRevConnector\AppointmentsPage;
use OpenEMR\Modules\ClaimRevConnector\GlobalConfig;
use OpenEMR\Modules\ClaimRevConnector\TypeCoerce;

function start_eligibility_sweep(): void
{
    $globals = OEGlobalsBag::getInstance();
    $logger = ServiceContainer::getLogger();

    // Check if sweep is enabled
    $enabled = TypeCoerce::asString($globals->get(GlobalConfig::CONFIG_ENABLE_SWEEP) ?? '');
    if ($enabled === '') {
        return;
    }

    // Check if today is a configured sweep day
    $sweepDaysConfig = TypeCoerce::asString($globals->get(GlobalConfig::CONFIG_SWEEP_DAYS) ?? '1,4');
    if ($sweepDaysConfig === '') {
        $sweepDaysConfig = '1,4';
    }
    $sweepDays = array_map(intval(...), array_filter(explode(',', $sweepDaysConfig)));
    $todayDow = (int) date('w'); // 0=Sun, 1=Mon, ..., 6=Sat

    if (!in_array($todayDow, $sweepDays, true)) {
        return;
    }

    // Calculate the date range
    $lookahead = TypeCoerce::asInt($globals->get(GlobalConfig::CONFIG_SWEEP_LOOKAHEAD) ?? 7, 7);
    if ($lookahead < 1) {
        $lookahead = 7;
    }
    $startDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime('+' . $lookahead . ' days'));

    // Get the stale age threshold (in days)
    $staleAge = TypeCoerce::asInt($globals->get(GlobalConfig::CONFIG_ENABLE_RESULTS_ELIGIBILITY) ?? 30, 30);
    if ($staleAge < 1) {
        $staleAge = 30;
    }

    try {
        // Find appointments that need eligibility checks:
        // - No eligibility record (never checked)
        // - Error state
        // - Stale (older than threshold)
        // Exclude appointments already queued (waiting/creating)
        $sql = "SELECT DISTINCT e.pc_eid
                FROM openemr_postcalendar_events AS e
                INNER JOIN patient_data AS p ON e.pc_pid = p.pid
                LEFT JOIN mod_claimrev_eligibility AS elig ON (
                    elig.pid = e.pc_pid
                    AND elig.payer_responsibility = 'P'
                )
                WHERE e.pc_eventDate >= ?
                AND e.pc_eventDate <= ?
                AND e.pc_pid > 0
                AND (
                    elig.id IS NULL
                    OR elig.status IN ('error', 'senderror')
                    OR DATEDIFF(NOW(), COALESCE(elig.last_checked, elig.create_date)) >= ?
                )
                AND (elig.status IS NULL OR elig.status NOT IN ('waiting', 'creating'))";

        $rows = QueryUtils::fetchRecords($sql, [$startDate, $endDate, $staleAge]);

        $count = 0;
        foreach ($rows as $row) {
            $pcEid = TypeCoerce::asString($row['pc_eid'] ?? '');
            if ($pcEid === '') {
                continue;
            }
            AppointmentsPage::runEligibilityForAppointment($pcEid);
            $count++;
        }

        if ($count > 0) {
            $logger->info('ClaimRev Eligibility Sweep queued appointments', [
                'count' => $count,
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);
        }
    } catch (\Exception $e) {
        $logger->error('ClaimRev Eligibility Sweep error', ['exception' => $e]);
    }
}
