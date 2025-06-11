<?php

$passePlat->registerEventListeners([
    new \PassePlat\App\EventListenerDefinition(
        \PassePlat\Core\Config\Event\GetEnabledConfigItemEvent::EVENT_NAME,
        \PassePlat\Logger\Elastic\StreamProcessor\SchemeProcessor\Task\ElasticsearchLogger_0::class
    ),
]);
