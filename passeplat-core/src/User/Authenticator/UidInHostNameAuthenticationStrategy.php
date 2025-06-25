<?php

namespace PassePlat\Core\User\Authenticator;

use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Security\HostChecker;
use PassePlat\Core\Security\HostCheckerInterface;
use PassePlat\Core\Tool\UrlQueryParser;
use PassePlat\Core\User\User;
use PassePlat\Core\User\UserInterface;
use Psr\Http\Message\ServerRequestInterface;

class UidInHostNameAuthenticationStrategy extends AuthenticationStrategyBase
{
    const QUERY_STRING__PP_TOKEN = 'PP_TOKEN';

    /**
     * Host checker.
     *
     * @var HostCheckerInterface
     */
    protected $hostChecker;

    public function authenticate(ServerRequestInterface $request): ?UserInterface
    {
        if ($this->hostChecker->getHostTypeFromUrl($request->getUri())
            !== HostCheckerInterface::HOST_TYPE__WITH_USER_ID) {
            return null;
        }

        $url = $request->getUri();

        // Break the host in parts. The only useful part is the first one.
        $host = $url->getHost();
        $parts = explode('.', $host);

        $userId = $parts[0];

        if (empty($userId)) {
            return null;
        }

        if (preg_match('/^.+---.+$/', $userId)) {
            // Not an allowed pattern. The user ID cannot have three consecutive dashes.
            // This is reserved for the DestinationAndUidInHostNameAuthenticationStrategy.
            // Fix the trusted host patterns if this happens.
            return null;
        }

        // Parse query parameters.
        $queryParameters = UrlQueryParser::parseFromUrl($url);

        // Start by checking the token. This is an optional value.
        $token = !empty($queryParameters[static::QUERY_STRING__PP_TOKEN])
            ? $queryParameters[static::QUERY_STRING__PP_TOKEN]
            : '';

        // We have all the info we need to attempt to find the user.
        if (!$this->userManager->authenticateUser($userId, $token)) {
            return null;
        }

        return new User($userId);
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(HostChecker::class, $this->hostChecker);
        return $definitions;
    }
}
