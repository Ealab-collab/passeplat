<?php

namespace PassePlat\Core\Config;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Event\EventDispatcher;
use Dakwamine\Component\Event\EventInterface;
use Dakwamine\Component\Event\EventListenerInterface;
use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Config\ConfigItem\ConfigItem;
use PassePlat\Core\Config\ConfigItem\TrustedHostPatternConfigItem;
use PassePlat\Core\Config\ConfigItem\UserConfigItem;
use PassePlat\Core\Config\ConfigItem\WebServiceConfigItem;
use PassePlat\Core\Config\Event\GetEnabledConfigItemEvent;
use PassePlat\Core\Exception\ConfigException;

/**
 * Main configuration loader for passeplat.
 */
class Configuration extends ComponentBasedObject implements EventListenerInterface
{
    /**
     * @var ConfigLoader
     */
    private $configLoader;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * Returns the enabled config values.
     *
     * @param string $configId
     *   ID of the config item.
     *
     * @return array|null
     *   Array of values as loaded by this configuration manager. May be null if not found.
     *
     * @throws ConfigException
     */
    public function getConfigValues(string $configId): ?array
    {
        try {
            // Get enabled configuration items.
            $enabledConfigItems = $this->getEnabledConfigItems();
        } catch (UnmetDependencyException $e) {
            throw new ConfigException('Error on retrieving enabled ConfigItem instances.', $e);
        }

        foreach ($enabledConfigItems as $configItem) {
            if ($configItem->getConfigId() === $configId) {
                return $configItem->getValues();
            }
        }

        return null;
    }

    /**
     * Gets the default list of config item names.
     *
     * @return string[]
     *   Array of default config item names.
     */
    protected function getDefaultConfigItemNames(): array
    {
        return [
            TrustedHostPatternConfigItem::class,
            UserConfigItem::class,
            WebServiceConfigItem::class,
        ];
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(ConfigLoader::class, $this->configLoader);
        $definitions[] = new RootDependencyDefinition(EventDispatcher::class, $this->eventDispatcher);
        return $definitions;
    }

    /**
     * Gets the enabled config item instances.
     *
     * @return ConfigItem[]
     *   An array of enabled config items.
     *
     * @throws UnmetDependencyException
     */
    protected function getEnabledConfigItems(): array
    {
        $enabledConfigItems = [];

        // Get the enabled config items from subscribers.
        $event = new GetEnabledConfigItemEvent();

        $this->eventDispatcher->dispatch($event);

        foreach ($event->getEnabledConfigItemClassNames() as $configItemClassName) {
            $enabledConfigItems[] = $this->getComponentByClassName($configItemClassName, true);
        }

        return $enabledConfigItems;
    }

    public function handleEvent(EventInterface $event): void
    {
        if ($event->getName() !== GetEnabledConfigItemEvent::EVENT_NAME) {
            return;
        }

        $configItemNames = $this->getDefaultConfigItemNames();

        foreach ($configItemNames as $configItemName) {
            /** @var GetEnabledConfigItemEvent $event */
            $event->registerConfigItem($configItemName);
        }
    }

    /**
     * Initialize the configuration.
     *
     * @param string $configurationDirectoryBasePath
     *   Configuration local file path.
     *
     * @throws ConfigException
     */
    public function initializeConfiguration($configurationDirectoryBasePath): void
    {
        // Initialize live configuration from files.
        $values = $this->configLoader->loadConfigFromDirectory($configurationDirectoryBasePath);

        try {
            // Get enabled configuration items.
            $enabledConfigItems = $this->getEnabledConfigItems();
        } catch (UnmetDependencyException $e) {
            throw new ConfigException('Error on retrieving enabled ConfigItem instances.', $e);
        }

        foreach ($values as $configId => $configValues) {
            foreach ($enabledConfigItems as $configItem) {
                if ($configId === $configItem->getConfigId()) {
                    // We only set enabled configurations.
                    $configItem->setValues($configValues);
                    continue 2;
                }
            }
        }
    }
}
