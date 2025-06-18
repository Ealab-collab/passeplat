<?php

namespace PassePlat\Core\WebService\Repository;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Config\ConfigLoader;
use PassePlat\Core\Exception\ConfigException;
use PassePlat\Core\Exception\ErrorCode;
use PassePlat\Core\Exception\UserRepositoryException;
use PassePlat\Core\User\Repository\UserRepository;

/**
 * Local config webservice repository.
 *
 */
class LocalConfigWebServiceRepository extends WebServiceRepository
{
    /**
     * Config loader.
     *
     * @var ConfigLoader
     */
    private $configLoader;

    /**
     * User definitions.
     *
     * @var array
     */
    private $definitions = [];

    /**
     * Tells if definitions were loaded.
     *
     * @var bool
     */
    private $hasLoaded = false;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(ConfigLoader::class, $this->configLoader);
        return $definitions;
    }

	/**
	 * Loads the config file for the web service identified by the given arguments.
	 *
	 * @param string $webServiceId
	 *   ID of the web service.
	 * @param string $userId
	 *   ID of the user who attempts to use the web service.
	 *
	 * @return array
	 *   Array of data.
	 *
	 * @throws UnmetDependencyException
	 * @throws ConfigException
	 */
	public function loadWebServiceConfigFromFileSystem(string $webServiceId, string $userId): array
	{
		/** @var ConfigLoader $configLoader */
		$configLoader = $this->getComponentByClassName(ConfigLoader::class, true);

		// Load webservice-specific config.
		$configs = $configLoader->loadConfigFromDirectory('config/app/webservice', $webServiceId);

		if (!empty($configs)) {
			return reset($configs);
		}

		// Load the default user config.
		$userConfig = $configLoader->loadConfigFromDirectory('config/app/webservice', $userId);

		if (!empty($userConfig[$userId])) {
			return $userConfig[$userId];
		}

		// Load the application wide default config.
		$userConfig = $configLoader->loadConfigFromDirectory('config/app/webservice', 'default');

		if (!empty($userConfig['default'])) {
			return $userConfig['default'];
		}

		return [];
	}
}
