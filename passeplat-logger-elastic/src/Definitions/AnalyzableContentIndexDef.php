<?php

namespace PassePlat\Logger\Elastic\Definitions;

use PassePlat\Logger\Elastic\Exception\ElasticsearchException;

/**
 * This class extends the IndexDefinitionBase class
 * to provide specific mappings and parameters for the analyzable content log index.
 */
class AnalyzableContentIndexDef extends IndexDefinitionBase
{
    protected static string $unprefixedIndexId = 'analyzable_content';

    public static function getExpectedMapping(): array
    {
        return [
            'app_execution_until_stop_duration' => [
                'type' => 'integer',
            ],
            'destination_response_body' => [
                'type' => 'wildcard',
            ],
            'destination_response_body_length' => [
                'type' => 'integer',
            ],
            'destination_response_headers' => [
                'type' => 'wildcard',
            ],
            'destination_response_http_status_code' => [
                'type' => 'integer',
            ],
            'destination_response_start_time' => [
                'type' => 'date',
                'format' => 'strict_date_optional_time||epoch_second',
            ],
            'destination_response_start_to_stop_duration' => [
                'type' => 'integer',
            ],
            'destination_response_step_started_receiving_time' => [
                'type' => 'date',
                'format' => 'strict_date_optional_time||epoch_second',
            ],
            'destination_response_stop_time' => [
                'type' => 'date',
                'format' => 'strict_date_optional_time||epoch_second',
            ],
            'destination_response_truncated_body' => [
                'type' => 'wildcard',
            ],
            'destination_response_wait_duration' => [
                'type' => 'integer',
            ],
            'destination_round_trip_duration' => [
                'type' => 'integer',
            ],
            'destination_url' => [
                'type' => 'keyword',
            ],
            'destination_uri_scheme' => [
                'type' => 'keyword'
            ],
            'initiator_addr' => [
                'type' => 'keyword',
            ],
            'initiator_port' => [
                'type' => 'integer',
            ],
            'initiator_request_body' => [
                'type' => 'wildcard',
            ],
            'initiator_request_body_length' => [
                'type' => 'integer',
            ],
            'initiator_request_truncated_body' => [
                'type' => 'wildcard',
            ],
            'initiator_request_headers' => [
                'type' => 'wildcard',
            ],
            'http_method' => [
                'type' => 'keyword',
            ],
            'passeplat_uid' => [
                'type' => 'keyword',
            ],
            'passeplat_wsid' => [
                'type' => 'keyword',
            ],
            'web_service_status' => [
                'type' => 'keyword',
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
                'settings' => [
                    'number_of_shards' => 2,
                    'number_of_replicas' => 1,
                ],
            ],
        ];
    }
}
