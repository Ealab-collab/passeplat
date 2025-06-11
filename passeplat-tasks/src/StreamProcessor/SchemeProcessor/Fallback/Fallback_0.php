<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\Fallback;

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

/**
 * Fallback task.
 * @todo mutualiser avec caching task (la majorité des fonctions sont les mêmes).
 */
class Fallback_0 extends TaskHandlerBase
{
    const ELASTICSEARCH_LOG_INDEX = 'analyzable_content';

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

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [];

        return static::replaceFormData($defaultData, $providedData);
    }

    public static function getFormDefinition(string $rootPath = '~'): array
    {
        return [
            'renderView' => [
                'type' => 'div',
                'attributes' => [
                    'class' => '',
                ],
                'content' => 'This task version has no configurable options.'
            ],
            'listForms' => []
        ];
    }

    /**
    * Execute the event.
    */
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {
        /** @var ResponseInfo $responseInfo */
        $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);

        if ($this->isChunkedTransferEncoding($responseInfo)) {
            // Do not handle chunked content at this stage.
            // @todo:
            //   La bonne façon de gérer ce cas serait que, par la présence de la tâche fallback dans la configuration
            //   du service web, le stream processor soit reconfiguré pour attendre
            //   la fin du transfert pour que le body soit complet ici.
            return;
        }

        /** @var Body $responseBody */
        $responseBody = $responseInfo->getComponentByClassName(Body::class);

        // Retrieve the string payload of the stream.
        if (empty($responseBody) || !$responseBody->isBodyAnalyzable()) {
            // The body is truncated. Indeed, we are not working on terabyte-long bodies!
            // So we work only on what the Body component allows to.
            return;
        }

        // Data to log before the fallback.
        $data = $analyzableContent->getDataToLog();

        // Only launch on non-write methods.
        // @todo POST
        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            // Do not work on other request method than GET.
            // @todo: When needed, we will make it work for POST.
            return;
        }

        // The server may send no status code.
        // @todo : no fallback on images ressources.
        if (!empty($data['destination_response_http_status_code']) && $data['destination_response_http_status_code'] < "400") {
            // The current response is not an error. We don't need a fallback.
            return;
        }
      
        // Load configs for future use in all methods.
        $this->configs = $this->getConfigs();
 
        // Requête de recherche Elasticsearch
        // @todo : date range (see caching).
        // @todo : POST.
        $query = [
            'size' => 1,
            'query' => [
                'bool' => [
                    'must' => [
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
            '_source' => [
                'destination_response_http_status_code',
                'destination_response_headers',
                'destination_response_body',
            ],
        ];

        // Search for cached entry in elastic.
        // @TODO : issue with obfuscation task.
        $elasticJsonResponse = $this->search($query);

        if (!isset($elasticJsonResponse['hits']['hits'][0]['_source']['destination_response_body'])) {
            // The fallback is not found.
            return;
        }

        // Rebuild the response.
        $source = $elasticJsonResponse['hits']['hits'][0]['_source'];
        $this->rebuildResponse($analyzableContent, $source);

        /** @var Header $headerList */
        $headerList = $responseInfo->getComponentByClassName(Header::class);

        // Add our own debugging headers to tell the end user that this was a fallback response.
        // @todo: Ajouter une configuration pour configurer ces en-têtes de débogage.
        if (empty($data['destination_response_http_status_code'])) {
            $status_code = 'not set';
        }
        else {
            $status_code = $data['destination_response_http_status_code'];
        }      
        $headerList->addHeaderFieldEntry(
            'x-Fallback-By-WSolution',
            'Fallback by WSolution from code ' . $status_code
        );
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
        $responseBody->write($elasticSearchSource['destination_response_body'] ?? '');

        // Rebuild the headers.
        $headerList = $responseInfo->getComponentByClassName(Header::class, true);
        $headerList->removeHeaders();
        $headers = json_decode($elasticSearchSource['destination_response_headers'] ?? '[]', true);


        //print_r($headers);

        foreach ($headers as $header) {
            $headerList->addHeaderFieldEntry($header['key'], $header['value']);
            //print $header['key'] . ' : ' . $header['value'];
        }

        // Log the special WSolution status.
        /** @var HttpWebServiceStatus $httpWebServiceStatus */
        $httpWebServiceStatus = $analyzableContent->getComponentByClassName(HttpWebServiceStatus::class, true);
        $httpWebServiceStatus->setStatus($httpWebServiceStatus->getWsStatusByHttpStatus($httpStatusCode), true);

        return $response;
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
}
