<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\DumpHandler;

require_once 'common.php';

try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        DumpHandler::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
