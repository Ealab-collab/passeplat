<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\Caching;

use Dakwamine\Component\Exception\UnmetDependencyException;
use GuzzleHttp\Psr7\Response;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\HttpWebServiceStatus;
use PassePlat\Core\Exception\Exception;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;
use Dakwamine\Component\ComponentBucketType;
use PassePlat\Core\Config\Configuration;
use Psr\Http\Message\ResponseInterface;
use PassePlat\Logger\Elastic\Config\ConfigItem\Elasticsearch;
use Elasticsearch\ClientBuilder;
use PassePlat\Logger\Elastic\Exception\CouldNotCreateElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\CouldNotLoadElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\ElasticsearchException;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;

/**
 * Caching task.
 *
 * This task is deeply tied with elasticsearch.
 *
 * @todo Déplacer cette tâche dans le package passeplat-elasticsearch.
 */
class Caching_0 extends TaskHandlerBase
{
    const ELASTICSEARCH_LOG_INDEX = 'analyzable_content';

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'hours' => 0,
        ];

        return static::replaceFormData($defaultData, $providedData);
    }

    public static function getFormDefinition(string $rootPath = '~'): array
    {
        return [
            'renderView' => [
                'type' => 'div',
                'content' =>  [
                    [
                        'type' => 'div',
                        'attributes' => [
                            'class' => 'fw-bold'
                        ],
                        'content' => 'Hours'
                    ],
                    [
                        'type' => 'TextField',
                        'attributes' => [
                            'class' => 'pb-2'
                        ],
                        'dataLocation' => '~~.hours',
                        'placeholder' => 'The caching duration in hours.',
                    ]
                ]
            ],
            'listForms' => [],
        ];
    }

    /**
     * Elasticsearch client.
     *
     * @var Client
     */
    private $elasticsearch;

    /**
     * Configs array.
     *
     * @var array
     */
    private $configs;

    /**
    * Execute the event.
    */
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {

        $data = $analyzableContent->getDataToLog();

        // Only launch on non-write methods.
        // @todo : no fallback on images ressources.
        // @todo : no cache if bearer / authentication token.
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            // Do not work on other request method than GET.
            // @todo: When needed, we will make it work for POST.
            return;
        }

        // Load configs for future use in all methods.
        $this->configs = $this->getConfigs();

        // Request Elastic to find
        $query = [
            'size' => 1,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'range' => [
                                'destination_response_start_time' => [
                                    'gte' => 'now-' . $options['hours'] . 'h',
                                    'lt' => 'now',
                                ],
                            ],
                        ],
                        [
                            'match' => [
                                'destination_url' => $data['destination_url'],
                            ],
                        ],
                        [
                            'match' => [
                                'passeplat_wsid' => $data['passeplat_wsid'],
                            ],
                        ],
                        [
                            'match' => [
                                'web_service_status' => '2XX',
                            ],
                        ],
                    ],
                ],
            ],

        ];

        // Search for e
        // @TODO : issue with obfuscation task.
        $elasticJsonResponse = $this->search($query);

        if (!isset($elasticJsonResponse['hits']['hits'][0]['_source']['destination_response_body'])) {
            // There is no saved response in Elastic.
            return;
        }

        // Use the saved response to build the fallback response.
        $source = $elasticJsonResponse['hits']['hits'][0]['_source'];

        // Alert the other tasks that this request must be stopped.
        $analyzableContent->setExecutionInfo('stopRequest', true);

        // Rebuild the response object from the elastic source.
        $this->rebuildResponse($analyzableContent, $source);

        $headerList = $analyzableContent
            ->getComponentByClassName(ResponseInfo::class, true)
            ->getComponentByClassName(Header::class, true);

        // Add our own debugging headers to tell the end user that this was a cached response.
        // @todo: Ajouter une configuration pour configurer ces en-têtes de débogage.
        $headerList->addHeaderFieldEntry(
            'Caching-by-Wsolution',
            'x-Cached by WSolution on ' . $source['destination_response_start_time']
        );
    }

    /**
     * Search.
     *
     * @param AnalyzableContent $content
     *   Analyzed data container.
     *
     * @throws CouldNotCreateElasticsearchIndexException
     * @throws CouldNotLoadElasticsearchIndexException
     * @throws ElasticsearchException
     */
    private function search($query)
    {

        $elasticsearchClient = $this->getElasticClient();

        // Mapping to elasticsearch fields.
        $params = [
            'index' => $this->configs['indexWithPrefix'],
            'body' => $query,
        ];

        // Search it!
        return $elasticsearchClient->search($params);
    }

    /**
     * Gets the elasticsearch client.
     *
     * @return Client|null
     *   The elasticsearch client.
     *
     * @throws CouldNotLoadElasticsearchIndexException
     */
    private function getElasticClient()
    {
        if (empty($this->elasticsearch)) {
            // Client was never instantiated. Build the client for the first time.

            if (empty($this->configs['hosts'])) {
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
     * Gets the ES configs.
     *
     * @return array
     *   Configs.
     *
     * @throws ElasticsearchException
     */
    private function getConfigs(): array
    {    
        try {
            /** @var Configuration $configuration */
            $configuration = $this->getComponentByClassName(Configuration::class, false, ComponentBucketType::ROOT);
            $configs = $configuration->getConfigValues('elasticsearch.passeplat-logger-elastic');
        } catch (\Exception $e) {
            throw new ElasticsearchException('Cannot retrieve the ES configs.', $e);
        }
        // Set the index with prefix if it exists in config.
        $configs['indexWithPrefix'] = static::ELASTICSEARCH_LOG_INDEX;
        if (!empty($configs['prefix']) && is_string($configs['prefix'])) {
            $configs['indexWithPrefix'] = $configs['prefix'] . $configs['indexWithPrefix'];
        }

        return $configs;
    }

    /**
     * Rebuilds a response object from the ElasticSearch source.
     *
     * @param AnalyzableContent $analyzableContent
     *   The AnalyzableContent object.
     * @param array $elasticSearchSource
     *   The ElasticSearch source. It must at least contain the following fields:
     *   - destination_response_body
     *   - destination_response_http_status_code
     *   - destination_response_headers
     *
     * @return ResponseInterface|null
     *   The response object or null if the response could not be rebuilt.
     *
     * @throws Exception
     *   If the response could not be rebuilt due to a serious error.
     * @throws UnmetDependencyException
     *   If the response could not be rebuilt due to a missing code dependency.
     */
    private function rebuildResponse(
        AnalyzableContent $analyzableContent,
        array $elasticSearchSource
    ): ?ResponseInterface {
        // Rebuild the Response object with status and body.
        $body = $elasticSearchSource['destination_response_body'];
        $httpStatusCode = $elasticSearchSource['destination_response_http_status_code'] ?? null;

        if (empty($httpStatusCode)) {
            // This should not happen because the status code is mandatory when logging.
            return null;
        }

        $response = new Response($httpStatusCode, [], $body);

        /** @var ResponseInfo $responseInfo */
        $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class, true);
        $responseInfo->setResponse($response);

        // Reset the body log component to the cached one.
        /** @var Body $responseBody */
        $responseBody = $responseInfo->getComponentByClassName(Body::class, true);
        $responseBody->resetBody();
        $responseBody->write($source['destination_response_body'] ?? '');

        // Rebuild the headers.
        $headerList = $responseInfo->getComponentByClassName(Header::class, true);
        $headerList->removeHeaders();
        $headers = json_decode($elasticSearchSource['destination_response_headers'] ?? '[]', true);

        foreach ($headers as $header) {
            $headerList->addHeaderFieldEntry($header['key'], $header['value']);
        }

        // Log the special WSolution status.
        /** @var HttpWebServiceStatus $httpWebServiceStatus */
        $httpWebServiceStatus = $analyzableContent->getComponentByClassName(HttpWebServiceStatus::class, true);

        $httpWebServiceStatus->setStatusFromResponse($response, true, 'WS');

        return $response;
    }

    /**
     * Gets the ES hosts from config.
     *
     * @return string[]
     *   Host names.
     *
     * @throws ElasticsearchException
     */
    private function getHosts(): array
    {
        try {
            /** @var Configuration $configuration */
            $configuration = $this->getComponentByClassName(Configuration::class, false, ComponentBucketType::ROOT);
            $values = $configuration->getConfigValues('elasticsearch.passeplat-logger-elastic');
        } catch (\Exception $e) {
            throw new ElasticsearchException('Cannot retrieve the ES hosts.', $e);
        }
        if (empty($values['hosts'] || !is_array($values['hosts']))) {
            // Basic check.
            return [];
        }

        return $values['hosts'];
    }
}
