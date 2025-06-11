<?php

namespace PassePlat\Core\Event;

use Dakwamine\Component\Event\EventInterface;

/**
 * Event on destination failure.
 */
class DestinationReachFailureEvent implements EventInterface
{
    const EVENT_NAME = 'PASSEPLAT_CORE__DESTINATION_REACH_FAILURE';

    public function getName(): string
    {
        return static::EVENT_NAME;
    }
}
