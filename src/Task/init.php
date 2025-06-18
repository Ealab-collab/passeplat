<?php

/*
require_once __DIR__ . '/../../vendor/emerya/passeplat-tasks/init.emerya.passeplat-tasks.php';
require_once __DIR__ . '/../../vendor/emerya/passeplat-core/init.emerya.passeplat-core.php';
require_once __DIR__ . '/../../vendor/emerya/passeplat-logger-elastic/init.emerya.passeplat-logger-elastic.php';
require_once __DIR__ . '/../../vendor/emerya/passeplat-openapi/init.emerya.passeplat-openapi.php';
*/
/*
The following code is deprecated and will be removed in future versions.
However, note that this deprecation does not apply to the required parts above.
*/

// Load files from the project's packages.
// Based on https://stackoverflow.com/a/29109311.
$rdi = new RecursiveDirectoryIterator(dirname(__FILE__));
$rii = new RecursiveIteratorIterator($rdi);
$initFiles = new RegexIterator($rii, '/init\..+\.php$/', RecursiveRegexIterator::GET_MATCH);
foreach ($initFiles as $initFile => $pattern) {
    require_once $initFile;
}
