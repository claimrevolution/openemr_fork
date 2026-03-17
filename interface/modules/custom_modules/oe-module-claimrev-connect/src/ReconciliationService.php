<?php

/**
 * Service for reconciling OpenEMR encounters against ClaimRev claim statuses.
 *
 * Queries billed encounters from OpenEMR, looks them up in ClaimRev via
 * the SearchClaimsPaged API (using batch patientControlNumbers), and
 * returns a merged view showing discrepancies.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector;

use OpenEMR\Common\Database\QueryUtils;

class ReconciliationService
{
    /**
     * OpenEMR claim status labels.
     *
     * @var array<int, string>
     */
    private const OE_STATUS_LABELS = [
        0 => 'Not Billed',
        1 => 'Unbilled',
        2 => 'Billed',
        6 => 'Crossover',
        7 => 'Denied',
    ];

    /**
     * Reconcile OpenEMR encounters with ClaimRev.
     *
     * @param array<string, mixed> $filters Search filters from the form
     * @return array{encounters: list<array<string, mixed>>, totalRecords: int, claimRevLookupFailed: bool}
     */
    public static function reconcile(array $filters): array
    {
        $pageIndex = isset($filters['pageIndex']) ? (int) $filters['pageIndex'] : 0;
        $pageSize = 50;
        $offset = $pageIndex * $pageSize;
        $statusFilter = $filters['statusFilter'] ?? 'billed';
        $discrepancyOnly = !empty($filters['discrepancyOnly']);

        // Build WHERE clause for OpenEMR encounters
        $where = [];
        $params = [];

        // Status filter
        if ($statusFilter === 'billed') {
            $where[] = "c.status IN (2, 6)";
        } elseif ($statusFilter === 'denied') {
            $where[] = "c.status = 7";
        } elseif ($statusFilter === 'all_billed') {
            $where[] = "c.status IN (1, 2, 6, 7)";
        }

        // Date filters
        if (!empty($filters['dateStart'])) {
            $where[] = "e.date >= ?";
            $params[] = $filters['dateStart'] . ' 00:00:00';
        }
        if (!empty($filters['dateEnd'])) {
            $where[] = "e.date <= ?";
            $params[] = $filters['dateEnd'] . ' 23:59:59';
        }

        // Patient filters
        if (!empty($filters['patientFirstName'])) {
            $where[] = "p.fname LIKE ?";
            $params[] = '%' . $filters['patientFirstName'] . '%';
        }
        if (!empty($filters['patientLastName'])) {
            $where[] = "p.lname LIKE ?";
            $params[] = '%' . $filters['patientLastName'] . '%';
        }

        // Payer filter
        if (!empty($filters['payerName'])) {
            $where[] = "ic.name LIKE ?";
            $params[] = '%' . $filters['payerName'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total matching encounters
        $countSql = "SELECT COUNT(*) AS cnt " .
            "FROM form_encounter e " .
            "JOIN patient_data p ON p.pid = e.pid " .
            "JOIN (SELECT patient_id, encounter_id, MAX(version) AS max_version FROM claims GROUP BY patient_id, encounter_id) cv " .
            "  ON cv.patient_id = e.pid AND cv.encounter_id = e.encounter " .
            "JOIN claims c ON c.patient_id = cv.patient_id AND c.encounter_id = cv.encounter_id AND c.version = cv.max_version " .
            "LEFT JOIN insurance_data id ON id.pid = e.pid AND id.type = 'primary' AND id.date <= e.date " .
            "LEFT JOIN insurance_companies ic ON ic.id = id.provider " .
            $whereClause;
        $totalRecords = (int) QueryUtils::fetchSingleValue($countSql, 'cnt', $params);

        // Get encounters with claim status
        $sql = "SELECT e.pid, e.encounter, e.date AS encounter_date, " .
            "p.fname, p.lname, p.DOB, " .
            "c.status AS claim_status, c.bill_process, c.bill_time, c.process_file, " .
            "c.payer_id, c.payer_type, " .
            "ic.name AS payer_name, ic.cms_id AS payer_number, " .
            "COALESCE((SELECT SUM(b.fee) FROM billing b WHERE b.pid = e.pid AND b.encounter = e.encounter AND b.activity = 1), 0) AS total_charges " .
            "FROM form_encounter e " .
            "JOIN patient_data p ON p.pid = e.pid " .
            "JOIN (SELECT patient_id, encounter_id, MAX(version) AS max_version FROM claims GROUP BY patient_id, encounter_id) cv " .
            "  ON cv.patient_id = e.pid AND cv.encounter_id = e.encounter " .
            "JOIN claims c ON c.patient_id = cv.patient_id AND c.encounter_id = cv.encounter_id AND c.version = cv.max_version " .
            "LEFT JOIN insurance_data id ON id.pid = e.pid AND id.type = 'primary' AND id.date <= e.date " .
            "LEFT JOIN insurance_companies ic ON ic.id = id.provider " .
            $whereClause . " " .
            "GROUP BY e.pid, e.encounter " .
            "ORDER BY e.date DESC " .
            "LIMIT {$pageSize} OFFSET {$offset}";
        $rows = QueryUtils::fetchRecords($sql, $params);

        if (empty($rows)) {
            return ['encounters' => [], 'totalRecords' => $totalRecords, 'claimRevLookupFailed' => false];
        }

        // Collect PCNs for ClaimRev batch lookup
        $pcnMap = []; // pcn => row index
        $encounters = [];
        foreach ($rows as $idx => $row) {
            $pcn = $row['pid'] . '-' . $row['encounter'];
            $oeStatus = (int) $row['claim_status'];

            $encounters[$idx] = [
                'pid' => (int) $row['pid'],
                'encounter' => (int) $row['encounter'],
                'pcn' => $pcn,
                'encounterDate' => substr((string) $row['encounter_date'], 0, 10),
                'patientName' => ($row['lname'] ?? '') . ', ' . ($row['fname'] ?? ''),
                'patientDob' => substr((string) ($row['DOB'] ?? ''), 0, 10),
                'payerName' => $row['payer_name'] ?? '',
                'payerNumber' => $row['payer_number'] ?? '',
                'totalCharges' => (float) $row['total_charges'],
                'billTime' => $row['bill_time'] ?? '',
                // OpenEMR status
                'oeStatus' => $oeStatus,
                'oeStatusLabel' => self::OE_STATUS_LABELS[$oeStatus] ?? 'Unknown (' . $oeStatus . ')',
                'oeProcessFile' => $row['process_file'] ?? '',
                // ClaimRev status (populated below)
                'crFound' => false,
                'crStatusName' => '',
                'crStatusId' => 0,
                'crPayerAcceptance' => '',
                'crPayerAcceptanceStatusId' => 0,
                'crEraClassification' => '',
                'crPayerPaidAmount' => 0.0,
                'crObjectId' => '',
                'crIsWorked' => false,
                // Discrepancy
                'discrepancy' => '',
                'discrepancyLevel' => '', // info, warning, danger
            ];

            $pcnMap[$pcn] = $idx;
        }

        // Batch lookup in ClaimRev
        $claimRevLookupFailed = false;
        try {
            $pcns = array_keys($pcnMap);
            $crResults = self::lookupClaimRev($pcns);

            foreach ($crResults as $crClaim) {
                $crPcn = $crClaim['patientControlNumber'] ?? '';
                if ($crPcn === '' || !isset($pcnMap[$crPcn])) {
                    continue;
                }

                $idx = $pcnMap[$crPcn];
                $encounters[$idx]['crFound'] = true;
                $encounters[$idx]['crStatusName'] = $crClaim['statusName'] ?? '';
                $encounters[$idx]['crStatusId'] = (int) ($crClaim['statusId'] ?? 0);
                $encounters[$idx]['crPayerAcceptance'] = $crClaim['payerAcceptanceStatusName'] ?? '';
                $encounters[$idx]['crPayerAcceptanceStatusId'] = (int) ($crClaim['payerAcceptanceStatusId'] ?? 0);
                $encounters[$idx]['crEraClassification'] = $crClaim['eraClassification'] ?? '';
                $encounters[$idx]['crPayerPaidAmount'] = (float) ($crClaim['payerPaidAmount'] ?? 0);
                $encounters[$idx]['crObjectId'] = $crClaim['objectId'] ?? '';
                $encounters[$idx]['crIsWorked'] = $crClaim['isWorked'] ?? false;

                // Sync to local tracking tables
                $enc = $encounters[$idx];
                ClaimTrackingService::upsertClaimRecord(
                    $enc['pid'],
                    $enc['encounter'],
                    1, // primary by default from reconciliation view
                    $crClaim
                );
            }
        } catch (ClaimRevException) {
            $claimRevLookupFailed = true;
        }

        // Compute discrepancies
        foreach ($encounters as &$enc) {
            $enc['discrepancy'] = self::computeDiscrepancy($enc);
        }
        unset($enc);

        // Filter to discrepancies only if requested
        if ($discrepancyOnly) {
            $encounters = array_values(array_filter($encounters, function ($enc) {
                return $enc['discrepancy'] !== '';
            }));
        }

        return [
            'encounters' => $encounters,
            'totalRecords' => $discrepancyOnly ? count($encounters) : $totalRecords,
            'claimRevLookupFailed' => $claimRevLookupFailed,
        ];
    }

    /**
     * Batch lookup claims in ClaimRev by patient control numbers.
     *
     * @param list<string> $pcns Patient control numbers (pid-encounter format)
     * @return list<array<string, mixed>> ClaimRev claim results
     */
    private static function lookupClaimRev(array $pcns): array
    {
        if (empty($pcns)) {
            return [];
        }

        $api = ClaimRevApi::makeFromGlobals();

        $model = new ClaimSearchModel();
        $model->patientControlNumbers = $pcns;
        $model->pagingSearch->pageSize = count($pcns);
        $model->pagingSearch->pageIndex = 0;

        $result = $api->searchClaims($model);
        return $result['results'] ?? [];
    }

    /**
     * Determine if there's a discrepancy between OE and ClaimRev status.
     *
     * @param array<string, mixed> $enc Merged encounter data
     * @return string Discrepancy description (empty if none)
     */
    private static function computeDiscrepancy(array $enc): string
    {
        $oeStatus = $enc['oeStatus'];
        $crFound = $enc['crFound'];
        $crStatusId = $enc['crStatusId'];
        $crPayerAcceptanceStatusId = $enc['crPayerAcceptanceStatusId'];
        $crEra = $enc['crEraClassification'];

        // Billed in OE but not found in ClaimRev
        if ($oeStatus === 2 && !$crFound) {
            $enc['discrepancyLevel'] = 'danger';
            return 'Billed in OpenEMR but not found in ClaimRev';
        }

        if (!$crFound) {
            return '';
        }

        // ClaimRev says rejected but OE still shows billed
        $crRejected = in_array($crStatusId, [10, 16, 17]) || $crPayerAcceptanceStatusId === 3;
        if ($crRejected && $oeStatus === 2) {
            $enc['discrepancyLevel'] = 'danger';
            return 'Rejected in ClaimRev but still Billed in OpenEMR';
        }

        // OE says denied but ClaimRev says accepted
        if ($oeStatus === 7 && $crPayerAcceptanceStatusId === 4) {
            $enc['discrepancyLevel'] = 'warning';
            return 'Denied in OpenEMR but Accepted in ClaimRev';
        }

        // Has ERA/payment but not posted to OE
        if (!empty($crEra) && stripos($crEra, 'paid') !== false) {
            // Check if payment has been posted
            $pid = $enc['pid'];
            $encounter = $enc['encounter'];
            $count = QueryUtils::fetchSingleValue(
                "SELECT COUNT(*) AS cnt FROM ar_activity WHERE pid = ? AND encounter = ? AND deleted IS NULL AND pay_amount > 0",
                'cnt',
                [$pid, $encounter]
            );
            if ((int) $count === 0) {
                $enc['discrepancyLevel'] = 'warning';
                return 'ERA shows paid but no payment posted in OpenEMR';
            }
        }

        // ERA denied but OE not marked denied
        if (!empty($crEra) && stripos($crEra, 'denied') !== false && $oeStatus !== 7) {
            $enc['discrepancyLevel'] = 'warning';
            return 'ERA shows denied but OpenEMR not marked as denied';
        }

        return '';
    }
}
