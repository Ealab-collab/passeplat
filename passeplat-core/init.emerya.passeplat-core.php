<?php

use PassePlat\App\EventListenerDefinition;
use PassePlat\Core\Config\Configuration;
use PassePlat\Core\Config\Event\GetEnabledConfigItemEvent;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Event\ProcessSchemeEvent;
use PassePlat\Core\StreamProcessor\SchemeProcessor\HttpSchemeProcessor;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;


$basePath = dirname(__FILE__) . '/src/StreamProcessor/SchemeProcessor/Task';
$rdi = new RecursiveDirectoryIterator($basePath);
$rii = new RecursiveIteratorIterator($rdi);
$initFiles = new RegexIterator($rii, '/^.+_\d+\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($initFiles as $initFile => $pattern) {
    require_once $initFile;
    $className = basename($initFile, '.php');
    // Core tasks can be organized into folders in the future without any issues.
    $parentNamespaceDirectory = str_replace('/', '\\', substr(dirname($initFile), strlen($basePath) + 1));
    $qualifiedClassName = 'PassePlat\Core\StreamProcessor\SchemeProcessor\Task\\'
        . ($parentNamespaceDirectory ? $parentNamespaceDirectory . '\\' : '') . $className;

    if (class_exists($qualifiedClassName) && is_subclass_of($qualifiedClassName, TaskHandlerBase::class)) {
        try {
            /** var TaskHandlerBase $className */
            $qualifiedClassName::register();
        } catch (\Exception $e) {
            //TODO
        }
    }
}
