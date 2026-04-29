<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
    'message' => '#^Path in require_once\\(\\) "/var/www/localhost/htdocs/openemr/interface/globals\\.php" is not a file or it does not exist\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/tests/test_compat_8x.php',
];
$ignoreErrors[] = [
    'message' => '#^Path in require_once\\(\\) "/var/www/localhost/htdocs/openemr/interface/globals\\.php" is not a file or it does not exist\\.$#',
    'count' => 1,
    'path' => __DIR__ . '/../../interface/modules/custom_modules/oe-module-claimrev-connect/tests/test_compat_nuclear.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
