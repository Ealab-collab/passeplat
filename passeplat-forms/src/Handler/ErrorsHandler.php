<?php

namespace PassePlat\Forms\Handler;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Core\Tool\DateTime;
use PassePlat\Forms\Vue\Response;
use PassePlat\Core\Exception\Exception;
use PassePlat\Logger\Elastic\Definitions\ErrorsIndexDef;
use PassePlat\Logger\Elastic\Service\ElasticsearchService;
use Symfony\Component\Yaml\Yaml;

class ErrorsHandler extends Handler
{
    /**
     * The maximum allowable number of buckets.
     */
    const MAX_PAGINATION_SIZE = 65536;

    /**
     * The maximum number of distinct paths to retrieve for ergonomic reasons (select options).
     */
    const MAX_PATHS = 20;

    /**
     * A backend copy of the frontend YAML.
     */
    const SERVER_YAML_FILE = './../passeplat-forms/blueprint/errors.yaml';

    /**
     * Adds optional filters to the Elasticsearch query.
     *
     * @param array $query
     *   Reference to the Elasticsearch query.
     * @param array $filters
     *   Optional filters to apply.
     *
     * @throws \Exception
     */
    private function addOptionalFilters(array &$query, array $filters)
    {
        $optionalFields = ['type', 'category', 'item', 'path'];

        foreach ($optionalFields as $optional) {
            if (!empty($filters[$optional]) && $filters[$optional] !== 'All') {
                $query['query']['bool']['must'][] = [
                    'term' => [
                        $optional => $filters[$optional],
                    ]
                ];
            }
        }

        // Add date range filter if provided.
        if (!empty($filters['startDate'])) {
            $query['query']['bool']['must'][] = [
                'range' => [
                    'timestamp' => [
                        'gte' => DateTime::convertDateToTimestamp($filters['startDate']),
                    ],
                ],
            ];
        }

        if (!empty($filters['endDate'])) {
            $query['query']['bool']['must'][] = [
                'range' => [
                    'timestamp' => [
                        'lte' => DateTime::convertDateToTimestamp($filters['endDate']),
                    ],
                ],
            ];
        }
    }

    /**
     * Adds required filters to the Elasticsearch query.
     *
     * @param array $query
     *   Reference to the Elasticsearch query.
     * @param array $filters
     *   Required filters to apply.
     *
     * @throws \Exception
     */
    private function addRequiredFilters(array &$query, array $filters)
    {
        // Todo add usedId.
        $requiredFields = [
            'webServiceId',
            //'userId',
        ];

        $query['query']['bool']['must'][] = [
            'term' => [
                'error_type' => 'openapi_validation_error',
            ],
        ];

        foreach ($requiredFields as $required) {
            if (empty($filters[$required])) {
                throw new \Exception("Missing required filter: $required");
            }
            $query['query']['bool']['must'][] = [
                'term' => [
                    $required => $filters[$required]
                ]
            ];
        }
    }

    /**
     * Adds sorting to the Elasticsearch query.
     *
     * @param array $query
     *   Reference to the Elasticsearch query.
     * @param array|null $sorts
     *   Optional sorting fields.
     */
    private function addSorting(array &$query, ?array $sorts = [])
    {
        // Adding dynamic sorting based on the $sorts parameter.
        if (!empty($sorts) && is_array($sorts)) {
            $sortFields = [];
            $hasLastDate = false;

            foreach ($sorts as $key => $field) {
                if (!in_array($key, ['primary', 'secondary'])) {
                    continue;
                }

                if (!in_array($field, ['occurrenceCount', 'lastDate', 'severityLevel'])) {
                    continue;
                }

                $sortFields[] = [
                    $field => [
                        'order' => 'desc',
                    ],
                ];

                if ($field === 'lastDate') {
                    $hasLastDate = true;
                }
            }

            // If lastDate is not in the sorting criteria, we add it last.
            if (!$hasLastDate) {
                $sortFields[] = [
                    'lastDate' => [
                        'order' => 'desc',
                    ],
                ];
            }

            $query['aggs']['group_by_error']['aggs']['sorted_by'] = [
                'bucket_sort' => [
                    'sort' => $sortFields,
                ],
            ];
        } else {
            // Default sorting by occurrenceCount and lastDate if $sorts is empty or invalid.
            $query['aggs']['group_by_error']['aggs']['sorted_by'] = [
                'bucket_sort' => [
                    'sort' => [
                        [
                            'occurrenceCount' => [
                                'order' => 'desc',
                            ],
                        ],
                        [
                            'lastDate' => [
                                'order' => 'desc',
                            ],
                        ],
                    ],
                ],
            ];
        }
    }

    /**
     * Adds text search to the Elasticsearch query.
     *
     * @param array $query
     *   Reference to the Elasticsearch query.
     * @param string $text
     *   Search text.
     */
    private function addTextSearch(array &$query, string $text)
    {
        if (empty(trim($text))) {
            return;
        }

        $words = array_filter(
            explode(' ', preg_replace('/\s+/', ' ', $text))
        );

        foreach ($words as $word) {
            $query['query']['bool']['must'][] = [
                'query_string' => [
                    'query' => '*' . $word . '*',
                    'fields' => ['*'],
                ],
            ];
        }
    }

    /**
     * Builds aggregations for the Elasticsearch query.
     *
     * @param int $size
     *   Maximum number of results per aggregation.
     *
     * @return array[]
     *   Elasticsearch aggregations.
     */
    private function buildAggregations(int $size): array
    {
        return [
            'group_by_error' => [
                'composite' => [
                    'size' => $size,
                    'sources' => [
                        [
                            'message' => [
                                'terms' => [
                                    'field' => "message",
                                ],
                            ],
                        ],
                        [
                            'code' => [
                                'terms' => [
                                    'field' => 'code',
                                ],
                            ],
                        ],
                        [
                            'category' => [
                                'terms' => [
                                    'field' => 'category',
                                ],
                            ],
                        ],
                        [
                            'item' => [
                                'terms' => [
                                    'field' => 'item',
                                ],
                            ],
                        ],
                        [
                            'type' => [
                                'terms' => [
                                    'field' => 'type',
                                ],
                            ],
                        ],
                        [
                            'severityLevel' => [
                                'terms' => [
                                    'field' => "severityLevel",
                                ],
                            ],
                        ],
                        [
                            'path' => [
                                'terms' => [
                                    'field' => 'path',
                                ],
                            ],
                        ],
                    ],
                ],
                'aggs' => [
                    'occurrenceCount' => [
                        'value_count' => [
                            'field' => 'timestamp',
                        ]
                    ],
                    'lastDate' => [
                        'max' => [
                            'field' => 'timestamp',
                        ]
                    ],
                    'severityLevel' => [
                        'max' => [
                            // Normally, they all have the same severity, so the maximum is the value itself.
                            'field' => 'severityLevel',
                        ]
                    ],
                ],
            ],
        ];
    }

    /**
     * Builds an Elasticsearch query to retrieve distinct paths.
     *
     * @return array
     *   Elasticsearch query.
     */
    protected function buildDistinctPathQuery(): array
    {
        return [
            'size' => 0,
            'aggs' => [
                'distinct_paths' => [
                    'terms' => [
                        'field' => 'path',
                        'size' => static::MAX_PATHS
                    ]
                ]
            ]
        ];
    }

    /**
     * Builds the Elasticsearch query based on provided filters and sorting.
     *
     * @param array $filters
     *   Filters for the query.
     *   An associative array containing filters like webServiceId, userId, etc.
     * @param array $sorts
     *   Sorting fields for the query.
     *   An associative array containing sorting fields and order (e.g., 'lastDate' => 'desc').
     * @param int $size
     *   Number of results per page.
     *
     * @return array
     *   Elasticsearch query.
     *
     * @throws \Exception
     */
    protected function buildQuery(array $filters, array $sorts, int $size): array
    {
        // Base structure of the Elasticsearch query.
        $query = [
            'size' => 0,
            'query' => [
                'bool' => [
                    'must' => [],
                ],
            ],
            'aggs' => $this->buildAggregations($size),
        ];

        $this->addRequiredFilters($query, $filters);
        $this->addOptionalFilters($query, $filters);
        $this->addTextSearch($query, $filters['text'] ?? '');
        $this->addSorting($query, $sorts);

        return $query;
    }

    public function handlePostRequest(): void
    {
        try {
            // Get the absolute path of the YAML file.
            $absoluteYamlFilePath = realpath(static::SERVER_YAML_FILE);

            if (!$absoluteYamlFilePath) {
                throw new \Exception('Invalid YAML file path.');
            }

            $fullYamlServer = Yaml::parse(file_get_contents($absoluteYamlFilePath));

            // Retrieve and decode the JSON input from the request body.
            // Retrieve post data.
            $clientJsonInput = file_get_contents('php://input');
            $clientInput = json_decode($clientJsonInput, true);
            $data = $fullYamlServer['data'];

            if (empty($clientInput)) {
                if (!empty($_GET['wsid'])) {
                    $data['filters']['webServiceId'] = $_GET['wsid'];
                } else {
                    throw new Exception('empty wsid');
                }
            } else {
                // The user clicked the search button
                $data = $clientInput['data'];
            }

            // Set the filters, sorts, pagination parameters (from and size), or default values if not provided.
            $filters = $data['filters'];
            $sorts = $data['sorts'] ?? [];
            $size = $data['size'] ?? static::MAX_PAGINATION_SIZE;

            // Build the Elasticsearch query with the provided filters and sorts.
            $query = $this->buildQuery($filters, $sorts, $size);

            /** @var ElasticsearchService $elasticSearchServer */
            $elasticSearchService = ComponentBasedObject::getRootComponentByClassName(
                ElasticsearchService::class,
                true
            );

            /** @var ErrorsIndexDef $errorIndex */
            $errorIndex = ComponentBasedObject::getRootComponentByClassName(ErrorsIndexDef::class, true);

            $response = $elasticSearchService->search($errorIndex, $query);

            $results = $this->parseElasticSearchResponse($response);

            $output = $fullYamlServer;
            $output['data'] = $data;
            $output['data']['results'] = $results;

            $pathsQuery = $this->buildDistinctPathQuery();
            $response = $elasticSearchService->search($errorIndex, $pathsQuery);
            $paths = $this->parseDistinctPathResponse($response);
            $output['data']['paths'] = $paths;

            Response::sendOk($output);
        } catch (\Exception $e) {
            Response::sendInternalServerError();
        }
    }

    /**
     * Parses the Elasticsearch response to extract distinct paths.
     *
     * @param array $response
     *   Elasticsearch response.
     *
     * @return array
     *   List of distinct paths.
     */
    protected function parseDistinctPathResponse(array $response): array
    {
        $paths[] = [
            'label' => 'All',
            'value' => 'All'
        ];

        $buckets = $response['aggregations']['distinct_paths']['buckets'];

        foreach ($buckets as $bucket) {
            $p = $bucket['key'];
            $paths[] = [
                'label' => $p,
                'value' => $p,
            ];
        }

        return $paths;
    }

    /**
     * Parses the Elasticsearch response to extract relevant error details.
     *
     * @param array $response
     *   Elasticsearch response containing aggregation results.
     *
     * @return array
     *   Array of results.
     */
    public function parseElasticSearchResponse(array $response): array
    {
        $results = [];
        $buckets = $response['aggregations']['group_by_error']['buckets'];

        foreach ($buckets as $bucket) {
            $results[] = [
                'results_item' => $bucket['key'] +
                    [
                        'occurrence' => $bucket['doc_count'],
                        'lastDate' => DateTime::convertTimestampToDate($bucket['lastDate']['value']),
                    ]
            ];
        }

        return $results;
    }
}
