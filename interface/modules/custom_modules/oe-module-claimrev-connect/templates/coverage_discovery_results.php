<?php

/**
 *
 * @package OpenEMR
 * @link    http://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

/** @var iterable<\stdClass>|null $coverageResults set by the caller (individual->coverageDiscovery) */

if ($coverageResults === null || (is_array($coverageResults) && count($coverageResults) === 0)) {
    echo xlt("No coverage discovered");
    return;
}
?>
<div class="card mb-2">
    <div class="card-header"><?php echo xlt("Coverage Discovery Results"); ?></div>
    <div class="card-body">
        <?php
        $cdIndex = 0;
        foreach ($coverageResults as $coverage) {
            $cdIndex++;
            $statusVal = property_exists($coverage, 'status') && is_string($coverage->status) ? $coverage->status : '';
            $payerInfo = property_exists($coverage, 'payerInfo') && is_object($coverage->payerInfo) ? $coverage->payerInfo : null;
            $subscriberId = property_exists($coverage, 'subscriberId') && is_string($coverage->subscriberId) ? $coverage->subscriberId : '';
            $groupNumber = property_exists($coverage, 'groupNumber') && is_string($coverage->groupNumber) ? $coverage->groupNumber : '';
            $groupName = property_exists($coverage, 'groupName') && is_string($coverage->groupName) ? $coverage->groupName : '';
            $insuranceType = property_exists($coverage, 'insuranceType') && is_string($coverage->insuranceType) ? $coverage->insuranceType : '';
            $insurancePlan = property_exists($coverage, 'insurancePlan') && is_string($coverage->insurancePlan) ? $coverage->insurancePlan : '';
            $policyDate = property_exists($coverage, 'policyDate') && is_object($coverage->policyDate) ? $coverage->policyDate : null;
            $confidenceScore = property_exists($coverage, 'confidenceScore') ? $coverage->confidenceScore : null;
            $confidenceScoreReason = property_exists($coverage, 'confidenceScoreReason') && is_string($coverage->confidenceScoreReason) ? $coverage->confidenceScoreReason : '';
            $mbi = property_exists($coverage, 'mbi') && is_string($coverage->mbi) ? $coverage->mbi : '';
            ?>
            <div class="<?php echo $cdIndex > 1 ? 'border-top pt-3 mt-3' : ''; ?>">
                <?php if ($statusVal !== '') {
                    $statusStyle = ($statusVal === "Active Coverage") ? "color:green" : "color:red";
                    ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Coverage Status"); ?>:</div>
                        <div class="col" style="<?php echo attr($statusStyle); ?>"><?php echo text($statusVal); ?></div>
                    </div>
                <?php } ?>
                <?php if ($payerInfo !== null) {
                    $payerName = property_exists($payerInfo, 'payerName') && is_string($payerInfo->payerName) ? $payerInfo->payerName : '';
                    $payerCode = property_exists($payerInfo, 'payerCode') && is_string($payerInfo->payerCode) ? $payerInfo->payerCode : '';
                    $payerAddress1 = property_exists($payerInfo, 'payerAddress1') && is_string($payerInfo->payerAddress1) ? $payerInfo->payerAddress1 : '';
                    $payerCity = property_exists($payerInfo, 'payerCity') && is_string($payerInfo->payerCity) ? $payerInfo->payerCity : '';
                    $payerState = property_exists($payerInfo, 'payerState') && is_string($payerInfo->payerState) ? $payerInfo->payerState : '';
                    $payerZip = property_exists($payerInfo, 'payerZip') && is_string($payerInfo->payerZip) ? $payerInfo->payerZip : '';
                    ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Payer"); ?>:</div>
                        <div class="col">
                            <?php echo text($payerName); ?>
                            <?php if ($payerCode !== '') { ?>
                                <small class="text-muted">(#<?php echo text($payerCode); ?>)</small>
                            <?php } ?>
                        </div>
                    </div>
                    <?php if ($payerAddress1 !== '') { ?>
                        <div class="row mb-1">
                            <div class="col-3 font-weight-bold"><?php echo xlt("Payer Address"); ?>:</div>
                            <div class="col">
                                <?php echo text($payerAddress1); ?>
                                <?php if ($payerCity !== '') { ?>
                                    , <?php echo text($payerCity); ?>, <?php echo text($payerState); ?> <?php echo text($payerZip); ?>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
                <?php if ($subscriberId !== '') { ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Subscriber ID"); ?>:</div>
                        <div class="col"><?php echo text($subscriberId); ?></div>
                    </div>
                <?php } ?>
                <?php if ($groupNumber !== '') { ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Group #"); ?>:</div>
                        <div class="col"><?php echo text($groupNumber); ?></div>
                    </div>
                <?php } ?>
                <?php if ($groupName !== '') { ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Group Name"); ?>:</div>
                        <div class="col"><?php echo text($groupName); ?></div>
                    </div>
                <?php } ?>
                <?php if ($insuranceType !== '') { ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Insurance Type"); ?>:</div>
                        <div class="col"><?php echo text($insuranceType); ?></div>
                    </div>
                <?php } ?>
                <?php if ($insurancePlan !== '') { ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Plan"); ?>:</div>
                        <div class="col"><?php echo text($insurancePlan); ?></div>
                    </div>
                <?php } ?>
                <?php if ($policyDate !== null) {
                    $startDate = property_exists($policyDate, 'startDate') && is_string($policyDate->startDate) ? $policyDate->startDate : '';
                    $endDate = property_exists($policyDate, 'endDate') && is_string($policyDate->endDate) ? $policyDate->endDate : '';
                    ?>
                    <?php if ($startDate !== '') { ?>
                        <div class="row mb-1">
                            <div class="col-3 font-weight-bold"><?php echo xlt("Policy Start"); ?>:</div>
                            <div class="col"><?php echo text(substr($startDate, 0, 10)); ?></div>
                        </div>
                    <?php } ?>
                    <?php if ($endDate !== '') { ?>
                        <div class="row mb-1">
                            <div class="col-3 font-weight-bold"><?php echo xlt("Policy End"); ?>:</div>
                            <div class="col"><?php echo text(substr($endDate, 0, 10)); ?></div>
                        </div>
                    <?php } ?>
                <?php } ?>
                <?php if ($confidenceScore !== null && $confidenceScore !== '' && $confidenceScore !== 0) { ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("Confidence"); ?>:</div>
                        <div class="col"><?php echo text((string) $confidenceScore); ?>
                            <?php if ($confidenceScoreReason !== '') { ?>
                                <small class="text-muted">(<?php echo text($confidenceScoreReason); ?>)</small>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($mbi !== '') { ?>
                    <div class="row mb-1">
                        <div class="col-3 font-weight-bold"><?php echo xlt("MBI"); ?>:</div>
                        <div class="col"><?php echo text($mbi); ?></div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
