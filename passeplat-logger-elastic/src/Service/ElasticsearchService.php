<?php

namespace PassePlat\Logger\Elastic\Service;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\ComponentBucketType;
use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;
use Elasticsearch\Common\Exceptions\ElasticsearchException as ES_ElasticsearchException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use PassePlat\Core\Config\Configuration;
use PassePlat\Core\Tool\PropertiesComparer;
use PassePlat\Logger\Elastic\Definitions\IndexDefinitionBase;
use PassePlat\Logger\Elastic\Exception\CouldNotCreateElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\CouldNotLoadElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\ElasticsearchException;

/**
 * This service class manages the interactions with an Elasticsearch, providing functionality
 * to configure the client, prepare indices, and log data into Elasticsearch.
 */
class ElasticsearchService extends ComponentBasedObject
{
    /**
     * Configs array.
     *
     * @var array
     */
    private array $configs;

    /**
     * Elasticsearch client.
     *
     * @var Client
     */
    private Client $elasticsearch;

    /**
     * Adds the missing fields from the expected mapping to the active mapping.
     *
     * @param IndexDefinitionBase $indexDef
     *   The index definition object.
     * @param array $activeMapping
     *   The active mapping.
     * @param array $expectedMapping
     *   The expected mapping.
     *
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    public function addMissingFieldsToActiveMapping(
        IndexDefinitionBase $indexDef,
        array $activeMapping,
        array $expectedMapping
    ): void {
        $missingFields = array_diff_key($expectedMapping, $activeMapping);

        if (empty($missingFields)) {
            // No field to add.
            return;
        }

        $this->getElasticsearchClient()->indices()->putMapping([
            'index' => $indexDef->getPrefixedIndexId(),
            'body' => [
                'properties' => $missingFields,
            ],
        ]);
    }

    /**
     * Returns the Elasticsearch configurations.
     *
     * @return array The configurations array.
     *
     * @throws ElasticsearchException
     *   If configurations are not set.
     */
    public function getConfigs(): array
    {
        if (empty($this->configs)) {
            $this->setConfigs();
        }

        return $this->configs;
    }

    /**
     * Gets the Elasticsearch client.
     *
     * @return Client|null
     *   The Elasticsearch client.
     *
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    public function getElasticsearchClient(): ?Client
    {
        if (empty($this->elasticsearch)) {
            // Client was never instantiated. Build the client for the first time.
            if (empty($this->getConfigs()['hosts'])) {
                throw new CouldNotLoadElasticsearchIndexException('ES hosts undefined.');
            }

            // Connection failure may happen here, but this will happen only if all nodes are not responding
            // (crashed, unavailable, authentication issue). This is due to curl having a too long timeout.
            // TODO: essayer de trouver un moyen pour que le timeout soit plus court ou pour que curl stoppe
            //   quand l'hôte ES refuse la connexion.
            $clientBuilder = ClientBuilder::create();

            // Determine the mode between hosts and elastic cloud.
            $firstItem = reset($this->configs['hosts']);

            if (!empty($firstItem['elastic_cloud_id'])) {
                // Elastic cloud mode.
                $clientBuilder->setElasticCloudId($firstItem['elastic_cloud_id']);
                $clientBuilder->setApiKey($firstItem['api_key_id'], $firstItem['api_key']);
            } else {
                // Hosts mode.
                $clientBuilder->setHosts($this->configs['hosts']);
            }

            $this->elasticsearch = $clientBuilder->build();
        }

        return $this->elasticsearch;
    }

    /**
     * Logs the given data to Elasticsearch.
     *
     * @param IndexDefinitionBase $indexDef
     *   The index definition object.
     *
     * @param array $data
     *   The data to log.
     *
     * @return array
     * @throws CouldNotCreateElasticsearchIndexException
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    public function logBulk(IndexDefinitionBase $indexDef, array $data): array
    {
        $this->prepareIndex($indexDef);

        $bulkParams = ['body' => []];

        foreach ($data as $item) {
            $bulkParams['body'][] = [
                'index' => [
                    '_index' => $indexDef->getPrefixedIndexId(),
                ]
            ];
            $bulkParams['body'][] = $item;
        }

        if (!empty($bulkParams['body'])) {
            return $this->getElasticsearchClient()->bulk($bulkParams);
        }
    }

    /**
     * Logs the given data to Elasticsearch.
     *
     * @param IndexDefinitionBase $indexDef
     *   The index definition object.
     *
     * @param array $data
     *   The data to log.
     *
     * @return array
     * @throws CouldNotCreateElasticsearchIndexException
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    public function logItem(IndexDefinitionBase $indexDef, array $data): array
    {
        $this->prepareIndex($indexDef);

        $params = [
            'index' => $indexDef->getPrefixedIndexId(),
            'body' => $data,
        ];

        return $this->getElasticsearchClient()->index($params);
    }

    /**
     * Prepare an Elasticsearch index with the provided parameters.
     *
     * @param IndexDefinitionBase $indexDef
     *   The index definition object.
     *
     * @throws CouldNotCreateElasticsearchIndexException
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    public function prepareIndex(IndexDefinitionBase $indexDef): void
    {
        if ($indexDef->isReady()) {
            return;
        }

        $prefixedIndex = $indexDef->getPrefixedIndexId();

        $isRetrying = false;

        while (true) {
            try {
                $this->getElasticsearchClient()->indices()->get([
                    'index' => $prefixedIndex,
                ]);
            } catch (Missing404Exception $exception) {
                if ($isRetrying) {
                    throw new CouldNotLoadElasticsearchIndexException();
                }

                $params = $indexDef->getParams();

                try {
                    $this->getElasticsearchClient()->indices()->create($params);
                } catch (\Exception $exception) {
                    throw new CouldNotCreateElasticsearchIndexException();
                }

                $isRetrying = true;
                continue;
            } catch (ES_ElasticsearchException $exception) {
                throw new ElasticsearchException(
                    'Tried to init Elasticsearch index, but Elasticsearch did not comply with our demands.'
                );
            } catch (\Exception $exception) {
                throw new ElasticsearchException(
                    'Tried to init Elasticsearch index, but an unknown error occurred.'
                );
            }

            $activeMapping = $this->getElasticsearchClient()->indices()->getMapping([
                'index' => $prefixedIndex,
            ])[$prefixedIndex]['mappings']['properties'];

            $expectedMapping = $indexDef::getExpectedMapping();

            if (count($activeMapping) < count($expectedMapping)) {
                if (!PropertiesComparer::containsArray($expectedMapping, $activeMapping)) {
                    $this->remapAndReindex($indexDef, $activeMapping, $expectedMapping);
                } else {
                    $this->addMissingFieldsToActiveMapping(
                        $indexDef,
                        $activeMapping,
                        $expectedMapping
                    );
                }
            } elseif (count($activeMapping) > count($expectedMapping)) {
                if (!PropertiesComparer::containsArray($activeMapping, $expectedMapping)) {
                    $this->remapAndReindex($indexDef, $activeMapping, $expectedMapping);
                }
            } else {
                if (!PropertiesComparer::containsArray($activeMapping, $expectedMapping)) {
                    $this->remapAndReindex($indexDef, $activeMapping, $expectedMapping);
                }
            }

            break;
        }

        $indexDef->setReady();
    }

    /**
     * Remaps the active mapping to the expected mapping and reindexes the items.
     *
     * //TODO: réfléchir aux implications de disponibilité.
     *
     * @param IndexDefinitionBase $indexDef
     *   The index definition object.
     * @param array $activeMapping
     *   The active mapping as given by the ES Mapping API.
     * @param array $expectedMapping
     *   The expected mapping.
     *
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    private function remapAndReindex(IndexDefinitionBase $indexDef, array $activeMapping, array $expectedMapping): void
    {
        // TODO: création d'alias, réindexation, etc.
        //  Voir https://www.elastic.co/fr/blog/changing-mapping-with-zero-downtime
        //  Pour le moment, on n'assure juste que l'ajout de nouveaux champs.
        //  La prochaine ligne devra donc être supprimée lors de l'implémentation
        //  de cette méthode, car nous devrons créer un tout nouvel index.
        $this->addMissingFieldsToActiveMapping($indexDef, $activeMapping, $expectedMapping);
    }

    /**
     * Executes a search query in Elasticsearch.
     *
     * @param IndexDefinitionBase $indexDef
     *   The index definition object.
     *
     * @param array $query
     *   The search query to send to Elasticsearch.
     *
     * @return array
     *   The search results.
     *
     * @throws ElasticsearchException
     *   If an error occurs during the search.
     */
    public function search(IndexDefinitionBase $indexDef, array $query): array
    {
        try {
            $params = [
                'index' => $indexDef->getPrefixedIndexId(),
                'body' => $query,
            ];

            return $this->getElasticsearchClient()->search($params);
        } catch (\Exception $exception) {
            throw new ElasticsearchException(
                'An error occurred while performing the Elasticsearch search.',
                $exception
            );
        }
    }

    /**
     * Gets the Elasticsearch configs.
     *
     * @throws ElasticsearchException
     */
    public function setConfigs(): void
    {
        try {
            /** @var Configuration $configuration */
            $configuration = $this->getComponentByClassName(Configuration::class, true, ComponentBucketType::ROOT);
            $configs = $configuration->getConfigValues('elasticsearch.passeplat-logger-elastic');
            $this->configs = $configs;
        } catch (\Exception $e) {
            throw new ElasticsearchException('Cannot retrieve the ES configs.', $e);
        }
    }
}
