<?php

namespace PassePlat\Core\Config\ConfigItem;

use Dakwamine\Component\ComponentBasedObject;

/**
 * Base class for config items.
 *
 * Config items are unique and live objects holding mutable and persisting configuration across the app lifecycle.
 */
abstract class ConfigItem extends ComponentBasedObject
{
    /**
     * Values of this config item.
     *
     * @var array
     *   Values as loaded by the config manager.
     */
    protected $config;

    /**
     * Gets all of the values of the config item.
     *
     * @return array
     *   Array of config values.
     */
    public function getValues(): array
    {
        return $this->config;
    }

    /**
     * Gets the config ID. Must be unique.
     *
     * @return string
     *   The config ID, e.g. "trusted-host-patterns.passeplat-core".
     */
    abstract public function getConfigId(): string;

    /**
     * Sets all of the values on the config item.
     *
     * Values filtering is implementation dependant; each ConfigItem is responsible of the values it handles.
     *
     * @param array $values
     *   Array of config values.
     */
    public function setValues(array $values): void
    {
        $this->config = $values;
    }
}
