<?php

namespace PassePlat\Core\User\Authenticator;

use PassePlat\Core\Exception\AuthenticationException;
use PassePlat\Core\User\UserInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for authenticators.
 */
interface AuthenticatorInterface
{
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
    public function authenticate(ServerRequestInterface $request): ?UserInterface;
}
