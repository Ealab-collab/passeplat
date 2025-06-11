<?php

namespace PassePlat\Core\AnalyzableContent\ExecutionTrace;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;

/**
 * Trait for easy use of the execution trace system.
 */
trait ExecutionTraceProviderTrait
{
    /**
     * Sets the execution trace details.
     *
     * @param mixed $details
     *   Any serializable value. Please insert light values only.
     */
    protected function addExecutionTraceDetails($details): void
    {
        if (!$this instanceof ComponentBasedObject) {
            // Dev note: DO NOT REMOVE THIS CHECK.
            // PhpStorm may ask to remove it, but don't do it; it's a bug.
            return;
        }

        try {
            /** @var ExecutionTraceProvider $provider */
            $provider = $this->addComponentByClassName(ExecutionTraceProvider::class);
        } catch (UnmetDependencyException $e) {
            // Just ignore this.
            return;
        }

        // The details will be collected by the ExecutionTraceCollector.
        $provider->setTraceSource($this);
        $provider->setDetails($details);
    }
}
