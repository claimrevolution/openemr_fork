<?php

/**
 * Claims search page for ClaimRev integration
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector;

use OpenEMR\Modules\ClaimRevConnector\ClaimSearch;
use OpenEMR\Modules\ClaimRevConnector\ClaimSearchModel;
use OpenEMR\Modules\ClaimRevConnector\Dto\ClaimSearchResult;

class ClaimsPage
{
    /**
     * @param array<string, mixed> $postData
     * @return array{results: list<ClaimSearchResult>, totalRecords: int}
     */
    public static function searchClaims(array $postData): array
    {
        $pageIndex = isset($postData['pageIndex']) ? (int)$postData['pageIndex'] : 0;

        $model = new ClaimSearchModel();
        $model->patientFirstName = $postData['patFirstName'] ?? '';
        $model->patientLastName = $postData['patLastName'] ?? '';
        $model->patientGender = $postData['patientGender'] ?? '';
        $model->patientBirthDate = !empty($postData['patientBirthDate']) ? $postData['patientBirthDate'] : null;
        $model->receivedDateStart = !empty($postData['startDate']) ? $postData['startDate'] : null;
        $model->receivedDateEnd = !empty($postData['endDate']) ? $postData['endDate'] : null;
        $model->serviceDateStart = !empty($postData['serviceDateStart']) ? $postData['serviceDateStart'] : null;
        $model->serviceDateEnd = !empty($postData['serviceDateEnd']) ? $postData['serviceDateEnd'] : null;
        $model->payerName = $postData['payerName'] ?? '';
        $model->payerNumber = $postData['payerNumber'] ?? '';
        $model->payerPaidAmtStart = !empty($postData['payerPaidAmtStart']) ? (float)$postData['payerPaidAmtStart'] : null;
        $model->payerPaidAmtEnd = !empty($postData['payerPaidAmtEnd']) ? (float)$postData['payerPaidAmtEnd'] : null;
        $model->traceNumber = $postData['traceNumber'] ?? '';
        $model->patientControlNumber = $postData['patientControlNumber'] ?? '';
        $model->payerControlNumber = $postData['payerControlNumber'] ?? '';
        $model->billingProviderNpi = $postData['billingProviderNpi'] ?? '';
        $model->errorMessage = $postData['errorMessage'] ?? '';

        $statusId = $postData['statusId'] ?? '';
        if ($statusId !== '') {
            $model->statusIds = [(int)$statusId];
        }

        $model->pagingSearch->pageIndex = $pageIndex;
        $model->pagingSearch->pageSize = 50;

        $sortField = $postData['sortField'] ?? '';
        $sortDir = $postData['sortDirection'] ?? '';
        if ($sortField !== '') {
            $model->sorting = [[
                'fieldName' => $sortField,
                'sortDirection' => $sortDir === 'desc' ? -1 : 1,
                'priority' => 1,
            ]];
        }

        $raw = ClaimSearch::search($model);
        if ($raw === false) {
            return ['results' => [], 'totalRecords' => 0];
        }

        $rawResults = $raw['results'] ?? $raw;
        if (!is_array($rawResults)) {
            $rawResults = [];
        }

        $results = [];
        foreach ($rawResults as $item) {
            $results[] = ClaimSearchResult::fromApi($item);
        }

        $totalRaw = $raw['totalRecords'] ?? null;
        $totalRecords = is_int($totalRaw) ? $totalRaw : count($results);

        return ['results' => $results, 'totalRecords' => $totalRecords];
    }

    /**
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    public static function exportCsv(array $postData): array
    {
        $model = new ClaimSearchModel();
        $model->patientFirstName = $postData['patFirstName'] ?? '';
        $model->patientLastName = $postData['patLastName'] ?? '';
        $model->patientGender = $postData['patientGender'] ?? '';
        $model->patientBirthDate = !empty($postData['patientBirthDate']) ? $postData['patientBirthDate'] : null;
        $model->receivedDateStart = !empty($postData['startDate']) ? $postData['startDate'] : null;
        $model->receivedDateEnd = !empty($postData['endDate']) ? $postData['endDate'] : null;
        $model->serviceDateStart = !empty($postData['serviceDateStart']) ? $postData['serviceDateStart'] : null;
        $model->serviceDateEnd = !empty($postData['serviceDateEnd']) ? $postData['serviceDateEnd'] : null;
        $model->payerName = $postData['payerName'] ?? '';
        $model->payerNumber = $postData['payerNumber'] ?? '';
        $model->payerPaidAmtStart = !empty($postData['payerPaidAmtStart']) ? (float)$postData['payerPaidAmtStart'] : null;
        $model->payerPaidAmtEnd = !empty($postData['payerPaidAmtEnd']) ? (float)$postData['payerPaidAmtEnd'] : null;
        $model->traceNumber = $postData['traceNumber'] ?? '';
        $model->patientControlNumber = $postData['patientControlNumber'] ?? '';
        $model->payerControlNumber = $postData['payerControlNumber'] ?? '';
        $model->billingProviderNpi = $postData['billingProviderNpi'] ?? '';
        $model->errorMessage = $postData['errorMessage'] ?? '';

        $statusId = $postData['statusId'] ?? '';
        if ($statusId !== '') {
            $model->statusIds = [(int)$statusId];
        }

        $sortField = $postData['sortField'] ?? '';
        $sortDir = $postData['sortDirection'] ?? '';
        if ($sortField !== '') {
            $model->sorting = [[
                'fieldName' => $sortField,
                'sortDirection' => $sortDir === 'desc' ? -1 : 1,
                'priority' => 1,
            ]];
        }

        $api = ClaimRevApi::makeFromGlobals();
        return $api->searchClaimsCsv($model);
    }

    public static function getClaimStatuses()
    {
        try {
            $api = ClaimRevApi::makeFromGlobals();
            return $api->getClaimStatuses();
        } catch (ClaimRevException) {
            return [];
        }
    }
}
