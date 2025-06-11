<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\NavbarHandler;

require_once 'common.php';

/**
 * An endpoint that provides the navbar.
 */
try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        NavbarHandler::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
