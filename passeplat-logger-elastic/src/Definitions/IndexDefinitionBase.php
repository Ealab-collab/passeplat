<?php

namespace PassePlat\Logger\Elastic\Definitions;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\ComponentBucketType;
use PassePlat\Core\Config\Configuration;
use PassePlat\Logger\Elastic\Exception\ElasticsearchException;

/**
 * Base class for defining Elasticsearch index.
 *
 * This abstract class provides the basic structure for defining the parameters and
 * mappings required for creating Elasticsearch indices.
 */
abstract class IndexDefinitionBase extends ComponentBasedObject
{
    /**
     * Indicates if the index is ready.
     *
     * @var bool
     */
    protected bool $isReady = false;

    /**
     * The prefix to apply to the index ID.
     *
     * @var string
     */
    protected string $prefix = '';

    /**
     * The ID of the index without any prefix.
     *
     * @var string
     */
    protected static string $unprefixedIndexId;

    /**
     * Gets the expected mapping for the log index.
     *
     * @return array
     *   The expected mapping.
     */
    abstract protected static function getExpectedMapping(): array;

    abstract protected function getParams(): array;

    /**
     * Gets the prefixed index ID.
     *
     * @return string
     *   The prefixed index ID.
     * @throws ElasticsearchException
     */
    public function getPrefixedIndexId(): string
    {
        if (empty($this->prefix)) {
            $this->setPrefix();
        }

        return $this->prefix . static::$unprefixedIndexId;
    }

    /**
     * Gets the unprefixed index ID.
     *
     * @return string
     *   The index ID.
     */
    public static function getUnprefixedIndexId(): string
    {
        return static::$unprefixedIndexId;
    }

    /**
     * Checks if the index is ready.
     *
     * @return bool
     *   True if the index is ready, false otherwise.
     */
    public function isReady(): bool
    {
        return $this->isReady;
    }

    /**
     * Sets the prefix to apply to the index ID.
     *
     * @throws ElasticsearchException
     *   If configuration retrieval fails.
     */
    private function setPrefix(): void
    {
        try {
            /** @var Configuration $configuration */
            $configuration = $this->getComponentByClassName(Configuration::class, false, ComponentBucketType::ROOT);
            $configs = $configuration->getConfigValues('elasticsearch.passeplat-logger-elastic');
            if (!empty($configs['prefix']) && is_string($configs['prefix'])) {
                $this->prefix = $configs['prefix'];
            }
        } catch (\Exception $e) {
            throw new ElasticsearchException('Cannot retrieve the ES configs.', $e);
        }
    }

    /**
     * Sets the index as ready.
     */
    public function setReady(): void
    {
        $this->isReady = true;
    }
}
