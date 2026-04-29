<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 2,
    'path' => __DIR__ . '/../../controllers/C_Prescription.class.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/forms/procedure_order/procedure_order_save_functions.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 2,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/public/claims.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/public/patient_balance_api.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 3,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/public/payment_advice.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 2,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/AgingReportService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 3,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/ClaimTrackingService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 4,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/ClaimsPage.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 7,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/DashboardService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 4,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/DenialAnalyticsService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 11,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/PatientBalanceService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 3,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/PaymentAdviceMockService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 9,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/PaymentAdvicePostingService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 2,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/ReconciliationService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 5,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/src/RecoupmentReportService.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-dorn/src/DornGenHl7Order.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 14,
    'path' => __DIR__ . '/../../interface/modules/zend_modules/module/Carecoordination/src/Carecoordination/Model/EncounterccdadispatchTable.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/patient_file/front_payment.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 2,
    'path' => __DIR__ . '/../../interface/procedure_tools/labcorp/gen_hl7_order.inc.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/reports/insurance_allocation_report.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 2,
    'path' => __DIR__ . '/../../library/edihistory/edih_835_html.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 5,
    'path' => __DIR__ . '/../../portal/portal_payment.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../src/Easipro/Easipro.php',
];
$ignoreErrors[] = [
    'message' => '#^Cannot cast mixed to float\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../src/Services/Cda/CdaTemplateParse.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
