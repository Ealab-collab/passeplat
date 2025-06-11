<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\DashboardHandler;

require_once 'common.php';

/**
 * An endpoint that provides the dashboard of a webservice.
 */
try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        DashboardHandler::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
