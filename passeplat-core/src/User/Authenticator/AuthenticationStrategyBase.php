<?php

namespace PassePlat\Core\User\Authenticator;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\User\UserManager;
use PassePlat\Core\User\UserManagerInterface;

/**
 * Base class for authenticator plugins.
 */
abstract class AuthenticationStrategyBase extends ComponentBasedObject implements
    AuthenticatorInterface,
    AuthenticatorStrategyInterface
{
    /**
     * User manager.
     *
     * @var UserManagerInterface
     */
    protected $userManager;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(UserManager::class, $this->userManager);
        return $definitions;
    }
}
