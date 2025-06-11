<?php

namespace PassePlat\Core\AnalyzableContent;

//TODO: content type, headers, created, destination url, response time, passeplat uid, passeplat wsid
use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\AnalyzableContentComponentBase;

/**
 * Analyzable content representation.
 */
class AnalyzableContent extends ComponentBasedObject
{
    /**
     * Contains the execution info for this AnalyzableContent.
     *
     * It may be used to store and share information between components.
     *
     * @var array
     */
    protected $executionInfo = [];

    /**
     * Gets the data to log.
     *
     * @return array
     *   An array with the "field" name as the unique index. E.g.: ['response_wait' => '153.000154'].
     */
    public function getDataToLog()
    {
        $data = [];

        // Merge data from sub-components.
        /** @var AnalyzableContentComponentBase[] $components */
        $components = $this->getComponentsByClassName(AnalyzableContentComponentBase::class);

        foreach ($components as $component) {
            $data = array_merge($data, $component->getComponentDataToLog());
        }

        return $data;
    }

    /**
     * Gets the execution info for this AnalyzableContent.
     *
     * @param string $key
     *   The key.
     * @param mixed $default
     *   The default value. Defaults to null.
     *
     * @return mixed|null
     *   The value or $default if not found.
     */
    public function getExecutionInfo(string $key, $default = null)
    {
        if (!isset($this->executionInfo[$key])) {
            return $default;
        }

        return $this->executionInfo[$key];
    }

    /**
     * Sets the execution info for this AnalyzableContent.
     *
     * @param string $key
     *   The key.
     * @param mixed $value
     *   The value.
     */
    public function setExecutionInfo(string $key, $value) : void
    {
        $this->executionInfo[$key] = $value;
    }
}
