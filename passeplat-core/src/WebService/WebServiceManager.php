<?php

namespace PassePlat\Core\WebService;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use GuzzleHttp\Psr7\Uri;
use PassePlat\Core\Config\ConfigLoader;
use PassePlat\Core\Exception\AuthenticationException;
use PassePlat\Core\Exception\ConfigException;
use PassePlat\Core\Exception\UserNotFoundException;
use PassePlat\Core\Exception\WebServiceInvalidUriException;
use PassePlat\Core\Security\HostChecker;
use PassePlat\Core\Security\HostCheckerInterface;
use PassePlat\Core\Tool\UrlQueryParser;
use PassePlat\Core\User\Authenticator\Authenticator;
use PassePlat\Core\User\Authenticator\AuthenticatorInterface;
use PassePlat\Core\User\Authenticator\QueryParametersAuthenticationStrategy;
use PassePlat\Core\User\UserInterface;
use PassePlat\Core\User\UserManager;
use PassePlat\Core\User\UserManagerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Web service manager.
 */
class WebServiceManager extends ComponentBasedObject implements WebServiceManagerInterface
{
    /**
     * Authenticator.
     *
     * @var AuthenticatorInterface
     */
    private $authenticator;

    /**
     * Host checker.
     *
     * @var HostCheckerInterface
     */
    private $hostChecker;

    /**
     * User manager.
     *
     * @var UserManagerInterface
     */
    private $userManager;

    /**
     * Extracts web service parameters from the URL.
     *
     * @param UriInterface $url
     *   The URL to inspect.
     *
     * @return array|null
     *   An array of [wsid, url], or null on failure.
     */
    private function extractParametersFromUrl(UriInterface $url): ?array
    {
        $hostType = $this->hostChecker->getHostTypeFromUrl($url);

        switch ($hostType) {
            case HostCheckerInterface::HOST_TYPE__BASE:
                return $this->scenarioA($url);
            case HostCheckerInterface::HOST_TYPE__WITH_USER_ID:
                return $this->scenarioB($url);
            case HostCheckerInterface::HOST_TYPE__WITH_DESTINATION:
                return $this->scenarioC($url);
        }

        return null;
    }

    /**
     * Extracts the destination URL from the server incoming request URL or query parameters.
     *
     * @param UriInterface $url
     *   The URL object.
     *
     * @return UriInterface
     *   The URL for the given initiator URL. Returns null if the URL is insufficient to extract the web service path.
     */
    private function extractDestinationUrlFromRequestUrl(UriInterface $url): ?UriInterface
    {
        // Parse query parameters.
        $queryParameters = UrlQueryParser::parseFromUrl($url);

        if (!empty($queryParameters[static::QUERY_STRING__PP_DESTINATION_URL])) {
            // This could be a path, or even an absolute URL.
            $path = $queryParameters[static::QUERY_STRING__PP_DESTINATION_URL];
        } else {
            // Use the incoming URL path for the destination URL path (path forwarding method).
            $path = $url->getPath();
            $path .= empty($url->getQuery()) ? '' : '?' . $url->getQuery();
            $path .= empty($url->getFragment()) ? '' : '#' . $url->getFragment();
        }

        $webServiceUrl = new Uri($path);

        if (!empty($webServiceUrl->getHost())) {
            // There is already a host, so this URL is complete.
            return $this->filterPpQueryParameters($webServiceUrl);
        }

        // No host. Attempt to get the host from another parameter.
        if (!empty($queryParameters[static::QUERY_STRING__PP_DESTINATION_SCHEME_AND_HOST])) {
            $completeUrl = $queryParameters[static::QUERY_STRING__PP_DESTINATION_SCHEME_AND_HOST]
                . $webServiceUrl->__toString();
            $webServiceUrl = new Uri($completeUrl);
            return $this->filterPpQueryParameters($webServiceUrl);
        }

        // TODO: faire la gestion des host name par host type.
        // TODO: 2025-06-03: ce n'est pas déjà fait ?

        // No host.
        return null;
    }

    /**
     * Filters out PassePlat query parameters.
     *
     * @param UriInterface $url
     *   The URL to edit.
     *
     * @return UriInterface
     *   The URI cleared from PP_* parameters.
     */
    private function filterPpQueryParameters(UriInterface $url): UriInterface
    {
        // Get the query strings.
        $queryParameters = UrlQueryParser::parseFromUrl($url);

        // Todo: make this array more modulable / dynamic, e.g. by detecting query params against
        // patterns with a configurable prefix.
        $toUnset = [
            static::QUERY_STRING__PP_DESTINATION_SCHEME_AND_HOST,
            static::QUERY_STRING__PP_DESTINATION_URL,
            static::QUERY_STRING__PP_USER,
            QueryParametersAuthenticationStrategy::QUERY_STRING__PP_TOKEN,
            QueryParametersAuthenticationStrategy::QUERY_STRING__PP_USER_ID,
        ];

        foreach ($queryParameters as $key => $value) {
            if (in_array($key, $toUnset, true)) {
                unset($queryParameters[$key]);
            }
        }

        $newQueryParameters = [];
        foreach ($queryParameters as $key => $value) {
            $newQueryParameters[] = $key . '=' . $value;
        }

        $newQueryString = implode('&', $newQueryParameters);

        return $url->withQuery($newQueryString);
    }

    /**
     * Attempts to build a user definition from the server request.
     *
     * @param ServerRequestInterface $serverRequest
     *   The URI which may contain login details.
     *
     * @return UserInterface|null
     *   The user. May be not ready / invalid (subscription ended, not confirmed yet, etc.). May be null if not found.
     *
     * @throws AuthenticationException
     */
    private function getUserFromServerRequest(ServerRequestInterface $serverRequest): ?UserInterface
    {
        return $this->authenticator->authenticate($serverRequest);
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(Authenticator::class, $this->authenticator);
        $definitions[] = new RootDependencyDefinition(HostChecker::class, $this->hostChecker);
        $definitions[] = new RootDependencyDefinition(UserManager::class, $this->userManager);
        return $definitions;
    }

    public function getWebServiceFromRequest(ServerRequestInterface $serverRequest): WebServiceInterface
    {
        try {
            // Get the user first. This is mandatory to have a user, even an unrestricted one, so we can check it first.
            $user = $this->getUserFromServerRequest($serverRequest);
        } catch (AuthenticationException $e) {
            // Be unspecific when throwing this error to prevent implementation details leaks.
            // The level of verbosity could be controlled by a debug flag.
            throw new UserNotFoundException();
        }

        if (empty($user)) {
            // No user found. This is suspicious.
            throw new UserNotFoundException();
        }

        $url = $serverRequest->getUri();
        $webServiceParameters = $this->extractParametersFromUrl($url);
        $webServiceUri = !empty($webServiceParameters['url']) ? $webServiceParameters['url'] : null;

        if (empty($webServiceUri) || empty($webServiceUri->getHost())) {
            // Do not load a webservice with an invalid URI.
            throw new WebServiceInvalidUriException();
        }

        $webServiceId = !empty($webServiceParameters['wsid']) ? $webServiceParameters['wsid'] : null;

        /** @var WebService $webService */
        $webService = $this->addComponentByClassName(WebService::class);

        if (empty($webServiceId)) {
            // Web service ID not found.
            // Create a fake web service.
            $webService->initValues('', [$user], $webServiceUri, $user);
            return $webService;
        }

        try {
            // Load the tasks affiliated to this web service ID.
            // Note: fallback config may be returned.
            $webServiceConfig = $this->loadWebServiceConfigFromFileSystem($webServiceId, $user->getId());
        } catch (ConfigException $e) {
            // Attempt to deliver the streams without doing anything else.
            $webServiceConfig = [];
        }

        $webServiceTasks = !empty($webServiceConfig['tasks']) ? $webServiceConfig['tasks'] : [];

        $webService->initValues($webServiceId, [], $webServiceUri, $user, $webServiceTasks);
        return $webService;
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
    private function loadWebServiceConfigFromFileSystem(string $webServiceId, string $userId): array
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

    /**
     * Extracts web service parameters from the URL with BASE host type.
     *
     * Scenario A requires the PP_DESTINATION_URL and PP_USER query arguments.
     *
     * @param UriInterface $url
     *   The URI object of the incoming request. Allowed URL example:
     *     - https://wsolutiondomain.tld/?PP_DESTINATION_URL=scheme://domain.name/path/index.html&PP_USER=user_id&PP_TOKEN=token
     *
     * @return array<string,mixed>|null
     *   The web service config, or null on failure. Keys: host, scheme, url, wsid.
     */
    private function scenarioA(UriInterface $url): ?array
    {
        // All parameters come from the query parameters. Parse query parameters.
        $queryParameters = UrlQueryParser::parseFromUrl($url);

        $userId = !empty($queryParameters[static::QUERY_STRING__PP_USER])
            ? $queryParameters[static::QUERY_STRING__PP_USER]
            : null;

        if (empty($userId)) {
            return null;
        }

        return $this->scenarioAB($url, $userId);
    }

    /**
     * Common method for scenarios A and B.
     *
     * @param UriInterface $url
     *   The URI object of the incoming request.
     * @param string $userId
     *   User ID.
     *
     * @return array<string,mixed>|null
     *   The web service config, or null on failure. Keys: host, scheme, url, wsid.
     */
    private function scenarioAB(UriInterface $url, string $userId): ?array
    {
        $return = [];

        $return['url'] = $this->extractDestinationUrlFromRequestUrl($url);

        // Determine the webservice ID from the destination URL host.
        if (empty($return['url'])) {
            // There is no destination URL either.
            return null;
        }

        $return['host'] = $return['url']->getHost();

        if (empty($return['host'])) {
            // The destination URL is probably invalid.
            return null;
        }

        $wsid = str_replace('-', '§', $return['host']);
        $wsid = str_replace('§', '--', $wsid);
        $wsid = str_replace('.', '-', $wsid);

        // Append the user ID.
        $wsid .= '---' . $userId;
        $return['wsid'] = $wsid;

        $scheme = $return['url']->getScheme();

        // Implicit https.
        $return['scheme'] = empty($scheme) ? 'https' : $scheme;
        return $return;
    }

    /**
     * Extracts web service parameters from the URL with user ID in host name.
     *
     * Scenario B requires the PP_DESTINATION_URL query argument, and the user ID is the subdomain.
     *
     * @param UriInterface $url
     *   The URI object of the incoming request. Allowed URL example:
     *     - https://user_id.wsolutiondomain.tld/?PP_DESTINATION_URL=scheme://domain.name/path/index.html&PP_TOKEN=token
     *
     * @return array<string,mixed>|null
     *   The web service config, or null on failure. Keys: host, scheme, url, wsid.
     */
    private function scenarioB(UriInterface $url): ?array
    {
        // Break the host in parts. The only useful part is the first one.
        $parts = explode('.', $url->getHost());

        if (preg_match('/^.+---.+$/', $parts[0])) {
            // Not the expected pattern.
            return null;
        }

        return $this->scenarioAB($url, $parts[0]);
    }

    /**
     * Extracts web service parameters from the URL with destination URL in host name.
     *
     * Scenario C determines the destination host and the user ID from the subdomain.
     * The path of the incoming server request is used as the path of the destination URL.
     *
     * @param UriInterface $url
     *   The URI object of the incoming request. Allowed URL examples:
     *     - https://scheme---domain-name---user_id.wsolutiondomain.tld/path/index.html?PP_TOKEN=token
     *     - https://domain-name---user_id.wsolutiondomain.tld/path/index.html?PP_TOKEN=token
     *
     * @return array<string,mixed>|null
     *   The web service config, or null on failure. Keys: host, scheme, url, wsid.
     */
    private function scenarioC(UriInterface $url): ?array
    {
        $parsedUrl = parse_url($url);

        $subdomains = explode('.', $parsedUrl['host']);

        // We only work on the first subdomain. It is the webservice ID.
        $return['wsid'] = $subdomains[0];

        if (preg_match('/^(.+[^-])---([^-].+[^-])---[^-].+$/', $subdomains[0], $matches)) {
            $return['scheme'] = $matches[1];
            $host = $matches[2];
        } elseif (preg_match('/^(.+[^-])---[^-].+$/', $subdomains[0], $matches)) {
            // Implicit https.
            $return['wsid'] = 'https---' . $return['wsid'];
            $return['scheme'] = 'https';
            $host = $matches[1];
        }

        if (empty($host)) {
            // Cannot retrieve the host.
            return null;
        }

        // Convert to the real host name.
        $host = str_replace('--', '§', $host);
        $host = str_replace('-', '.', $host);
        $return['host'] = str_replace('§', '-', $host);

        $destinationUrl = $return['scheme'] . '://' . $return['host'] . $parsedUrl['path'];

        if (isset($parsedUrl['query'])) {
            $destinationUrl .= '?' . $parsedUrl['query'];
        }

        $return['url'] = new Uri($destinationUrl);

        return $return;
    }
}
