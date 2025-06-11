<?php

namespace PassePlat\Logger\Elastic\Definitions;

use PassePlat\Logger\Elastic\Exception\ElasticsearchException;

/**
 * This class extends the IndexDefinitionBase class
 * to provide specific mappings and parameters for the errors log index.
 */
class ErrorsIndexDef extends IndexDefinitionBase
{
    protected static string $unprefixedIndexId = 'errors_log';

    public static function getExpectedMapping(): array
    {
        return [
            'transaction_id' => [
                'type' => 'keyword',
            ],
            'error_type' => [
                'type' => 'keyword',
            ],
            'userId' => [
                'type' => 'keyword',
            ],
            'webServiceId' => [
                'type' => 'keyword',
            ],
            'path' => [
                'type' => 'keyword',
                'fields' => [
                    'raw' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ]
                ]
            ],
            'timestamp' => [
                'type' => 'date',
                'format' => 'strict_date_optional_time||epoch_second',
            ],
            'code' => [
                'type' => 'integer',
            ],
            'type' => [
                'type' => 'keyword',
                'fields' => [
                    'raw' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ]
                ]
            ],
            'category' => [
                'type' => 'keyword',
                'fields' => [
                    'raw' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ]
                ]
            ],
            'item' => [
                'type' => 'keyword',
                'fields' => [
                    'raw' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ]
                ]
            ],
            'message' => [
                'type' => 'wildcard',
                'fields' => [
                    'raw' => [
                        'type' => 'text',
                        'analyzer' => 'standard'
                    ]
                ]
            ],
            'lastModifiedDateOpenAPI' => [
                'type' => 'date',
                'format' => 'strict_date_optional_time||epoch_second',
            ],
            'severityLevel' => [
                'type' => 'byte',
            ],
        ];
    }

    /**
     * Retrieves parameters required for Elasticsearch index creation.
     *
     * @return array
     *   Array containing index parameters, including mappings and settings.
     *
     * @throws ElasticsearchException
     */
    public function getParams(): array
    {
        return [
            'index' => $this->getPrefixedIndexId(),
            'body' => [
                'mappings' => [
                    'properties' => static::getExpectedMapping(),
                ],
                //TODO
                // Check the settings.
                'settings' => [
                    'number_of_shards' => 2,
                    'number_of_replicas' => 0,
                ],
            ],
        ];
    }
}
