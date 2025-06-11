<?php

$passePlat->registerEventListeners([
    new \PassePlat\App\EventListenerDefinition(
        \PassePlat\Core\StreamProcessor\SchemeProcessor\Event\ProcessSchemeEvent::EVENT_NAME,
        \PassePlat\Ftp\StreamProcessor\SchemeProcessor\FtpSchemeProcessor::class
    ),
]);
