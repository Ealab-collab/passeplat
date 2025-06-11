<?php

namespace PassePlat\Openapi\Tool\Har;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use PassePlat\Openapi\Exception\SpecGenerationFailureException;
use PassePlat\Openapi\Exception\HarFailureException;
use PassePlat\Openapi\Exception\MissingStrategyException;
use PassePlat\Openapi\Exception\RemoteServiceException;
use PassePlat\Openapi\Tool\OpenApi\OpenApiSpecHandler;

/**
 * This class represents a tool for handling HTTP Archive (HAR) format data.
 * It provides methods to add entries, convert to JSON, and export to OpenAPI format.
 */
class Har
{
    //TODO: Move this URL to a configuration file or environment variable.
    /**
     * The URL of the microservice for converting HAR format to OpenAPI format.
     */
    const HAR_TO_OPENAPI_URL = "http://har-to-openapi.emerya.biz/convert";

    /**
     * The maximum number of entries that can be stored in the HAR entries array.
     *
     * It is used to limit the size of the HAR array, but to have an unlimited number of entries,
     * simply assign a negative number to $allowedEntriesCount.
     * By default, it is set to 1.
     */
    private int $allowedEntriesCount = 1;

    /**
     * Array of HAR entries, each representing a single HTTP request/response pair.
     *
     * @var array
     */
    private array $entries = [];

    /**
     * Strategy for building HAR entries.
     *
     * @var HarEntryBuilderStrategy
     */
    private HarEntryBuilderStrategy $harEntryBuilderStrategy;

    /**
     * Adds an entry using the specified strategy.
     *
     * @param array $params
     *   The parameters for the entry.
     *
     * @throws MissingStrategyException
     *   If no adding entry strategy is set.
     */
    public function addEntry(array $params): void
    {
        if (empty($this->harEntryBuilderStrategy)) {
            throw new MissingStrategyException('No HarEntryBuilderStrategy set.');
        }

        $this->entries[] = $this->harEntryBuilderStrategy->buildEntry($params);
    }

    /**
     * Empty the HAR entries.
     */
    private function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Generates the JSON representation of the HAR entries.
     *
     * @return string
     *   The JSON representation of the entries in HAR format.
     *
     * @throws HarFailureException
     *   If unable to generate the JSON representation of the HAR entries.
     */
    public function generateHarJson(): string
    {
        // Prepare the data structure for the HAR Format.
        $harData = [
            'log' => [
                'creator' => [
                    'name' => 'PassePlat', // Your creator name
                    'version' => '0.0' // Your creator version
                ],
                'version' => '1.2',
                'entries' => $this->entries,
            ],
        ];

        // Convert the data to JSON.
        $json = json_encode($harData);

        if ($json === false) {
            throw new HarFailureException('Unable to generate JSON representation of HAR entries.');
        }

        return $json;
    }

    /**
     * Generates the OpenAPI specification from the HAR data and saves the result.
     *
     * @param OpenApiSpecHandler $openapi
     *   The OpenApiSpecHandler object to save the generated specification.
     *
     * @throws HarFailureException
     *   If there is an issue with the HAR format.
     * @throws RemoteServiceException
     *   If there is an issue with a remote microservice.
     * @throws SpecGenerationFailureException
     *   If the generation of the OpenAPI fails.
     */
    public function generateOpenAPI(OpenApiSpecHandler $openapi): void
    {
        try {
            $client = new Client();

            $response = $client->request('POST', new Uri(static::HAR_TO_OPENAPI_URL), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                // Use the generated HAR Json as the request body.
                'body' => $this->generateHarJson(),
            ]);

            // Check if there was an error during the request.
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                throw new RemoteServiceException(
                    'The remote microservice har-to-openapi returned a status code: '
                    . $response->getStatusCode()
                );
            }

            file_put_contents($openapi->getPath(), $response->getBody());
        } catch (GuzzleException $e) {
            if ($e instanceof RequestException && $e->hasResponse()) {
                // Catching specific Guzzle exceptions
                $statusCode = $e->getResponse()->getStatusCode();
                //TODO
                // Special handling for HTTP error codes like 403 (Forbidden), 404 (Not Found), etc.
            }

            throw new SpecGenerationFailureException($e->getMessage());
        } catch (HarFailureException $e) {
            // Empty the HAR entries.
            $this->clear();
            throw $e;
        } catch (RemoteServiceException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Other or unknown errors.
            throw new SpecGenerationFailureException($e->getMessage());
        }
    }

    /**
     * Initializes the Har object.
     *
     * @param int $allowedEntriesCount
     *   The maximum size of the entries array.
     */
    public function init(int $allowedEntriesCount = 1): void
    {
        $this->allowedEntriesCount = $allowedEntriesCount;
    }

    /**
     * Checks if the entries array is full.
     *
     * @return bool
     *   True if the entries array is full, false otherwise.
     */
    public function isFull(): bool
    {
        if ($this->allowedEntriesCount < 0) {
            return false;
        }

        return count($this->entries) >= $this->allowedEntriesCount;
    }

    /**
     * Sets the strategy for adding entries to the tool.
     *
     * @param HarEntryBuilderStrategy $harEntryBuilderStrategy
     *   The strategy for adding entries.
     */
    public function setHarEntryBuilderStrategy(HarEntryBuilderStrategy $harEntryBuilderStrategy): void
    {
        $this->harEntryBuilderStrategy = $harEntryBuilderStrategy;
    }
}
