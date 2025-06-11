<?php

namespace PassePlat\Forms\Handler;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Exception\BadRequestException;
use PassePlat\Forms\Vue\Response;
use PassePlat\Core\Exception\Exception;
use PassePlat\Core\Tool\DateTime;
use PassePlat\Logger\Elastic\Definitions\AnalyzableContentIndexDef;
use PassePlat\Logger\Elastic\Service\ElasticsearchService;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides methods for performing specific operations related to logs page.
 */
class LogsHandler extends Handler
{
    /**
     * The maximum allowable number of results per page for pagination.
     */
    const MAX_PAGINATION_SIZE = 10000;

    /**
     * Path to the backend YAML configuration file.
     */
    const SERVER_YAML_FILE = './../passeplat-forms/blueprint/logs.yaml';

    /**
     * Builds an Elasticsearch query using the provided filters and pagination parameters.
     *
     * @param array $filters
     *   Associative array containing the filters (e.g., webServiceId, userId).
     * @param int $from
     *   The starting point for pagination (offset).
     * @param int $size
     *   The number of results per page.
     *
     * @return array
     *   The constructed Elasticsearch query.
     *
     * @throws BadRequestException
     *   If required filters are missing.
     */
    private function buildQuery(array $filters, int $from, int $size): array
    {
        // Base structure of the Elasticsearch query.
        $query = [
            '_source' => [
                '_id',
                'app_execution_until_stop_duration',
                'destination_round_trip_duration',
                'destination_response_start_time',
                'http_method',
                'destination_url',
                'destination_response_http_status_code',
                'initiator_request_headers',
                'initiator_request_body',
                'destination_response_headers',
                'destination_response_body',
                'passeplat_wsid',
                'passeplat_uid',
            ],

            'size' => $size,
            'from' => $from,

            'query' => [
                'bool' => [
                    'filter' => []
                ]
            ],

            'sort' => [
                "destination_response_start_time" => [
                    "order" => 'DESC',
                ],
            ],
        ];

        // Ensure the passeplat_wsid filter is provided.
        if (empty($filters['passeplat_wsid'])) {
            throw new BadRequestException('Empty passeplat_wsid');
        }

        $query['query']['bool']['filter'][] = [
            'term' => [
                'passeplat_wsid' => $filters['passeplat_wsid'],
            ],
        ];

        // TODO: Add the user ID.
        // Verifying passeplat_uid enhances security by preventing unauthorized access to another user's logs.

        /*
        if (empty($filters['passeplat_uid'])) {
            throw new BadRequestException('Empty passeplat_uid');
        }

        $query['query']['bool']['filter'][] = [
            'term' => [
                'passeplat_uid' => $filters['passeplat_uid'],
            ],
        ];
        */

        // Optional date range filters.
        if (!empty($filters['startDate'])) {
            $query['query']['bool']['filter'][] = [
                'range' => [
                    'destination_response_start_time' => [
                        'gte' => $filters['startDate'],
                    ],
                ],
            ];
        }

        if (!empty($filters['endDate'])) {
            $query['query']['bool']['filter'][] = [
                'range' => [
                    'destination_response_start_time' => [
                        'lte' => $filters['endDate'],
                    ],
                ],
            ];
        }

        // Optional status filter.
        if (!empty($filters['status'])) {
            $query['query']['bool']['filter'][] = [
                'match' => [
                    'web_service_status' => $filters['status'],
                ],
            ];
        }

        // Optional text search filter.
        if (!empty($filters['text'])) {
            $escapedText = preg_quote($filters['text'], '/');
            $cleanedText = preg_replace('/\s+/', ' ', trim($escapedText));
            $words = explode(' ', $cleanedText);

            foreach ($words as $word) {
                $query['query']['bool']['filter'][] = [
                    'query_string' => [
                        'query' => '*' . $word . '*',
                        'fields' => ["*"],
                    ],
                ];
            }
        }

        return $query;
    }

    /**
     * Calculates the difference between the app execution duration and round-trip duration.
     *
     * @param array $params
     *   The parameters containing both durations.
     *
     * @return string
     *   The calculated duration in milliseconds or 'N/A' if any of the values are missing.
     */
    private function getAppExecutionDuration(array $params = []): string
    {
        $app_execution_until_stop_duration = $params['app_execution_until_stop_duration'] ?? null;
        $destination_round_trip_duration = $params['destination_round_trip_duration'] ?? null;

        if (empty($app_execution_until_stop_duration) || empty($destination_round_trip_duration)) {
            return 'N/A';
        }

        return round(($app_execution_until_stop_duration - $destination_round_trip_duration) / 1000) . ' ms';
    }

    /**
     * Gets the destination round-trip duration in milliseconds.
     *
     * @param int|null $destination_round_trip_duration
     *   The round-trip duration in milliseconds.
     *
     * @return string
     *   The formatted duration or 'N/A' if not provided.
     */
    private function getExecutionDuration(int $destination_round_trip_duration = null): string
    {
        if (!empty($destination_round_trip_duration)) {
            return round($destination_round_trip_duration / 1000) . ' ms';
        }
        return 'N/A';
    }

    public function handlePostRequest(): void
    {
        try {
            $absoluteYamlFilePath = realpath(static::SERVER_YAML_FILE);

            if (!$absoluteYamlFilePath) {
                throw new \Exception('Invalid YAML file path.');
            }

            $fullYamlServer = Yaml::parse(file_get_contents($absoluteYamlFilePath));

            $clientInput = json_decode(file_get_contents('php://input'), true);

            $data = $fullYamlServer['data'];

            if (empty($clientInput)) {
                if (!empty($_GET['wsid'])) {
                    $data['filters']['passeplat_wsid'] = $_GET['wsid'];
                } else {
                    throw new Exception('Missing wsid');
                }
            } else {
                // The user clicked on the search button.
                $data = $clientInput['data'];
            }

            // Set the filters, pagination, and sort parameters.
            $filters = $data['filters'];

            $size = $data['size'] ?? static::MAX_PAGINATION_SIZE;
            $from = $data['from'] ?? 0;

            // Build the Elasticsearch query with the provided filters and sorts.
            $query = $this->buildQuery($filters, $from, $size);

            /** @var ElasticsearchService $elasticSearchServer */
            $elasticSearchService = ComponentBasedObject::getRootComponentByClassName(
                ElasticsearchService::class,
                true
            );

            /** @var AnalyzableContentIndexDef $analyzableContentIndex */
            $analyzableContentIndex = ComponentBasedObject::getRootComponentByClassName(
                AnalyzableContentIndexDef::class,
                true
            );

            $response = $elasticSearchService->search($analyzableContentIndex, $query);

            $results = $this->parseElasticSearchResponse($response);

            $output = $fullYamlServer;
            $output['data'] = $data;
            $output['data']['results'] = $results;

            Response::sendOk($output);
        } catch (\Exception $e) {
            Response::sendInternalServerError();
        }
    }

    /**
     * Parses the Elasticsearch response to extract relevant error details.
     *
     * @param array $response
     *   The Elasticsearch response containing aggregation results.
     *
     * @return array
     *   An array of formatted results including error details.
     *
     * @throws \DateMalformedStringException
     */
    public function parseElasticSearchResponse(array $response): array
    {
        $nb = $response['hits']['total']['value'];
        $entries = [];

        foreach ($response['hits']['hits'] as $hit) {
            $created = DateTime::getFormatedDate($hit['_source']['destination_response_start_time']);
            $destination_round_trip_duration = $hit['_source']['destination_round_trip_duration'] ?? null;
            $app_execution_until_stop_duration = $hit['_source']['app_execution_until_stop_duration'];

            $entries[] = [
                'results_item' => [
                    'id' => $hit['_id'],
                    'httpMethod' => $hit['_source']['http_method'],
                    'created' => $created,
                    'destinationURL' => $hit['_source']['destination_url'],
                    'executionDuration' => $this->getExecutionDuration($destination_round_trip_duration),
                    'appExecutionDuration' => $this->getAppExecutionDuration([
                        'app_execution_until_stop_duration' => $app_execution_until_stop_duration,
                        'destination_round_trip_duration' => $destination_round_trip_duration,
                    ]),
                    'httpStatus' => $hit['_source']['destination_response_http_status_code'],
                    'initiator_request_headers' => '',
                    'destination_response_headers' => '',
                    'destination_response_body' => '',
                ],
            ];
        }

        return [
            'nb' => $nb . ' ' . 'results',
            'entries' => $entries,
        ];
    }
}
