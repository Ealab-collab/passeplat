<?php

namespace PassePlat\Forms\Handler;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\Tool\DateTime;
use PassePlat\Forms\Exception\BadRequestException;
use PassePlat\Forms\Exception\WebServiceException;
use PassePlat\Forms\Vue\Response;
use PassePlat\Core\Exception\Exception;
use PassePlat\Logger\Elastic\Definitions\AnalyzableContentIndexDef;
use PassePlat\Logger\Elastic\Definitions\ErrorsIndexDef;
use PassePlat\Logger\Elastic\Exception\ElasticsearchException;
use PassePlat\Logger\Elastic\Service\ElasticsearchService;
use Symfony\Component\Yaml\Yaml;

class WebServicesList extends Handler
{
    private const SERVER_YAML_FILE = './../passeplat-forms/blueprint/list.yaml';

    private const WEBSERVICES_DIRECTORY = '../config/app/webservice/';

    private array $webservices = [];

    /**
     * Builds the Elasticsearch query.
     *
     * @param string $webservice_id
     *   The id of the webservice.
     *
     * @return array
     *   The constructed Elasticsearch query.
     */
    private function buildQuery(string $webservice_id): array
    {
        return [
            'size' => 0,
            'query' => [
                'term' => [
                    'passeplat_wsid' => $webservice_id,
                ],
            ],
            'aggs' => [
                'total_logs' => [
                    'value_count' => [
                        'field' => '_id',
                    ],
                ],
                'last_call_date' => [
                    'max' => [
                        'field' => 'destination_response_start_time',
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds the Elasticsearch query for checking validation errors.
     *
     * @param string $webService
     *   The webservice ID.
     *
     * @return array
     *   The constructed Elasticsearch query.
     */
    private function buildQueryForCheck(string $webService): array
    {
        return [
            'size' => 0,
            'query' => [
                'term' => [
                    'webServiceId' => $webService,
                ],
            ],
            'aggs' => [
                'total_errors' => [
                    'value_count' => [
                        'field' => '_id',
                    ],
                ],
            ],
        ];
    }

    /**
     * Checks if the given web service has validation errors.
     *
     * @param string $webService
     *   The webservice ID.
     *
     * @return int
     *   The number of validation errors found.
     *
     * @throws UnmetDependencyException|ElasticsearchException
     */
    private function checkIfHasErrorValidation(string $webService): int
    {
        $query = $this->buildQueryForCheck($webService);

        /** @var ElasticsearchService $elasticSearchServer */
        $elasticSearchService = ComponentBasedObject::getRootComponentByClassName(ElasticsearchService::class, true);

        /** @var ErrorsIndexDef $errorIndex */
        $errorIndex = ComponentBasedObject::getRootComponentByClassName(ErrorsIndexDef::class, true);

        $response = $elasticSearchService->search($errorIndex, $query);

        $errors = $response['aggregations']['total_errors']['value'];

        return !empty($errors) ? $errors : 0;
    }

    /**
     * Checks specific features of a web service like logs, security, alert.
     *
     * @param $webService
     *   The webservice to check.
     *
     * @return array
     *   Array indicating if 'Alert', 'ElasticsearchLogger', and 'Obfuscation' are present in the tasks.
     *
     * @throws BadRequestException
     * @throws WebServiceException
     */
    private function checkWebServiceFeature($webService): array
    {
        $tasks = $this->getWebServiceTasks($webService);

        return [
            'alert' => in_array('Alert', $tasks),
            'logs' => in_array('ElasticsearchLogger', $tasks),
            'security' => in_array('Obfuscation', $tasks),
        ];
    }

    /**
     * Retrieves aggregated bucket information from Elasticsearch.
     *
     * @param array $aggregations
     *   The Elasticsearch aggregations.
     *
     * @return array
     *   Array with the last call date and the number of logs.
     */
    private function extractBucketMetrics(array $aggregations): array
    {
        $lastCall = $aggregations['last_call_date']['value'];

        if (empty($lastCall)) {
            $lastCall = 0;
        }

        return [
            'lastCall' => DateTime::convertTimestampToString($lastCall),
            'nbOfLogs' => $aggregations['total_logs']['value'],
        ];
    }

    /**
     * Retrieves information from Elasticsearch for a given web service.
     *
     * @param string $webservice
     *   The web service ID.
     *
     * @return array
     *   Array containing the number of logs and last call date for the web service.
     *
     * @throws UnmetDependencyException|ElasticsearchException
     */
    private function getInformationFromES(string $webservice): array
    {
        $query = $this->buildQuery($webservice);

        /** @var ElasticsearchService $elasticSearchServer */
        $elasticSearchService = ComponentBasedObject::getRootComponentByClassName(ElasticsearchService::class, true);

        /** @var AnalyzableContentIndexDef $analyzableIndex */
        $analyzableIndex = ComponentBasedObject::getRootComponentByClassName(AnalyzableContentIndexDef::class, true);

        $response = $elasticSearchService->search($analyzableIndex, $query);

        return ($this->extractBucketMetrics($response['aggregations']));
    }

    /**
     * Retrieves the list of webservices.
     *
     * @return array
     *   The webservices list.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    private function getWebServices(): array
    {
        if (empty($this->webservices)) {
            $this->updateWebServicesList();
        }

        return $this->webservices;
    }

    /**
     * Retrieves the tasks of a specified web service.
     *
     * @param string $webserviceName
     *   The name of the web service.
     *
     * @return array
     *   Array of task class names for the specified web service.
     *
     * @throws WebServiceException
     * @throws BadRequestException
     */
    public function getWebServiceTasks(string $webserviceName): array
    {
        $webservice = $this->getWebServices()[$webserviceName] ?? null;

        if (empty($webservice)) {
            throw new BadRequestException('WebService does not exist');
        }

        $res = [];

        foreach ($webservice['tasks'] as $tasksByEvent) {
            if (empty($tasksByEvent)) {
                continue;
            }

            foreach ($tasksByEvent as $task) {
                [$taskName,] = explode('_', $task['class'], 2);
                $pathParts = explode('\\', $taskName);
                $nameClassTask = end($pathParts);
                $res[] = $nameClassTask;
            }
        }

        return $res;
    }

    public function handlePostRequest(): void
    {
        try {
            $result = static::load(static::SERVER_YAML_FILE);
            $this->setItems($result);
            Response::sendOk($result);
        } catch (\Exception $e) {
            Response::sendInternalServerError();
        }
    }

    /**
     * Retrieves the list of all webservices.
     *
     * @return array
     *   An array of webservice names.
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    public function listWebServices(): array
    {
        $webservices = $this->getWebServices();

        // Unset unwanted entries.
        unset($webservices['default']);

        return array_keys($webservices);
    }

    /**
     * Loads and parses a YAML file.
     *
     * @param string $filePath
     *   The path to the YAML file.
     *
     * @return array
     *   The parsed YAML content.
     *
     * @throws Exception
     *   If the file cannot be loaded or parsed.
     */
    public static function load(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new Exception("YAML file not found: $filePath");
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new Exception("Failed to read YAML file: $filePath");
        }

        return Yaml::parse($content) ?? [];
    }

    /**
     * Sets the items to be displayed based on web service data.
     *
     * @param array $result
     *   Reference to the result array to populate.
     *
     * @throws Exception|UnmetDependencyException|WebServiceException
     */
    private function setItems(array &$result)
    {
        $listWebServices = $this->listWebServices();

        foreach ($listWebServices as $webService) {
            $information = $this->getInformationFromES($webService);
            $result['data']['results'][] = [
                'results_item' => [
                        'title' => $this->webservices[$webService]['name'] ?? 'undefined title',
                        'domain' => $this->webservices[$webService]['domain'] ?? 'undefined domain',
                        'url' => $webService,
                        'nbOfLogs' => $information['nbOfLogs'] ?? 0,
                        'lastCall' => $information['lastCall'] ?? 0,
                        'edit' => '/build/webservice-edit.php?wsid=' . $webService,
                        'nbOfErrors' => $this->checkIfHasErrorValidation($webService),
                        'errorsPage' => '/build/errors.php?wsid=' . $webService,
                        'dashboard' => '/build/dashboard.php?wsid=' . $webService,
                        'logsPage' => '/build/logs.php?wsid=' . $webService,
                        'copy' => $webService,
                    ] + $this->checkWebServiceFeature($webService)
            ];
        }
    }

    /**
     * Update the list of webservices directly from the files of config directory.
     *
     * @return void
     *
     * @throws WebServiceException
     *   Thrown if there are any issues encountered at the PassePlat webservice level.
     */
    private function updateWebServicesList(): void
    {
        $yamlFiles = glob(static::WEBSERVICES_DIRECTORY . "/*.yaml");

        unset($this->webservices);

        foreach ($yamlFiles as $yamlFile) {
            $webserviceContent = file_get_contents($yamlFile);

            if ($webserviceContent === false) {
                throw new WebServiceException('Impossible to load the web service.');
            }

            $parsedWebService = Yaml::parse($webserviceContent);

            $wsid = $parsedWebService['wsid'];

            $this->webservices[$wsid] = $parsedWebService;
        }
    }
}
