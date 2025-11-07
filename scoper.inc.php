<?php
// scoper.inc.php

function getWpExcludedSymbols(string $fileName): array
{
    //insert username below in place of jacob
    $username = 'jacob';
    $filePath = '/home/'.$username.'/.config/composer/vendor/sniccowp/php-scoper-wordpress-excludes/generated/'.$fileName;

    return json_decode(
        file_get_contents($filePath),
        true,
    );
}

$wpConstants = getWpExcludedSymbols('exclude-wordpress-constants.json');
$wpClasses = getWpExcludedSymbols('exclude-wordpress-classes.json');
$wpFunctions = getWpExcludedSymbols('exclude-wordpress-functions.json');


return [
  'exclude-constants' => $wpConstants,
  'exclude-classes' => $wpClasses,
  'exclude-functions' => $wpFunctions,
  // ...
];