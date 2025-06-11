<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\WebServicesList;

require_once 'common.php';

/**
 * An endpoint that provides a list of webservices.
 */
try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        WebServicesList::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
