<?php

namespace PassePlat\Core\Config\ConfigItem;

use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Exception\UserException;
use PassePlat\Core\User\UserManager;
use PassePlat\Core\User\UserManagerInterface;

/**
 * Contains user config.
 */
class UserConfigItem extends ConfigItem
{
    /**
     * User manager.
     *
     * @var UserManagerInterface
     */
    protected $userManager;

    public function getConfigId(): string
    {
        return 'user.passeplat-core';
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(UserManager::class, $this->userManager);
        return $definitions;
    }

    public function setValues(array $values): void
    {
        unset($this->config);

        if (empty($values)) {
            return;
        }

        $this->config = $values;

        if (!empty($values['repository']['type'])) {
            if (!($userManager = $this->userManager)) {
                // No user manager.
                return;
            }

            try {
                // Get the user manager and set the repository type.
                $userManager->setRepositoryType($values['repository']['type']);
            } catch (UserException $e) {
                // No repository will be set.
                return;
            }
        }
    }
}
