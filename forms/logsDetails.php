<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\LogsDetailsHandler;

require_once 'common.php';

/**
 * Endpoint to retrieve headers and body details of the request and response
 * for an element fetched by the `logs.php` endpoint.
 */
try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        LogsDetailsHandler::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
