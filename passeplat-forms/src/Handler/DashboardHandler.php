<?php

namespace PassePlat\Forms\Handler;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Vue\Response;
use PassePlat\Core\Exception\Exception;
use PassePlat\Logger\Elastic\Definitions\AnalyzableContentIndexDef;
use PassePlat\Logger\Elastic\Definitions\ErrorsIndexDef;
use PassePlat\Logger\Elastic\Service\ElasticsearchService;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides methods for performing specific operations related to dashboard page.
 */
class DashboardHandler extends Handler
{
    /**
     * A backend copy of the frontend YAML.
     */
    const SERVER_YAML_FILE = './../passeplat-forms/blueprint/dashboard.yaml';

    const MAX_BUCKETS = 65536; // Elasticsearch limit

    /**
     * Constructs an Elasticsearch query to retrieve the response history
     * within a specified time range for a given web service.
     *
     * The query aggregates response statuses over time using a date histogram.
     * This allows tracking of response status trends over the selected period.
     *
     * @param string $webServiceId
     *   The unique identifier of the web service.
     * @param string $endDate
     *   The end date of the query range, in timestamp format.
     * @param string $startDate
     *   The start date of the query range, in timestamp format.
     * @param array $interval
     *   An array defining the aggregation interval, where:
     *   - `$interval[0]` is the interval value (e.g., '1h', '1d').
     *   - `$interval[1]` specifies whether the interval is 'fixed' or 'calendar' .
     *
     * @return array
     *   The Elasticsearch query structure for retrieving response history.
     */
    protected function buildHistoryQuery(
        string $webServiceId,
        string $endDate,
        string $startDate,
        array $interval
    ): array {
        return [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'range' => [
                                'destination_response_start_time' => [
                                    'gte' => $startDate,
                                    'lt' => $endDate,
                                ],
                            ],
                        ],
                        [
                            'query_string' => [
                                'query' => $webServiceId,
                                'default_field' => 'passeplat_wsid',
                            ],
                        ],
                    ],
                ],
            ],
            'aggs' => [
                'history' => [
                    'date_histogram' => [
                        'field' => 'destination_response_start_time',
                        $interval[1] .'_interval' => $interval[0],
                        'format' => 'yyyy-MM-dd HH:mm:ss',
                        // Todo: Fix the time zone in the future.
                        'time_zone' => 'UTC',
                    ],
                    'aggs' => [
                        'status' => [
                            'terms' => [
                                'field' => 'web_service_status',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds an Elasticsearch query to retrieve performance metrics
     * for a specific web service within a given time range.
     *
     * The query aggregates response times over time using a date histogram.
     *
     * @param string $webServiceId
     *   The unique identifier of the web service.
     * @param string $endTime
     *   The end timestamp for the query range.
     * @param string $startTime
     *   The start timestamp for the query range.
     * @param array $interval
     *   An array defining the aggregation interval, where:
     *   - `$interval[0]` is the interval value (e.g., '1h', '1d').
     *   - `$interval[1]` specifies whether the interval is 'fixed' or 'calendar'.
     *
     * @return array
     *   The Elasticsearch query structure for retrieving performance data.
     */
    protected function buildPerformanceQuery(
        string $webServiceId,
        string $endTime,
        string $startTime,
        array $interval
    ): array {
        return [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'range' => [
                                'destination_response_start_time' => [
                                    'gte' => $startTime,
                                    'lt' => $endTime
                                ]
                            ]
                        ],
                        [
                            'query_string' => [
                                'query' => $webServiceId,
                                'default_field' => 'passeplat_wsid'
                            ]
                        ],
                    ],
                ],
            ],
            'aggregations' => [
                'performance' => [
                    'date_histogram' => [
                        'field' => 'destination_response_start_time',
                        $interval[1] .'_interval' => $interval[0],
                        'format' => 'yyyy-MM-dd HH:mm:ss',
                        'time_zone' => 'UTC',
                    ],
                    'aggregations' => [
                        'wait' => [
                            'avg' => [
                                'field' => 'app_execution_until_stop_duration'
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds the Elasticsearch query.
     *
     * @param string $webServiceId
     *   The unique identifier for the web service.
     * @param string $endDate
     *   The end date for the query range in timestamp format.
     * @param string $startDate
     *   The start date for the query range in timestamp format.
     *
     * @return array
     *   Elasticsearch query.
     */
    protected function buildQuery(string $webServiceId, string $endDate, string $startDate): array
    {
        return [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'error_type' => 'openapi_validation_error',
                            ],
                        ],
                        [
                            'term' => [
                                'webServiceId' => $webServiceId,
                            ],
                        ],
                        [
                            'range' => [
                                'timestamp' => [
                                    'gte' => $startDate,
                                    'lte' => $endDate,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'aggs' => [
                'request_response_count' => [
                    'terms' => [
                        'field' => 'type',
                    ]
                ],
                'category_count' => [
                    'terms' => [
                        'field' => 'category',
                    ]
                ],
                'item_count' => [
                    'terms' => [
                        'field' => 'item',
                    ]
                ]
            ]
        ];
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
                    $data['wsid'] = $_GET['wsid'];
                } else {
                    throw new Exception('empty wsid');
                }
            } else {
                // The user clicked on the button.
                $data = $clientInput['data'];
            }

            $endDate = !empty($data['endDateFilter']) ? strtotime($data['endDateFilter']) : time();
            $startDate = !empty($data['startDateFilter']) ? strtotime($data['startDateFilter']) : 0;

            $query = $this->buildQuery($data['wsid'], $endDate, $startDate);

            /** @var ElasticsearchService $elasticSearchServer */
            $elasticSearchService = ComponentBasedObject::getRootComponentByClassName(
                ElasticsearchService::class,
                true
            );

            /** @var ErrorsIndexDef $AnalyzableContentIndex */
            $errorsIndexDef = ComponentBasedObject::getRootComponentByClassName(ErrorsIndexDef::class, true);

            $analyzableContentIndexDef = ComponentBasedObject::getRootComponentByClassName(
                AnalyzableContentIndexDef::class,
                true
            );

            $response = $elasticSearchService->search($errorsIndexDef, $query);

            $stats = $this->getBucketCount($response['aggregations']);

            $data['totalValidationErrors'] = $stats['totalErrors'];

            $data['errorTypesChart']['datasets'][0]['data'] = [
                $stats['requestErrors'],
                $stats['responseErrors'],
            ];

            $data['errorCategoriesChart']['datasets'][0]['data'] = [
                $stats['parsingErrors'],
                $stats['schemaErrors'],
                $stats['valueErrors'],
            ];

            $data['errorItemsChart']['datasets'][0]['data'] = [
                $stats['bodyErrors'],
                $stats['cookiesErrors'],
                $stats['headersErrors'],
                $stats['pathErrors'],
                $stats['queryErrors'],
                $stats['securityErrors'],
            ];

            $interval = $this->getOptimalInterval($startDate, $endDate);

            try {
                // History Lines:
                $historyQuery = $this->buildHistoryQuery($data['wsid'], $endDate, $startDate, $interval);

                $performanceResponse = $elasticSearchService->search($analyzableContentIndexDef, $historyQuery);

                $this->getHistoryData($performanceResponse['aggregations'], $data['historyChart']);
            } catch (\Exception $e) {
                $data['historyChart'] = '';
            }

            try {
                // Performance Lines:
                $performanceQuery = $this->buildPerformanceQuery(
                    $data['wsid'],
                    $endDate,
                    $startDate,
                    $interval
                );

                $performanceResponse = $elasticSearchService->search($analyzableContentIndexDef, $performanceQuery);

                $this->getPerformanceData($performanceResponse['aggregations'], $data['performanceChart']);
            } catch (\Exception $e) {
                $data['performanceChart'] = '';
            }

            $output = $fullYamlServer;
            $output['data'] = $data;

            Response::sendOk($output);
        } catch (\Exception $e) {
            Response::sendInternalServerError();
        }
    }

    /**
     * Processes Elasticsearch aggregation data to extract counts for various error categories.
     *
     * @param array $aggregations
     *   The aggregation results obtained from Elasticsearch.
     *
     * @return array
     *   An associative array with the total error count and counts for various error categories:
     *   'requestErrors', 'responseErrors', 'parsingErrors', etc.
     */
    protected function getBucketCount(array $aggregations): array
    {
        $result = [
            'totalErrors' => 0,
            'requestErrors' => 0,
            'responseErrors' => 0,
            'parsingErrors' => 0,
            'schemaErrors' => 0,
            'valueErrors' => 0,
            'bodyErrors' => 0,
            'cookiesErrors' => 0,
            'headersErrors' => 0,
            'pathErrors' => 0,
            'queryErrors' => 0,
            'securityErrors' => 0,
        ];

        $buckets = $aggregations['request_response_count']['buckets'];

        foreach ($buckets as $bucket) {
            $result['totalErrors'] += $bucket['doc_count'];

            if ($bucket['key'] === 'Request') {
                $result['requestErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Response') {
                $result['responseErrors'] = $bucket['doc_count'];
            }
        }

        $buckets = $aggregations['category_count']['buckets'];

        foreach ($buckets as $bucket) {
            if ($bucket['key'] === 'Parsing') {
                $result['parsingErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Schema') {
                $result['schemaErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Value') {
                $result['valueErrors'] = $bucket['doc_count'];
            }
        }

        $buckets = $aggregations['item_count']['buckets'];

        foreach ($buckets as $bucket) {
            if ($bucket['key'] === 'Body') {
                $result['bodyErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Cookies') {
                $result['cookiesErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Headers') {
                $result['headersErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Path') {
                $result['pathErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Query') {
                $result['queryErrors'] = $bucket['doc_count'];
            } elseif ($bucket['key'] === 'Security') {
                $result['securityErrors'] = $bucket['doc_count'];
            }
        }

        return $result;
    }

    /**
     * Populates the history chart with response status counts from Elasticsearch aggregations.
     *
     * @param array $aggregations
     *   The aggregation results returned by Elasticsearch.
     * @param array $historyChart
     *   Reference to the chart data structure.
     */
    protected function getHistoryData(array $aggregations, array &$historyChart)
    {
        $mapping = [
            '2XX' => 0,
            '2WS' => 1,
            '3XX' => 2,
            '3WS' => 3,
            '4XX' => 4,
            '4WS' => 5,
            '5XX' => 6,
        ];

        $i = -1;
        $historyChart['labels'] = [];
        foreach ($aggregations['history']['buckets'] as $bucket) {
            $i = $i + 1;

            $historyChart['labels'][] = $bucket['key_as_string'];

            // Initialize all data to zero.
            for ($j = 0; $j <= 6; $j++) {
                $historyChart['datasets'][$j]['data'][$i] = 0;
            }

            foreach ($bucket['status']['buckets'] as $statusBucket) {
                $statusCode = (string) $statusBucket['key'];
                $count = $statusBucket['doc_count'];
                $historyChart['datasets'][$mapping[$statusCode]]['data'][$i] = $count;
            }
        }
    }

    /**
     * Dynamically determines the interval for the date_histogram aggregation.
     *
     * @param int $startDate
     *   Start timestamp.
     * @param int $endDate
     *   End timestamp.
     *
     * @return array
     *   Optimal interval for the aggregation.
     */
    protected function getOptimalInterval(int $startDate, int $endDate): array
    {
        $duration = $endDate - $startDate;

        // Possible intervals in ascending order
        $intervals = [
            ['1s', 'fixed', 1],
            ['30s', 'fixed', 30],
            ['1m', 'fixed', 60],
            ['10m', 'fixed', 600],
            ['30m', 'fixed', 1800],
            ['1h', 'fixed', 3600],
            ['3h', 'fixed', 10800],
            ['6h', 'fixed', 21600],
            ['12h', 'fixed', 43200],
            ['day', 'calendar', 86400,],
            ['week', 'calendar', 604800,],
            ['month', 'calendar', 2629800,],
            ['year', 'calendar', 31557600,],
        ];

        foreach ($intervals as $interval) {
            if ($duration / $interval[2] <= static::MAX_BUCKETS) {
                return [$interval[0], $interval[1]];
            }
        }

        // Fallback to yearly interval if nothing fits.
        return ['1y', 'calendar'];
    }

    /**
     * Processes performance data from Elasticsearch aggregations and updates the performance chart structure.
     *
     * The method extracts time labels and response time values
     * from the aggregation buckets and formats them for visualization.
     *
     * @param array $aggregations
     *   The Elasticsearch aggregation results containing performance data.
     * @param array &$performanceChart
     *   The reference to the performance chart array, which will be populated
     *   with labels (timestamps) and dataset values (response times).
     */
    protected function getPerformanceData(array $aggregations, array &$performanceChart)
    {
        $i = -1;
        $performanceChart['labels'] = [];

        foreach ($aggregations['performance']['buckets'] as $bucket) {
            $i++;

            $performanceChart['labels'][] = $bucket['key_as_string'];
            $performanceChart['datasets'][0]['data'][$i] = $bucket['wait']['value'] ?? 0;
        }
    }
}
