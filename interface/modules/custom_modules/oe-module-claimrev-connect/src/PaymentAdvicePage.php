<?php

/**
 * Payment Advice page controller.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2026 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector;

class PaymentAdvicePage
{
    /**
     * Build a search model from POST data and execute the search.
     *
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    public static function searchPaymentInfo(array $postData): array
    {
        $pageIndex = isset($postData['pageIndex']) ? (int) $postData['pageIndex'] : 0;

        $model = new PaymentAdviceSearchModel();
        $model->receivedDateStart = !empty($postData['receivedDateStart']) ? $postData['receivedDateStart'] : null;
        $model->receivedDateEnd = !empty($postData['receivedDateEnd']) ? $postData['receivedDateEnd'] : null;
        $model->serviceDateStart = !empty($postData['serviceDateStart']) ? $postData['serviceDateStart'] : null;
        $model->serviceDateEnd = !empty($postData['serviceDateEnd']) ? $postData['serviceDateEnd'] : null;
        $model->patientFirstName = !empty($postData['patientFirstName']) ? $postData['patientFirstName'] : null;
        $model->patientLastName = !empty($postData['patientLastName']) ? $postData['patientLastName'] : null;
        $model->payerNumber = !empty($postData['payerNumber']) ? $postData['payerNumber'] : null;
        $model->patientControlNumber = !empty($postData['patientControlNumber']) ? $postData['patientControlNumber'] : null;
        $model->checkNumber = !empty($postData['checkNumber']) ? $postData['checkNumber'] : null;

        $isWorked = $postData['isWorked'] ?? '';
        if ($isWorked !== '') {
            $model->isWorked = $isWorked === '1';
        }

        $model->pagingSearch->pageIndex = $pageIndex;
        $model->pagingSearch->pageSize = 50;
        $model->pagingSearch->sortField = $postData['sortField'] ?? '';
        $model->pagingSearch->sortDirection = $postData['sortDirection'] ?? '';

        $api = ClaimRevApi::makeFromGlobals();
        return $api->searchPaymentInfo($model);
    }

    /**
     * Look up OpenEMR claim status for a patient control number.
     *
     * The patient control number from ClaimRev is formatted as "pid-encounter".
     *
     * @return array{pid: int, encounter: int, status: int, status_label: string}|null
     */
    public static function getOpenEmrClaimStatus(string $patientControlNumber): ?array
    {
        if ($patientControlNumber === '') {
            return null;
        }

        $parts = preg_split('/[\s\-]/', $patientControlNumber);
        if (!is_array($parts) || count($parts) < 2) {
            return null;
        }

        $pid = (int) $parts[0];
        $encounter = (int) $parts[1];

        if ($pid <= 0 || $encounter <= 0) {
            return null;
        }

        $row = sqlQuery(
            "SELECT status, bill_process FROM claims WHERE patient_id = ? AND encounter_id = ? ORDER BY version DESC LIMIT 1",
            [$pid, $encounter]
        );

        if (empty($row)) {
            return [
                'pid' => $pid,
                'encounter' => $encounter,
                'status' => -1,
                'status_label' => 'Not Found',
            ];
        }

        $status = (int) $row['status'];
        $labels = [
            0 => 'Not Billed',
            1 => 'Unbilled',
            2 => 'Billed',
            3 => 'Processed',
            6 => 'Crossover',
            7 => 'Denied',
        ];

        return [
            'pid' => $pid,
            'encounter' => $encounter,
            'status' => $status,
            'status_label' => $labels[$status] ?? 'Unknown (' . $status . ')',
        ];
    }
}
