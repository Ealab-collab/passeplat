<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\LogsHandler;

require_once 'common.php';

/**
 * An endpoint to retrieve web service logs from the Elasticsearch index `analyzable_content`.
 */
try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        LogsHandler::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
