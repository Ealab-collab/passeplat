<?php

namespace PassePlat\Forms\Handler;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Forms\Model\DummyValidationError;
use PassePlat\Forms\Vue\Response;
use PassePlat\Core\Exception\Exception;
use PassePlat\Logger\Elastic\Definitions\ErrorsIndexDef;
use PassePlat\Logger\Elastic\Errors\LoggableErrors;
use PassePlat\Logger\Elastic\Exception\CouldNotCreateElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\CouldNotLoadElasticsearchIndexException;
use PassePlat\Logger\Elastic\Exception\ElasticsearchException;
use PassePlat\Logger\Elastic\Service\ElasticsearchService;

/**
 * This file should only exist in development and testing environments.
 * It must NEVER be used in production.
 */

/**
 * For generating and logging dump OpenAPI validation errors:
 *
 * - Set the ERROR_COUNT constant to define the number of documents in Elasticsearch,
 *   where each document corresponds to a validation error.
 * - Then, run the script by calling localhost/dump.php.
 */

/**
 * Handles requests corresponding to 'forms/dump.php'.
 * This class is responsible for generating and logging dump validation errors.
 */
class DumpHandler extends Handler
{
    /**
     * Fictitious timestamp representing the last modification date of a Swagger specification file.
     */
    const LAST_MODIFIED_DATE_OPENAPI = 1640995200;

    /**
     * Processes the POST request for 'charts/dump.php'.
     *
     * Generates random validation errors and logs them to Elasticsearch.
     *
     * @throws CouldNotCreateElasticsearchIndexException
     *   If the Elasticsearch index cannot be created.
     * @throws CouldNotLoadElasticsearchIndexException
     *   If the Elasticsearch index cannot be loaded.
     * @throws ElasticsearchException
     *   If an error occurs while logging to Elasticsearch.
     * @throws UnmetDependencyException|Exception
     *   If a required component cannot be created.
     * @throws \Random\RandomException
     *   Thrown if an error occurs while generating random validation errors.
     */
    public function handlePostRequest(): void
    {
        $dumpErrorsToLog = $this->generateLoggableDumpErrors([
            'webServiceId' => '',
            'userId' => '',
        ], 0);

        /** @var ElasticsearchService $elasticSearchServer */
        $elasticSearchServer = ComponentBasedObject::getRootComponentByClassName(ElasticsearchService::class, true);

        /** @var ErrorsIndexDef $errorIndex */
        $errorIndex = ComponentBasedObject::getRootComponentByClassName(ErrorsIndexDef::class, true);

        $response = $elasticSearchServer->logBulk($errorIndex, $dumpErrorsToLog);

        Response::sendOk($response);
    }

    /**
     * Generates a set of loggable validation errors.
     *
     * Creates dummy validation errors and formats them for logging.
     *
     * @param array<string,mixed>|null $errorOverrides
     *   Overrides the default error values. See the implementation for the list of values to override.
     * @param int|null $errorCount
     *   Desired dummy validation errors to generate.
     *
     * @return array
     *   The array of formatted validation errors ready to be logged.
     *
     * @throws UnmetDependencyException
     *   If a required component cannot be created.
     * @throws \Random\RandomException
     *   Thrown if an error occurs while generating random validation errors.
     */
    private function generateLoggableDumpErrors(?array $errorOverrides = null, ?int $errorCount = 0): array
    {
        /** @var DummyValidationError $dummyValidationError */
        $dummyValidationError = ComponentBasedObject::getRootComponentByClassName(DummyValidationError::class, true);

        /** @var LoggableErrors $loggableErrors */
        $loggableErrors = ComponentBasedObject::getRootComponentByClassName(LoggableErrors::class, true);

        for ($i = 0; $i < $errorCount; $i++) {
            $validationError = $dummyValidationError->getRandomValidationError();

            $loggableErrors->addError(
                array_merge(
                    [
                        'error_type' => 'openapi_validation_error',
                        'webServiceId' => '',
                        'userId' => '',
                        'timestamp' => $validationError->getTimestamp(),
                        'code' => $validationError->getCode(),
                        'type' => $validationError->getType(),
                        'category' => $validationError->getCategory(),
                        'item' => $validationError->getItem(),
                        'message' => $validationError->getMessage(),
                        'lastModifiedDateOpenAPI' => static::LAST_MODIFIED_DATE_OPENAPI,
                        'severityLevel' => $validationError->getSeverityLevel(),
                        'path' => $validationError->getSpecPath(),
                    ],
                    $errorOverrides ?? []
                )
            );
        }

        $dataToLog = $loggableErrors->getErrors();

        // Add dump id-transaction to distinguish fake data from real data in case of issues.
        foreach ($dataToLog as &$item) {
            $item['transaction_id'] = 'dump';
        }

        return $dataToLog;
    }
}
