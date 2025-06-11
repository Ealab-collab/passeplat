<?php

namespace PassePlat\Core\Config\Event;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Event\EventInterface;

/**
 * Used when configuration is initialized from file system.
 */
class GetEnabledConfigItemEvent extends ComponentBasedObject implements EventInterface
{
    public const EVENT_NAME = 'PASSEPLAT_CORE__CONFIG_ITEM__GET_ENABLED';

    /**
     * Class names of enabled ConfigItems.
     *
     * @var string[]
     */
    private $enabledConfigItemClassNames = [];

    public function getName(): string
    {
        return static::EVENT_NAME;
    }

    /**
     * Class names of enabled ConfigItems.
     *
     * @return string[]
     *   Array of class names (values returned by *::class).
     */
    public function getEnabledConfigItemClassNames(): array
    {
        return $this->enabledConfigItemClassNames;
    }

    /**
     * Adds the ConfigItem class name to enabled config items.
     *
     * @param string $configItemClass
     *   The class name to instantiate.
     */
    public function registerConfigItem(string $configItemClass): void
    {
        $this->enabledConfigItemClassNames[$configItemClass] = $configItemClass;
    }
}
