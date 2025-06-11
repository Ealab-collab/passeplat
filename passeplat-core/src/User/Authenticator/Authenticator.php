<?php

namespace PassePlat\Core\User\Authenticator;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Exception\AuthenticationException;
use PassePlat\Core\Exception\UserRepositoryException;
use PassePlat\Core\User\UnrestrictedUser;
use PassePlat\Core\User\UserInterface;
use PassePlat\Core\User\UserManager;
use PassePlat\Core\User\UserManagerInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authenticator.
 */
class Authenticator extends ComponentBasedObject implements AuthenticatorInterface
{
    /**
     * User manager.
     *
     * @var UserManagerInterface
     */
    private $userManager;

    /**
     * Authenticates the user for the given request.
     *
     * @param ServerRequestInterface $request
     *   The request to inspect for authentication.
     *
     * @return UserInterface|null
     *   The user, if found. null if not found.
     *
     * @throws AuthenticationException
     */
    public function authenticate(ServerRequestInterface $request): ?UserInterface
    {
        try {
            if (!$this->userManager->hasUsers()) {
                // Unrestricted mode.
                return new UnrestrictedUser();
            }
        } catch (UserRepositoryException $exception) {
            throw new AuthenticationException('Could not authenticate against invalid user repository', $exception);
        }

        /** @var $strategyClasses string[] */
        $strategyClasses[] = QueryParametersAuthenticationStrategy::class;
        $strategyClasses[] = UidInHostNameAuthenticationStrategy::class;
        $strategyClasses[] = DestinationAndUidInHostNameAuthenticationStrategy::class;
        // Todo: add header / token / etc authentication strategies.

        foreach ($strategyClasses as $strategyClass) {
            try {
                /** @var AuthenticatorStrategyInterface $authenticationStrategy */
                $authenticationStrategy = $this->getComponentByClassName($strategyClass, true);
            } catch (UnmetDependencyException $e) {
                continue;
            }

            $user = $authenticationStrategy->authenticate($request);

            if (!empty($user)) {
                // User found and valid.
                return $user;
            }
        }

        // No user found, none of the authentication strategies worked.
        return null;
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(UserManager::class, $this->userManager);
        return $definitions;
    }
}
