<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\Tool\PropertiesComparer;
use PassePlat\Core\WebService\WebServiceInterface;

/**
 * Condition which checks the request query parameters.
 */
class RequestQueryParamCondition_0 extends ConditionBase
{
    use ExecutionTraceProviderTrait;

    /**
     * Expected value max length in bytes.
     *
     * This limit can be increased if needed, but keep in mind the security implications
     * (impact on DB storage, parsing performance).
     */
    const EXPECTED_VALUES_MAX_LENGTH = 10000;

    /**
     * Checks if the current request query parameters equals all the specified parameters.
     */
    const MATCH_TYPE__ALL = '0';

    /**
     * Checks if the current request query parameters contain all the specified parameters.
     */
    const MATCH_TYPE__CONTAINS = '1';

    /**
     * Query string max length in bytes.
     *
     * This limit can be increased if needed, but keep in mind the security implications
     */
    const QUERY_STRING_MAX_LENGTH = 10000;

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'status' => 'normal',
            'options' => [
                'invertResult' => false,
                'expectedValues' => '',
                'matchType' => static::MATCH_TYPE__ALL,
            ],
        ];

        return static::replaceFormData($defaultData, $providedData);
    }

    public static function getFormDefinition(string $rootPath = '~'): array
    {
        return [
            'renderView' => [
                [
                    'type' => 'div',
                    'content' => [
                        [
                            'type' => 'div',
                            'attributes' => [
                                'class' => 'fw-bold',
                            ],
                            'content' => 'Expected Values:',
                        ],
                        [
                            'type' => 'TextField',
                            'placeholder' => 'param1=value1&param2=value2',
                            'dataLocation' => $rootPath . '.options.expectedValues',
                        ],
                        [
                            'type' => 'div',
                            'attributes' => [
                                'class' => 'fw-bold',
                            ],
                            'content' => 'Matching Type:',
                        ],
                        [
                            'type' => 'CheckBoxField',
                            'controlType' => 'radio',
                            'dataLocation' => $rootPath . '.options.matchType',
                            'defaultFieldValue' => '0',
                            'options' => [
                                [
                                    'label' => [
                                        [
                                            'type' => 'span',
                                            'attributes' => [
                                                'class' => 'me-2',
                                            ],
                                            'content' => ['ALL'],
                                        ],
                                    ],
                                    'value' => '0',
                                ],
                                [
                                    'label' => [
                                        [
                                            'type' => 'span',
                                            'attributes' => [
                                                'class' => 'me-2',
                                            ],
                                            'content' => ['CONTAINS'],
                                        ],
                                    ],
                                    'value' => '1',
                                ],
                            ],
                        ],
                        [
                            'type' => 'div',
                            'attributes' => [
                                'class' => 'fw-bold',
                            ],
                            'content' => 'Invert Result:',
                        ],
                        [
                            'type' => 'CheckBoxField',
                            'attributes' => [
                                'class' => 'pb-2',
                            ],
                            'dataLocation' => $rootPath . '.options.invertResult',
                            'defaultFieldValue' => false,
                            'options' => [
                                [
                                    'label' => [
                                        [
                                            'type' => 'span',
                                            'attributes' => [
                                                'class' => 'me-2',
                                            ],
                                            'content' => ['Invert Result?'],
                                        ],
                                    ],
                                    'value' => true,
                                ],
                            ],
                        ],
                        [
                            'type' => 'div',
                            'attributes' => [
                                'class' => 'fw-bold',
                            ],
                            'content' => 'Status:',
                        ],
                        [
                            'type' => 'CheckBoxField',
                            'controlType' => 'radio',
                            'dataLocation' => $rootPath . '.status',
                            'defaultFieldValue' => 'normal',
                            'options' => [
                                [
                                    'label' => [
                                        [
                                            'type' => 'span',
                                            'attributes' => [
                                                'class' => 'me-2',
                                            ],
                                            'content' => ['Normal'],
                                        ],
                                    ],
                                    'value' => 'normal',
                                ],
                                [
                                    'label' => [
                                        [
                                            'type' => 'span',
                                            'attributes' => [
                                                'class' => 'me-2',
                                            ],
                                            'content' => ['Disable'],
                                        ],
                                    ],
                                    'value' => 'disabled',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getPluginDescription(): array
    {
        // Todo: traduction.
        $description = parent::getPluginDescription();
        $description['publicName'] = 'Request query parameter';
        $description['id'] = 'request_query_param';
        $description['version'] = '0';
        // Todo: réfléchir à ce système de "appliesTo" pour limiter la visibilité des conditions dans l'UI.
        $description['appliesTo'] = [
            'task',
            'phase:' . WebServiceInterface::PHASE__DESTINATION_REACH_FAILURE,
            'phase:' . WebServiceInterface::PHASE__DESTINATION_REQUEST_PREPARATION,
            'phase:' . WebServiceInterface::PHASE__EMITTED_RESPONSE,
            'phase:' . WebServiceInterface::PHASE__STARTED_RECEIVING,
        ];
        $description['optionsSchema']['matchType'] = [
            'type' => 'string',
            'default' => static::MATCH_TYPE__ALL,
        ];
        // TODO: Il n'y a pas vraiment de limite à la taille d'un paramètre de requête, mais il faut quand même
        //  limiter la taille de la valeur de ce paramètre pour éviter de faire exploser la taille de la base de
        //  données. Pour le moment, on se limite en caractères. Si nécessaire, on
        //  pourra augmenter cette limite plus tard, voire la rendre configurable.
        $description['optionsSchema']['expectedValues'] = [
            'type' => 'string',
            'max_chars' => static::EXPECTED_VALUES_MAX_LENGTH,
            'default' => '',
        ];
        return $description;
    }

    protected function selfEvaluate(AnalyzableContent $analyzableContent): bool
    {
        // @todo : delete and refactor.
        $expected = $this->options['expectedValues'];
        if (!isset($_GET[$expected]) || (isset($_GET[$expected]) && !$_GET[$expected])) {
            return true;
        }
        else {
            return false;
        }
        
        if (!isset($this->options['matchType'])) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                "Match type is not provided."
            );
            throw new ConditionException("Match type is not provided.");
        }

        /** @var RequestInfo|null $streamInfo */
        $streamInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);

        if (!$streamInfo) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No stream info found. This condition may be misplaced.');
            throw new ConditionException('No stream info found. This condition may be misplaced.');
        }

        if (strlen($this->options['expectedValues']) > static::EXPECTED_VALUES_MAX_LENGTH) {
            // Todo: traduction.
            $expectedValueMaxLength = static::EXPECTED_VALUES_MAX_LENGTH;
            $this->addConditionExecutionTraceString(
                "The expected value is too long. The maximum length is: $expectedValueMaxLength."
            );
            throw new ConditionException(
                "The expected value is too long. The maximum length is: $expectedValueMaxLength."
            );
        }

        if (strlen($streamInfo->getQueryParamsRaw()) > static::QUERY_STRING_MAX_LENGTH) {
            // Todo: traduction.
            $queryStringMaxLength = static::QUERY_STRING_MAX_LENGTH;
            $this->addConditionExecutionTraceString(
                "The current request's query string is too long. The maximum length is: $queryStringMaxLength."
            );
            throw new ConditionException(
                "The current request's query string is too long. The maximum length is: $queryStringMaxLength."
            );
        }

        // Parse the configured parameters to check.
        parse_str($this->options['expectedValues'], $paramsToCheck);

        $liveQueryParams = $streamInfo->getQueryParams();

        if ($liveQueryParams === null) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                "We could not retrieve the query params of the current request. This condition may be misplaced."
            );
            throw new ConditionException(
                "We could not retrieve the query params of the current request. This condition may be misplaced."
            );
        }

        if (static::MATCH_TYPE__ALL === $this->options['matchType']) {
            if (PropertiesComparer::compareAny($paramsToCheck, $liveQueryParams)) {
                // This is a non-strict comparison (the order doesn't matter). This is intentional.
                // If we need more strict comparison, this can be made configurable.
                // Todo: traduction.
                $this->addConditionExecutionTraceString(<<<INFO
The query params of the current request match the required query params. The condition is met.
INFO);
                return true;
            }

            // Todo: traduction.
            $this->addConditionExecutionTraceString(<<<INFO
The query params of the current request do not match the required query params. The condition is not met.
INFO);
            return false;
        }

        if (static::MATCH_TYPE__CONTAINS === $this->options['matchType']) {
            if (PropertiesComparer::containsAny($paramsToCheck, $liveQueryParams)) {
                // This is a non-strict comparison (the order doesn't matter). This is intentional.
                // If we need more strict comparison, this can be made configurable.
                // Todo: traduction.
                $this->addConditionExecutionTraceString(<<<INFO
The current request does not contain all of the required query params. The condition is not met.
INFO
                );
                return false;
            }

            // Todo: traduction.
            $this->addConditionExecutionTraceString(<<<INFO
The current request contains all of the required query params. The condition is met.
INFO);
            return true;
        }

        // This should never happen.
        // Todo: traduction.
        $this->addConditionExecutionTraceString(
            "Unknown match type: {$this->options['matchType']}"
        );
        throw new ConditionException("Unknown match type: {$this->options['matchType']}");
    }
}
