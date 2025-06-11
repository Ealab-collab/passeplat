<?php

namespace PassePlat\Logger\Elastic\Errors;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\AnalyzableContentComponentBase;

/*
 * Handles the logging of errors with validation against a predefined schema.
 */
class LoggableErrors extends AnalyzableContentComponentBase
{
    /**
     * The list of errors to log.
     *
     * @var array
     */
    protected array $errorList = [];

    /**
     * Schema to validate errors against.
     *
     * @var array|array[]
     */
    protected array $errorSchema = [
        'error_type' => [
            'type' => 'string',
            'required' => true,
            'enum' => [
                'openapi_validation_error',
                // TODO: Add other types of errors gradually.
            ],
        ],
        'userId' => [
            'type' => 'string',
            'required' => false,
        ],
        'webServiceId' => [
            'type' => 'string',
            'required' => false,
        ],
        'timestamp' => [
            'type' => 'integer',
            'required' => false,
        ],
        'code' => [
            'type' => 'integer',
            'required' => false,
        ],
        'type' => [
            'type' => 'string',
            'required' => false,
            'enum' => ['Request', 'Response'],
        ],
        'category' => [
            'type' => 'string',
            'required' => false,
            'enum' => ['Parsing', 'Schema', 'Value'],
        ],
        'item' => [
            'type' => 'string',
            'required' => false,
            'enum' => ['Body', 'Cookies', 'Headers', 'Path', 'Query', 'Security'],
        ],
        'message' => [
            'type' => 'string',
            'required' => false,
        ],
        'lastModifiedDateOpenAPI' => [
            'type' => 'integer',
            'required' => false,
        ],
        'severityLevel' => [
            'type' => 'integer',
            'required' => false,
        ],
        'path' => [
            // Specification path.
            'type' => 'string',
            'required' => false,
        ],
    ];

    /**
     * Adds an error to the list after validating it against the schema.
     *
     * @param array $error
     *   The error data to add.
     */
    public function addError(array $error): void
    {
        if ($this->isValidError($error, $this->errorSchema)) {
            $this->errorList[] = $error;
        }
    }

    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        if (!empty($this->errorList)) {
            $data['errors'] = $this->errorList;
        }

        return $data;
    }

    /**
     * Gets the list of logged errors.
     *
     * @return array
     *   The list of errors.
     */
    public function getErrors(): array
    {
        return $this->errorList;
    }

    /**
     * Validates an error against the schema.
     *
     * @param array $error
     *   The error to validate.
     * @param array $schema
     *   The schema to validate against.
     *
     * @return bool
     *   True if the error is valid, false otherwise.
     */
    private function isValidError(array $error, array $schema): bool
    {
        foreach ($schema as $key => $constraints) {
            if (!array_key_exists($key, $error)) {
                if ($constraints['required']) {
                    // Required field is missing.
                    return false;
                }

                // Skip non-required fields that are not present.
                continue;
            }

            if ($constraints['type'] === 'array' && isset($constraints['schema'])) {
                // Recursively validate array fields.
                if (!is_array($error[$key]) || !$this->isValidError($error[$key], $constraints['schema'])) {
                    return false;
                }
            } else {
                // Validate the type of the field.
                if (gettype($error[$key]) !== $constraints['type']) {
                    return false;
                }

                // Validate enum values if specified.
                if (isset($constraints['enum']) && !in_array($error[$key], $constraints['enum'], true)) {
                    return false;
                }
            }
        }

        return true;
    }
}
