<?php

use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;

//TODO
// Create directory Task into SchemeProcessor.
$basePath = dirname(__FILE__) . '/src/StreamProcessor/SchemeProcessor';
$rdi = new RecursiveDirectoryIterator($basePath);
$rii = new RecursiveIteratorIterator($rdi);
$initFiles = new RegexIterator($rii, '/^.+_\d+\.php$/i', RecursiveRegexIterator::GET_MATCH);

foreach ($initFiles as $initFile => $pattern) {
    require_once $initFile;
    $className = basename($initFile, '.php');
    $parentNamespaceDirectory = str_replace('/', '\\', substr(dirname($initFile), strlen($basePath) + 1));
    $qualifiedClassName = 'PassePlat\Tasks\StreamProcessor\SchemeProcessor\\'.
        $parentNamespaceDirectory . '\\' . $className;
    if (class_exists($qualifiedClassName) && is_subclass_of($qualifiedClassName, TaskHandlerBase::class)) {
        try {
            /** var TaskHandlerBase $className */
            $qualifiedClassName::register();
        } catch (\Exception $e) {
            //TODO
        }
    }
}
