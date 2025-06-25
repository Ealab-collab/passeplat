<?php

namespace PassePlat\Core\Security;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Config\ConfigItem\TrustedHostPatternConfigItem;
use PassePlat\Core\Config\Configuration;
use Psr\Http\Message\UriInterface;

/**
 * Provides checks on the host.
 */
class HostChecker extends ComponentBasedObject implements HostCheckerInterface
{
    /**
     * A cache to serve already processed host checks.
     *
     * @var array<string,string>
     */
    private array $cache = [];

    /**
     * Enabled configurations holder.
     *
     * @var Configuration
     */
    protected $configuration;

    /**
     * Caches the result of a host check.
     *
     * @param string $host
     *   The host name.
     * @param string $result
     *   One of the HostCheckerInterface::HOST_TYPE__* constants.
     */
    private function cacheResult($host, $result): void
    {
        if (count($this->cache) > 10000) {
            $this->cache = array_slice($this->cache, 5000, null, true);
        }

        $this->cache[$host] = $result;
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(Configuration::class, $this->configuration);
        return $definitions;
    }

    /**
     * Gets the host type from the given URL.
     *
     * @param UriInterface $uri
     *   The URI object containing URL info.
     * @param bool $recheck
     *   Set to true to check again if already checked once. When false, it will use the cached result.
     *
     * @return string
     *   Host type, one of HostCheckerInterface::* consts.
     *
     * @throws UnmetDependencyException
     */
    public function getHostTypeFromUrl(UriInterface $uri, bool $recheck = false): string
    {
        $host = $uri->getHost();

        if (!$recheck && isset($this->cache[$host])) {
            return $this->cache[$host];
        }

        /** @var TrustedHostPatternConfigItem $trustedHostPatterns */
        $trustedHostPatterns = $this->configuration->getComponentByClassName(TrustedHostPatternConfigItem::class);

        if (empty($trustedHostPatterns)) {
            // This should not happen on a well configured passeplat instance.
            $this->cacheResult($host, static::HOST_TYPE__UNKNOWN);
            return static::HOST_TYPE__UNKNOWN;
        }

        // Attempt to match with a pattern containing the destination URL.
        foreach ($trustedHostPatterns->getTrustedHostPatternsWithDestination() as $pattern) {
            if (preg_match($pattern, $host)) {
                $this->cacheResult($host, static::HOST_TYPE__WITH_DESTINATION);
                return static::HOST_TYPE__WITH_DESTINATION;
            }
        }

        // Attempt to match with a pattern containing the user ID.
        foreach ($trustedHostPatterns->getTrustedHostPatternsWithUserId() as $pattern) {
            if (preg_match($pattern, $host)) {
                $this->cacheResult($host, static::HOST_TYPE__WITH_USER_ID);
                return static::HOST_TYPE__WITH_USER_ID;
            }
        }

        // Attempt to match with a base pattern.
        foreach ($trustedHostPatterns->getTrustedHostPatterns() as $pattern) {
            if (preg_match($pattern, $host)) {
                $this->cacheResult($host, static::HOST_TYPE__BASE);
                return static::HOST_TYPE__BASE;
            }
        }

        // Not a known host pattern.
        $this->cacheResult($host, static::HOST_TYPE__UNKNOWN);
        return static::HOST_TYPE__UNKNOWN;
    }
}
