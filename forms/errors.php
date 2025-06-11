<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\ErrorsHandler;

require_once 'common.php';

try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        ErrorsHandler::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
