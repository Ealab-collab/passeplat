<?php

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Handler\WebServiceEdit;

require_once 'common.php';

/**
 * An endpoint for editing a webservice.
 */
try {
    $handler = ComponentBasedObject::getRootComponentByClassName(
        WebServiceEdit::class,
        true
    );
    $handler->handleRequest();
} catch (\Throwable $e) {
    global $handException;
    $handException($e);
}
