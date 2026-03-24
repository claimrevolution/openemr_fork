<?php

/**
 *
 * @package OpenEMR
 * @link    https://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace OpenEMR\Modules\ClaimRevConnector;

class ClaimSearchModel
{
    public $patientFirstName = "";
    public $patientLastName = "";
    public $patientGender = "";
    public $patientBirthDate;
    public $receivedDateStart;
    public $receivedDateEnd;
    public $serviceDateStart;
    public $serviceDateEnd;
    public $payerName = "";
    public $payerNumber = "";
    public $payerPaidAmtStart;
    public $payerPaidAmtEnd;
    public $traceNumber = "";
    public $traceNumbers = [];
    public $patientControlNumber = "";
    public $patientControlNumbers = [];
    public $payerControlNumber = "";
    public $payerControlNumbers = [];
    public $billingProviderNpi = "";
    public $errorMessage = "";
    public $statusIds = [];
    public $accountNumbers = [];
    public $claimTypeIds = [];
    public $excludeStatusIds = [];
    public $paymentAdviceStatusIds = [];
    public $sorting = [];
    public $tagIds = [];
    public $excludeTagIds = [];
    public $eraClassifications = [];
    public $pagingSearch;

    public function __construct()
    {
        $this->pagingSearch = new PagingSearchModel();
    }
}
