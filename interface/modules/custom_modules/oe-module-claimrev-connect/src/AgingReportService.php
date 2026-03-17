<?php

/**
 * Service for AR Aging Report.
 *
 * Calculates 30/60/90/120/120+ day aging buckets from OpenEMR billing
 * data, grouped by payer.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector;

use OpenEMR\Common\Database\QueryUtils;

class AgingReportService
{
    /**
     * Get aging report grouped by payer.
     *
     * @param array<string, mixed> $filters Optional filters: payerName, patientName, minAmount
     * @return array{payers: list<array<string, mixed>>, totals: array<string, float>, encounters: list<array<string, mixed>>}
     */
    public static function getAgingReport(array $filters = []): array
    {
        $where = ["fe.date >= DATE_SUB(NOW(), INTERVAL 730 DAY)"];
        $params = [];

        if (!empty($filters['payerName'])) {
            $where[] = "ic.name LIKE ?";
            $params[] = '%' . $filters['payerName'] . '%';
        }
        if (!empty($filters['patientName'])) {
            $where[] = "(p.lname LIKE ? OR p.fname LIKE ?)";
            $params[] = '%' . $filters['patientName'] . '%';
            $params[] = '%' . $filters['patientName'] . '%';
        }
        $minAmount = isset($filters['minAmount']) && $filters['minAmount'] !== '' ? (float) $filters['minAmount'] : 0.01;

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $balanceSql = "(COALESCE((SELECT SUM(b.fee) FROM billing b WHERE b.pid = fe.pid AND b.encounter = fe.encounter AND b.activity = 1), 0) " .
            "+ COALESCE((SELECT SUM(ds.fee) FROM drug_sales ds WHERE ds.pid = fe.pid AND ds.encounter = fe.encounter), 0) " .
            "- COALESCE((SELECT SUM(a.pay_amount) FROM ar_activity a WHERE a.pid = fe.pid AND a.encounter = fe.encounter AND a.deleted IS NULL), 0) " .
            "- COALESCE((SELECT SUM(a.adj_amount) FROM ar_activity a WHERE a.pid = fe.pid AND a.encounter = fe.encounter AND a.deleted IS NULL), 0))";

        // Per-encounter detail with aging bucket
        $sql = "SELECT fe.pid, fe.encounter, fe.date AS encounter_date, " .
            "p.fname, p.lname, " .
            "COALESCE(ic.name, 'Self-Pay') AS payer_name, ic.id AS payer_id, " .
            "DATEDIFF(NOW(), fe.date) AS age_days, " .
            "$balanceSql AS balance, " .
            "fe.last_level_closed, fe.stmt_count " .
            "FROM form_encounter fe " .
            "JOIN patient_data p ON p.pid = fe.pid " .
            "LEFT JOIN insurance_data id ON id.pid = fe.pid AND id.type = 'primary' AND id.date <= fe.date " .
            "LEFT JOIN insurance_companies ic ON ic.id = id.provider " .
            $whereClause . " " .
            "GROUP BY fe.pid, fe.encounter " .
            "HAVING balance > ? " .
            "ORDER BY payer_name, age_days DESC";

        $rows = QueryUtils::fetchRecords($sql, array_merge($params, [$minAmount]));

        // Group by payer with aging buckets
        $payerMap = [];
        $totals = ['current' => 0, 'days30' => 0, 'days60' => 0, 'days90' => 0, 'days120' => 0, 'days120plus' => 0, 'total' => 0];
        $encounters = [];

        foreach ($rows as $row) {
            $payerName = $row['payer_name'] ?: 'Self-Pay';
            $balance = round((float) $row['balance'], 2);
            $ageDays = (int) $row['age_days'];

            $bucket = self::getBucket($ageDays);

            if (!isset($payerMap[$payerName])) {
                $payerMap[$payerName] = [
                    'payerName' => $payerName,
                    'payerId' => $row['payer_id'] ?? null,
                    'current' => 0, 'days30' => 0, 'days60' => 0, 'days90' => 0, 'days120' => 0, 'days120plus' => 0, 'total' => 0,
                    'encounterCount' => 0,
                ];
            }

            $payerMap[$payerName][$bucket] += $balance;
            $payerMap[$payerName]['total'] += $balance;
            $payerMap[$payerName]['encounterCount']++;

            $totals[$bucket] += $balance;
            $totals['total'] += $balance;

            $encounters[] = [
                'pid' => (int) $row['pid'],
                'encounter' => (int) $row['encounter'],
                'encounterDate' => substr((string) $row['encounter_date'], 0, 10),
                'patientName' => ($row['lname'] ?? '') . ', ' . ($row['fname'] ?? ''),
                'payerName' => $payerName,
                'ageDays' => $ageDays,
                'bucket' => $bucket,
                'balance' => $balance,
                'lastLevelClosed' => (int) $row['last_level_closed'],
                'stmtCount' => (int) $row['stmt_count'],
            ];
        }

        // Sort payers by total descending
        $payers = array_values($payerMap);
        usort($payers, fn($a, $b) => $b['total'] <=> $a['total']);

        // Round totals
        foreach ($totals as &$v) {
            $v = round($v, 2);
        }

        return ['payers' => $payers, 'totals' => $totals, 'encounters' => $encounters];
    }

    /**
     * Get the aging bucket name for a given age in days.
     */
    private static function getBucket(int $ageDays): string
    {
        if ($ageDays <= 30) {
            return 'current';
        }
        if ($ageDays <= 60) {
            return 'days30';
        }
        if ($ageDays <= 90) {
            return 'days60';
        }
        if ($ageDays <= 120) {
            return 'days90';
        }
        if ($ageDays <= 150) {
            return 'days120';
        }
        return 'days120plus';
    }

    /**
     * Export aging data as CSV string.
     *
     * @param list<array<string, mixed>> $encounters
     */
    public static function toCsv(array $encounters): string
    {
        $output = "Patient,Encounter,Service Date,Payer,Age Days,Bucket,Balance,Ins Level,Stmts Sent\n";
        foreach ($encounters as $enc) {
            $output .= '"' . str_replace('"', '""', $enc['patientName']) . '",';
            $output .= $enc['encounter'] . ',';
            $output .= $enc['encounterDate'] . ',';
            $output .= '"' . str_replace('"', '""', $enc['payerName']) . '",';
            $output .= $enc['ageDays'] . ',';
            $output .= $enc['bucket'] . ',';
            $output .= $enc['balance'] . ',';
            $output .= $enc['lastLevelClosed'] . ',';
            $output .= $enc['stmtCount'] . "\n";
        }
        return $output;
    }
}
