<?php

namespace PassePlat\Openapi\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\WebService;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition\ConditionBase;
use PassePlat\Logger\Elastic\Errors\LoggableErrors;
use PassePlat\Openapi\Exception\ValidationFailureException;
use PassePlat\Openapi\Tool\Validation\ValidationError;
use PassePlat\Openapi\Tool\Validation\Validator;

//TODO
// Unlike tasks, conditions are not detectable here by the jsonforms system.
// So, move it to the core condition folder or update the jsonforms system.

/**
 * This condition validates various HTTP stream elements against an OpenAPI specification file,
 * which can be specified as options. It returns true if all elements are valid; otherwise, it returns false.
 */
class OpenApiValidationCondition_0 extends ConditionBase
{
    protected function getPluginDescription(): array
    {
        // Todo: traduction.
        $description = parent::getPluginDescription();
        $description['publicName'] = 'Open API validation';
        $description['id'] = 'open_api_validation';
        $description['version'] = '0';
        $description['appliesTo'] = ['task'];

        $description['optionsSchema']['specFilePath'] = [
            'type' => 'string',
            //TODO
            // To be adjusted based on the file system.
            'max_chars' => 255,
        ];

        $options = [
            'Cookies',
            'Path',
            'PathMethod',
            'PathMethodParameters',
            'QueryArguments',
            'Request',
            'RequestBody',
            'RequestHeaders',
            'Response',
            'ResponseBody',
            'ResponseHeaders',
            'Security',
        ];

        foreach ($options as $option) {
            $description['optionsSchema']['validationMethods'][$option] = [
                'type' => 'bool',
            ];
        }

        return $description;
    }

    public static function hasEnableForm(): bool
    {
        //TODO
        // Implement the jsonForm methods and delete this one.
        return false;
    }

    /**
     * Log a validation error.
     *
     * @param AnalyzableContent $analyzableContent
     * @param ValidationError $error
     *   The ValidationError object.
     * @param string $specFilePath
     *   The path of specification openapi file.
     */
    private function logValidationError(
        AnalyzableContent $analyzableContent,
        ValidationError $error,
        string $specFilePath
    ): void {
        try {
            $webService = $analyzableContent->getComponentByClassName(WebService::class, false);

            if (empty($webService)) {
                return;
            }

            $loggableErrors = $analyzableContent->getComponentByClassName(LoggableErrors::class, true);

            $loggableErrors->addError([
                'error_type' => 'openapi_validation_error',
                'webServiceId' => $webService->getWebService()->getWebServiceId(),
                'userId' => $webService->getWebService()->getAccessingUser()->getId(),
                'timestamp' => $error->getTimestamp(),
                'code' => $error->getCode(),
                'type' => $error->getType(),
                'category' => $error->getCategory(),
                'item' => $error->getItem(),
                'message' => $error->getMessage(),
                'lastModifiedDateOpenAPI' => filemtime($specFilePath),
                'severityLevel' => $error->getSeverityLevel(),
                'path' => $error->getSpecPath(),
            ]);
        } catch (\Exception $e) {
            // Skip the log.
        }
    }

    protected function selfEvaluate(AnalyzableContent $analyzableContent): bool
    {
        //TODO
        // Find a more secure method to retrieve this file.
        $specFilePath = $this->options['specFilePath'] ?? '';
        $logErrors = $this->options['logErrors'] ?? false;

        if (!file_exists($specFilePath)) {
            throw new ConditionException('The specification file needs to be specified as an option.');
        }

        $requestInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);
        $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);
        $validator = $analyzableContent->getComponentByClassName(Validator::class, true);
        $validator->initFromYamlFile($specFilePath, $requestInfo->getRequest(), $responseInfo->getResponse());
        $validationMethods = $this->options['validationMethods'] ?? [];
        $validationError = $analyzableContent->getComponentByClassName(ValidationError::class, true);

        foreach ($validationMethods as $key => $value) {
            try {
                if ($value !== true) {
                    // The option is not checked.
                    continue;
                }

                $validationMethod = 'check' . $key . 'Errors';

                if (method_exists($validator, $validationMethod)) {
                    /** @var ValidationError $hasError */
                    $hasError = $validator->$validationMethod($validationError);
                    if ($hasError !== false) {
                        if ($logErrors) {
                            $this->logValidationError($analyzableContent, $validationError, $specFilePath);
                        }

                        return false;
                    }
                }
            } catch (ValidationFailureException $e) {
                return false;
            }
        }

        return true;
    }
}
