<?php

namespace PassePlat\Core\User\Repository;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Config\ConfigLoader;
use PassePlat\Core\Exception\ConfigException;
use PassePlat\Core\Exception\ErrorCode;
use PassePlat\Core\Exception\UserRepositoryException;

/**
 * Local config user repository.
 *
 * Not the most efficient repository for large user bases.
 * Useful when a database is not available and when only few users exist.
 */
class LocalConfigUserRepository extends UserRepository
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

    public function authenticateUser($uid, $token): bool
    {
        if (empty($uid)) {
            return false;
        }

        try {
            foreach ($this->getDefinitions() as $definition) {
                if ($definition['uid'] !== $uid) {
                    continue;
                }

                if (empty($definition['token']) && empty($token)) {
                    // User without token. Kinda bad.
                    return true;
                }

                if (!empty($definition['token']) && $definition['token'] === $token) {
                    return true;
                }
            }
        } catch (ConfigException $exception) {
            // Do as no user was found.
            return false;
        }

        return false;
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(ConfigLoader::class, $this->configLoader);
        return $definitions;
    }

    /**
     * Gets the users definitions.
     *
     * @throws ConfigException
     *   Config error.
     */
    private function getDefinitions(): array
    {
        if (!$this->hasLoaded) {
            $this->definitions = $this->configLoader->loadConfigFromDirectory('config/app/user');
            $this->hasLoaded = true;
        }

        return $this->definitions;
    }

    public function hasUsers(): bool
    {
        try {
            return !empty($this->getDefinitions());
        } catch (ConfigException $exception) {
            throw new UserRepositoryException('Could not load the users.', $exception, ErrorCode::PP_USER, 0);
        }
    }
}
