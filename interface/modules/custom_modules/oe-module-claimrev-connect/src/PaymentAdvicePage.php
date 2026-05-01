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

declare(strict_types=1);

namespace OpenEMR\Modules\ClaimRevConnector;

use OpenEMR\Common\Database\QueryUtils;

/**
 * @phpstan-type PaymentInfoShape array{
 *     patientFirstName?: string,
 *     patientLastName?: string,
 *     patientControlNumber?: string,
 *     claimStatusCode?: string,
 *     totalClaimAmount?: float,
 *     claimPaymentAmount?: float,
 *     patientResponsibility?: float,
 *     isWorked?: bool
 * }
 * @phpstan-type CheckInfoShape array{
 *     checkNumber?: string,
 *     checkDate?: string,
 *     paymentMethodCode?: string,
 *     paymentAmount?: float
 * }
 * @phpstan-type PaymentAdviceShape array{
 *     paymentAdviceId?: string,
 *     receivedDate?: string,
 *     payerName?: string,
 *     payerNumber?: string,
 *     eraClassification?: string,
 *     paymentInfo?: PaymentInfoShape,
 *     checkInformation?: CheckInfoShape
 * }
 */
class PaymentAdvicePage
{
    /**
     * Build a search model from POST data and execute the search.
     *
     * @param array{receivedDateStart?: string, receivedDateEnd?: string, serviceDateStart?: string, serviceDateEnd?: string, patientFirstName?: string, patientLastName?: string, payerNumber?: string, patientControlNumber?: string, checkNumber?: string, isWorked?: string, sortField?: string, sortDirection?: string, pageIndex?: int} $postData
     * @return array{results: list<PaymentAdviceShape>, totalRecords: int}
     */
    public static function searchPaymentInfo(array $postData): array
    {
        $pageIndex = $postData['pageIndex'] ?? 0;

        $model = new PaymentAdviceSearchModel();
        $model->receivedDateStart = ($postData['receivedDateStart'] ?? '') !== '' ? $postData['receivedDateStart'] : null;
        $model->receivedDateEnd = ($postData['receivedDateEnd'] ?? '') !== '' ? $postData['receivedDateEnd'] : null;
        $model->serviceDateStart = ($postData['serviceDateStart'] ?? '') !== '' ? $postData['serviceDateStart'] : null;
        $model->serviceDateEnd = ($postData['serviceDateEnd'] ?? '') !== '' ? $postData['serviceDateEnd'] : null;
        $model->patientFirstName = ($postData['patientFirstName'] ?? '') !== '' ? $postData['patientFirstName'] : null;
        $model->patientLastName = ($postData['patientLastName'] ?? '') !== '' ? $postData['patientLastName'] : null;
        $model->payerNumber = ($postData['payerNumber'] ?? '') !== '' ? $postData['payerNumber'] : null;
        $model->patientControlNumber = ($postData['patientControlNumber'] ?? '') !== '' ? $postData['patientControlNumber'] : null;
        $model->checkNumber = ($postData['checkNumber'] ?? '') !== '' ? $postData['checkNumber'] : null;

        $isWorked = $postData['isWorked'] ?? '';
        if ($isWorked !== '') {
            $model->isWorked = $isWorked === '1';
        }

        $model->pagingSearch->pageIndex = $pageIndex;
        $model->pagingSearch->pageSize = 50;
        $model->pagingSearch->sortField = $postData['sortField'] ?? '';
        $model->pagingSearch->sortDirection = $postData['sortDirection'] ?? '';

        $api = ClaimRevApi::makeFromGlobals();
        $raw = $api->searchPaymentInfo($model);

        $results = [];
        $rawResults = $raw['results'] ?? null;
        if (is_array($rawResults)) {
            foreach ($rawResults as $entry) {
                if (is_array($entry)) {
                    /** @var PaymentAdviceShape $entry */
                    $results[] = $entry;
                }
            }
        }

        return [
            'results' => $results,
            'totalRecords' => TypeCoerce::asInt($raw['totalRecords'] ?? 0),
        ];
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

        $row = QueryUtils::querySingleRow(
            "SELECT status, bill_process FROM claims WHERE patient_id = ? AND encounter_id = ? ORDER BY version DESC LIMIT 1",
            [$pid, $encounter]
        );

        if ($row === [] || $row === false) {
            return [
                'pid' => $pid,
                'encounter' => $encounter,
                'status' => -1,
                'status_label' => 'Not Found',
            ];
        }

        $status = TypeCoerce::asInt($row['status'] ?? 0);
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
