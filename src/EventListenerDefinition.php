<?php

namespace PassePlat\App;

/**
 * Just a simple container for event listener definition.
 */
class EventListenerDefinition
{
    public $eventName;
    public $listenerClassName;
    public $priority;

    /**
     * EventListenerDefinition constructor.
     *
     * @param string $eventName
     *   Event name.
     * @param string $listenerClassName
     *   Listener full class name.
     * @param int $priority
     *   Optional priority.
     */
    public function __construct($eventName, $listenerClassName, $priority = 0)
    {
        $this->eventName = $eventName;
        $this->listenerClassName = $listenerClassName;
        $this->priority = $priority;
    }
}
