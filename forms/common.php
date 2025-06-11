<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\App\Exception\ErrorCode;
use PassePlat\App\Exception\ErrorString;
use PassePlat\App\PassePlat;
use PassePlat\Forms\Vue\Response;
use PassePlat\Core\Exception\ConfigException;
use PassePlat\Forms\Exception\BadRequestException;

require_once '../vendor/autoload.php';

/** @var PassePlat $passePlat */
$passePlat = ComponentBasedObject::getRootComponentByClassName(PassePlat::class, true);

// Init scripts.
require_once __DIR__ . '/../src/Task/init.php';

try {
    $passePlat->loadConfiguration();
} catch (ConfigException $e) {
    echo ErrorString::buildUnknownError([ErrorCode::CONFIGURATION_ERROR]);
}

$handleException = function ($exception) {
    if ($exception instanceof BadRequestException) {
        Response::sendBadRequest($exception->getMessage());
    } else {
        Response::sendInternalServerError();
    }
};
