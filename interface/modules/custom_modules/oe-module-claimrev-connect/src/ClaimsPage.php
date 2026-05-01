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

use OpenEMR\Modules\ClaimRevConnector\Dto\ClaimSearchResult;

class ClaimsPage
{
    /**
     * @param array<string, mixed> $postData
     * @return array{results: list<ClaimSearchResult>, totalRecords: int}
     */
    public static function searchClaims(array $postData): array
    {
        $pageIndex = TypeCoerce::asInt($postData['pageIndex'] ?? 0);
        $model = self::buildSearchModel($postData, $pageIndex, 50);

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
        $model = self::buildSearchModel($postData, 0, 0);
        $api = ClaimRevApi::makeFromGlobals();
        return $api->searchClaimsCsv($model);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getClaimStatuses(): array
    {
        try {
            $api = ClaimRevApi::makeFromGlobals();
            $statuses = $api->getClaimStatuses();
            return is_array($statuses) ? array_values($statuses) : [];
        } catch (ClaimRevException) {
            return [];
        }
    }

    /**
     * Build a ClaimSearchModel from POST data, applying paging and sort.
     *
     * @param array<string, mixed> $postData
     */
    private static function buildSearchModel(array $postData, int $pageIndex, int $pageSize): ClaimSearchModel
    {
        $model = new ClaimSearchModel();
        $model->patientFirstName = TypeCoerce::asString($postData['patFirstName'] ?? '');
        $model->patientLastName = TypeCoerce::asString($postData['patLastName'] ?? '');
        $model->patientGender = TypeCoerce::asString($postData['patientGender'] ?? '');
        $model->patientBirthDate = self::nonEmptyString($postData['patientBirthDate'] ?? null);
        $model->receivedDateStart = self::nonEmptyString($postData['startDate'] ?? null);
        $model->receivedDateEnd = self::nonEmptyString($postData['endDate'] ?? null);
        $model->serviceDateStart = self::nonEmptyString($postData['serviceDateStart'] ?? null);
        $model->serviceDateEnd = self::nonEmptyString($postData['serviceDateEnd'] ?? null);
        $model->payerName = TypeCoerce::asString($postData['payerName'] ?? '');
        $model->payerNumber = TypeCoerce::asString($postData['payerNumber'] ?? '');
        $model->payerPaidAmtStart = self::nonEmptyFloat($postData['payerPaidAmtStart'] ?? null);
        $model->payerPaidAmtEnd = self::nonEmptyFloat($postData['payerPaidAmtEnd'] ?? null);
        $model->traceNumber = TypeCoerce::asString($postData['traceNumber'] ?? '');
        $model->patientControlNumber = TypeCoerce::asString($postData['patientControlNumber'] ?? '');
        $model->payerControlNumber = TypeCoerce::asString($postData['payerControlNumber'] ?? '');
        $model->billingProviderNpi = TypeCoerce::asString($postData['billingProviderNpi'] ?? '');
        $model->errorMessage = TypeCoerce::asString($postData['errorMessage'] ?? '');

        $statusId = TypeCoerce::asString($postData['statusId'] ?? '');
        if ($statusId !== '') {
            $model->statusIds = [(int) $statusId];
        }

        $model->pagingSearch->pageIndex = $pageIndex;
        if ($pageSize > 0) {
            $model->pagingSearch->pageSize = $pageSize;
        }

        $sortField = TypeCoerce::asString($postData['sortField'] ?? '');
        $sortDir = TypeCoerce::asString($postData['sortDirection'] ?? '');
        if ($sortField !== '') {
            $model->sorting = [[
                'fieldName' => $sortField,
                'sortDirection' => $sortDir === 'desc' ? -1 : 1,
                'priority' => 1,
            ]];
        }

        return $model;
    }

    private static function nonEmptyString(mixed $v): ?string
    {
        if (is_string($v) && $v !== '') {
            return $v;
        }
        return null;
    }

    private static function nonEmptyFloat(mixed $v): ?float
    {
        if (is_string($v) && $v !== '' && is_numeric($v)) {
            return (float) $v;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        return null;
    }
}
