<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent;

use Dakwamine\Component\ComponentBasedObject;

/**
 * Base class for analyzable content components.
 *
 * AnalyzableContentComponent objects are expected to return indexable values.
 */
abstract class AnalyzableContentComponentBase extends ComponentBasedObject
{
    /**
     * Gets the component data to log.
     *
     * @return array
     *   Array of keyed data to log.
     */
    abstract public function getComponentDataToLog(): array;

    /**
     * Gets the sub components data to log.
     *
     * @return array
     *   Array of keyed data to log.
     */
    public function getSubComponentsDataToLog(): array
    {
        $data = [];

        // Perform recursive calls on sub components.
        /** @var AnalyzableContentComponentBase[] $subComponents */
        $subComponents = $this->getComponentsByClassName(AnalyzableContentComponentBase::class);

        foreach ($subComponents as $subComponent) {
            $data = array_merge($data, $subComponent->getComponentDataToLog());
        }

        return $data;
    }
}
