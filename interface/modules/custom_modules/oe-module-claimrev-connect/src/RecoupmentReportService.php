<?php

/**
 * Service for the Recoupment Report.
 *
 * Identifies claims where payments were reversed (recouped) — typically
 * from Medicare reprocessing — and shows the original payment, recoupment,
 * any reprocessed payment, and the net impact.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector;

use OpenEMR\Common\Database\QueryUtils;

class RecoupmentReportService
{
    /**
     * Get encounters that have had recoupments (negative payments).
     *
     * @param array<string, mixed> $filters dateStart, dateEnd, payerName, patientName
     * @return array{recoupments: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public static function getRecoupmentReport(array $filters = []): array
    {
        $where = ["a.pay_amount < 0", "a.deleted IS NULL"];
        $params = [];

        if (!empty($filters['dateStart'])) {
            $where[] = "a.post_time >= ?";
            $params[] = $filters['dateStart'] . ' 00:00:00';
        }
        if (!empty($filters['dateEnd'])) {
            $where[] = "a.post_time <= ?";
            $params[] = $filters['dateEnd'] . ' 23:59:59';
        }
        if (!empty($filters['payerName'])) {
            $where[] = "ic.name LIKE ?";
            $params[] = '%' . $filters['payerName'] . '%';
        }
        if (!empty($filters['patientName'])) {
            $where[] = "(p.lname LIKE ? OR p.fname LIKE ?)";
            $params[] = '%' . $filters['patientName'] . '%';
            $params[] = '%' . $filters['patientName'] . '%';
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        // Find all encounters with negative payments (recoupments)
        $sql = "SELECT a.pid, a.encounter, a.code, a.modifier, " .
            "a.pay_amount AS recoup_amount, a.memo AS recoup_memo, " .
            "a.post_time AS recoup_date, a.session_id AS recoup_session_id, " .
            "s.reference AS recoup_reference, s.check_date AS recoup_check_date, " .
            "p.fname, p.lname, p.DOB, " .
            "fe.date AS encounter_date, " .
            "ic.name AS payer_name " .
            "FROM ar_activity a " .
            "JOIN ar_session s ON s.session_id = a.session_id " .
            "JOIN patient_data p ON p.pid = a.pid " .
            "JOIN form_encounter fe ON fe.pid = a.pid AND fe.encounter = a.encounter " .
            "LEFT JOIN insurance_companies ic ON ic.id = s.payer_id " .
            $whereClause . " " .
            "ORDER BY a.post_time DESC";

        $rows = QueryUtils::fetchRecords($sql, $params);

        // For each recoupment, find the original payment and any reprocessed payment
        $recoupments = [];
        $totalRecouped = 0.0;
        $totalOriginal = 0.0;
        $totalReprocessed = 0.0;

        foreach ($rows as $row) {
            $pid = (int) $row['pid'];
            $encounter = (int) $row['encounter'];
            $recoupAmount = round((float) $row['recoup_amount'], 2); // negative
            $recoupSessionId = (int) $row['recoup_session_id'];

            // Get all positive payments for this encounter to find original and reprocessed
            $payments = QueryUtils::fetchRecords(
                "SELECT a.pay_amount, a.post_time, a.memo, a.code, a.session_id, " .
                "s.reference, s.check_date " .
                "FROM ar_activity a " .
                "JOIN ar_session s ON s.session_id = a.session_id " .
                "WHERE a.pid = ? AND a.encounter = ? AND a.pay_amount > 0 AND a.deleted IS NULL " .
                "ORDER BY a.post_time",
                [$pid, $encounter]
            );

            // Classify payments as original (before recoup) or reprocessed (after recoup)
            $recoupDate = $row['recoup_date'];
            $originalPayments = [];
            $reprocessedPayments = [];
            $originalTotal = 0.0;
            $reprocessedTotal = 0.0;

            foreach ($payments as $pmt) {
                $pmtAmount = round((float) $pmt['pay_amount'], 2);
                if ($pmt['post_time'] <= $recoupDate && $pmt['session_id'] != $recoupSessionId) {
                    $originalPayments[] = $pmt;
                    $originalTotal += $pmtAmount;
                } elseif ($pmt['post_time'] > $recoupDate) {
                    $reprocessedPayments[] = $pmt;
                    $reprocessedTotal += $pmtAmount;
                }
            }

            $netImpact = round($recoupAmount + $reprocessedTotal, 2);

            // Get current encounter balance
            $balance = self::getEncounterBalance($pid, $encounter);

            $recoupments[] = [
                'pid' => $pid,
                'encounter' => $encounter,
                'patientName' => ($row['lname'] ?? '') . ', ' . ($row['fname'] ?? ''),
                'patientDob' => substr((string) ($row['DOB'] ?? ''), 0, 10),
                'encounterDate' => substr((string) $row['encounter_date'], 0, 10),
                'payerName' => $row['payer_name'] ?? '',
                'code' => $row['code'] ?? '',
                'recoupAmount' => $recoupAmount,
                'recoupDate' => substr((string) $recoupDate, 0, 10),
                'recoupReference' => $row['recoup_reference'] ?? '',
                'recoupCheckDate' => $row['recoup_check_date'] ?? '',
                'recoupMemo' => $row['recoup_memo'] ?? '',
                'originalTotal' => round($originalTotal, 2),
                'reprocessedTotal' => round($reprocessedTotal, 2),
                'netImpact' => $netImpact,
                'currentBalance' => $balance,
                'hasReprocessed' => !empty($reprocessedPayments),
                'originalPayments' => array_map(fn($p) => [
                    'amount' => round((float) $p['pay_amount'], 2),
                    'date' => substr((string) $p['post_time'], 0, 10),
                    'reference' => $p['reference'] ?? '',
                    'memo' => $p['memo'] ?? '',
                ], $originalPayments),
                'reprocessedPayments' => array_map(fn($p) => [
                    'amount' => round((float) $p['pay_amount'], 2),
                    'date' => substr((string) $p['post_time'], 0, 10),
                    'reference' => $p['reference'] ?? '',
                    'memo' => $p['memo'] ?? '',
                ], $reprocessedPayments),
            ];

            $totalRecouped += $recoupAmount;
            $totalOriginal += $originalTotal;
            $totalReprocessed += $reprocessedTotal;
        }

        return [
            'recoupments' => $recoupments,
            'summary' => [
                'count' => count($recoupments),
                'totalRecouped' => round($totalRecouped, 2),
                'totalOriginal' => round($totalOriginal, 2),
                'totalReprocessed' => round($totalReprocessed, 2),
                'netImpact' => round($totalRecouped + $totalReprocessed, 2),
                'pendingReprocess' => count(array_filter($recoupments, fn($r) => !$r['hasReprocessed'])),
            ],
        ];
    }

    /**
     * Get current balance for an encounter.
     */
    private static function getEncounterBalance(int $pid, int $encounter): float
    {
        $row = QueryUtils::fetchRecords(
            "SELECT " .
            "(COALESCE((SELECT SUM(b.fee) FROM billing b WHERE b.pid = ? AND b.encounter = ? AND b.activity = 1), 0) " .
            "+ COALESCE((SELECT SUM(ds.fee) FROM drug_sales ds WHERE ds.pid = ? AND ds.encounter = ?), 0) " .
            "- COALESCE((SELECT SUM(a.pay_amount) FROM ar_activity a WHERE a.pid = ? AND a.encounter = ? AND a.deleted IS NULL), 0) " .
            "- COALESCE((SELECT SUM(a.adj_amount) FROM ar_activity a WHERE a.pid = ? AND a.encounter = ? AND a.deleted IS NULL), 0)" .
            ") AS balance",
            [$pid, $encounter, $pid, $encounter, $pid, $encounter, $pid, $encounter]
        );
        return round((float) ($row[0]['balance'] ?? 0), 2);
    }

    /**
     * Export as CSV.
     *
     * @param list<array<string, mixed>> $recoupments
     */
    public static function toCsv(array $recoupments): string
    {
        $output = "Patient,Encounter,Service Date,Payer,Code,Original Paid,Recoup Amount,Recoup Date,Reference,Reprocessed,Net Impact,Current Balance,Status\n";
        foreach ($recoupments as $r) {
            $status = $r['hasReprocessed'] ? 'Reprocessed' : 'Pending Reprocess';
            $output .= '"' . str_replace('"', '""', $r['patientName']) . '",';
            $output .= $r['encounter'] . ',';
            $output .= $r['encounterDate'] . ',';
            $output .= '"' . str_replace('"', '""', $r['payerName']) . '",';
            $output .= '"' . str_replace('"', '""', $r['code']) . '",';
            $output .= $r['originalTotal'] . ',';
            $output .= $r['recoupAmount'] . ',';
            $output .= $r['recoupDate'] . ',';
            $output .= '"' . str_replace('"', '""', $r['recoupReference']) . '",';
            $output .= $r['reprocessedTotal'] . ',';
            $output .= $r['netImpact'] . ',';
            $output .= $r['currentBalance'] . ',';
            $output .= $status . "\n";
        }
        return $output;
    }
}
