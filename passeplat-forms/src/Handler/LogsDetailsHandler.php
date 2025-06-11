<?php

namespace PassePlat\Forms\Handler;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Forms\Vue\Response;
use PassePlat\Logger\Elastic\Definitions\AnalyzableContentIndexDef;
use PassePlat\Logger\Elastic\Service\ElasticsearchService;
use Symfony\Component\Yaml\Yaml;

/**
 * Handles the processing of detailed log information for the log page's details modal.
 */
class LogsDetailsHandler extends Handler
{
    /**
     * Path to the backend YAML file for the log page.
     */
    const SERVER_YAML_FILE = './../passeplat-forms/blueprint/logs.yaml';

    /**
     * Builds the Elasticsearch query.
     *
     * @param string $id
     *   The unique identifier for the log entry to be retrieved.
     *
     * @return array
     *   Elasticsearch query.
     *
     * @throws \Exception
     *   If the identifier is null or empty.
     */
    private function buildQuery(string $id): array
    {
        if (empty($id)) {
            throw new \Exception('The identifier cannot be null or empty.');
        }

        // Base structure of the Elasticsearch query.
        return [
            '_source' => [
                'initiator_request_headers',
                'initiator_request_body',
                'destination_response_headers',
                'destination_response_body',
            ],
            'query' => [
                'term' => [
                    '_id' => $id,
                ]
            ],
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

            $data = array_replace_recursive($fullYamlServer['data'], $clientInput['data'] ?? []);

            // Build the Elasticsearch query with the provided filters and sorts.
            $query = $this->buildQuery($data['details']['id']);

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

            $details = $this->parseElasticSearchResponse($response);

            $output = $fullYamlServer;
            $output['data'] = $data;

            $output['data']['details']['initiator_request_headers'] = $details['initiator_request_headers'];
            $output['data']['details']['initiator_request_body'] = $details['initiator_request_body'];
            $output['data']['details']['destination_response_headers'] =
                $details['destination_response_headers'];
            $output['data']['details']['destination_response_body'] = $details['destination_response_body'];

            Response::sendOk($output);
        } catch (\Exception $e) {
            Response::sendInternalServerError();
        }
    }

    /**
     * Parses the Elasticsearch response to extract relevant details for the logs.
     *
     * @param array $response
     *   The response from Elasticsearch containing log details.
     *
     * @return array
     *   An associative array containing the parsed log details.
     */
    public function parseElasticSearchResponse(array $response): array
    {
        $hit = $response['hits']['hits'][0]['_source'];

        return [
            'initiator_request_headers' => $this->parseJsonToArray($hit['initiator_request_headers']),
            'initiator_request_body' => $hit['initiator_request_body'],
            'destination_response_headers' => $this->parseJsonToArray($hit['destination_response_headers']),
            'destination_response_body' => $hit['destination_response_body'],
        ];
    }

    /**
     * Converts a JSON string into an array of header items with key-value pairs.
     *
     * @param string $json
     *   The JSON string representing the header data.
     *
     * @return array
     *   An array of parsed header items, each containing a 'key' and 'value'.
     */
    private function parseJsonToArray(string $json): array
    {
        $data = json_decode($json, true);

        $resultArray = [];

        foreach ($data as $item) {
            $resultArray[] = [
                'header_item_breakable' => [
                    'key' => $item['key'],
                    'value' => $item['value'],
                ]
            ];
        }

        return $resultArray;
    }
}
