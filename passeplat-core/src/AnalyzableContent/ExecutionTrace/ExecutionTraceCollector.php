<?php

namespace PassePlat\Core\AnalyzableContent\ExecutionTrace;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Tool\DateTime;

/**
 * Collects all execution trace providers contained by the collection base component.
 */
class ExecutionTraceCollector extends ComponentBasedObject
{
    protected ComponentBasedObject $collectionBaseComponent;

    /**
     * Date time tool.
     *
     * @var DateTime|null
     */
    private ?DateTime $dateTime;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(DateTime::class, $this->dateTime);
        return $definitions;
    }

    /**
     * Collects all execution trace info contained by the collection base component.
     *
     * @return array
     *   The execution trace info ready for serialization.
     */
    public function collectTraceInfo(): array
    {
        $executionTraces = $this->collectProviders();
        $traceInfo = [];

        foreach ($executionTraces as $executionTrace) {
            $traceInfo[] = $executionTrace->provideTraceInfo();
        }

        return $traceInfo;
    }

    /**
     * Collects all execution trace providers contained by the collection base component.
     *
     * @return ExecutionTraceProvider[]
     *   The execution trace providers.
     */
    private function collectProviders(): array
    {
        /** @var ExecutionTraceProvider[] $executionTraces */
        $executionTraces = [];
        $this->recursivelyCollectProviders($executionTraces);

        // Sort by microtime.
        uasort($executionTraces, function (ExecutionTraceProvider $a, ExecutionTraceProvider $b) {
            try {
                // Convert the microtime float values to BigDecimal to avoid float precision issues.
                return $this->dateTime->microtimeToDecimal($a->getMicrotime())
                    ->compareTo($this->dateTime->microtimeToDecimal($b->getMicrotime()));
            } catch (\Exception $exception) {
                return $a->getMicrotime() <=> $b->getMicrotime();
            }
        });

        return $executionTraces;
    }

    /**
     * Recursively collects all execution trace providers contained by the given component.
     *
     * @param array $executionTraces
     *   The array to fill with execution trace providers.
     * @param ComponentBasedObject|null $object
     *   The component to analyze. Defaults to the collection base component if set.
     */
    private function recursivelyCollectProviders(array &$executionTraces, ComponentBasedObject $object = null): void
    {
        $workingObject = $object ?? $this->collectionBaseComponent;

        if (!$workingObject instanceof ComponentBasedObject) {
            // Safety check.
            return;
        }

        $subComponents = $workingObject->getComponents();

        foreach ($subComponents as $subComponent) {
            if (!$subComponent instanceof ComponentBasedObject) {
                // Safety check.
                continue;
            }

            $this->recursivelyCollectProviders($executionTraces, $subComponent);
        }

        if ($object instanceof ExecutionTraceProvider) {
            $executionTraces[] = $object;
        }
    }

    /**
     * Sets the collection base component.
     *
     * This component will be used as base for the collection.
     * The main AnalyzableContent object is generally suitable for this.
     *
     * @param ComponentBasedObject $objectBase
     *   The component to use as base for the collection.
     */
    public function setCollectionBaseComponent(ComponentBasedObject $objectBase): void
    {
        $this->collectionBaseComponent = $objectBase;
    }
}
