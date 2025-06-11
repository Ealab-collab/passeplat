<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\Tool\PropertiesComparer;
use PassePlat\Core\WebService\WebServiceInterface;

/**
 * Condition which checks the x-www-form-urlencoded body of the request.
 */
class RequestXWwwFormUrlencodedBodyCondition_0 extends ConditionBase
{
    use ExecutionTraceProviderTrait;

    /**
     * Body max length in bytes.
     *
     * This limit can be increased if needed, but keep in mind the security implications
     */
    const BODY_MAX_LENGTH = 10000;

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
        $description['publicName'] = 'Request x-www-form-urlencoded body';
        $description['id'] = 'request_xwwwformurlencoded_body';
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
            'type' => 'integer',
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
        if (empty($this->options['expectedValues'])) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No expected values provided.');
            throw new ConditionException('No expected values provided.');
        }

        /** @var RequestInfo $streamInfo */
        $streamInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);

        if (!$streamInfo) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No stream info found. This condition may be misplaced.');
            throw new ConditionException('No stream info found. This condition may be misplaced.');
        }

        /** @var Body $body */
        $body = $streamInfo->getComponentByClassName(Body::class);

        if (!$body->isBodyAnalyzable()) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                'The body is not analyzable. It may be too big or not ready for analysis yet.'
            );
            throw new ConditionException(
                'The body is not analyzable. It may be too big or not ready for analysis yet.'
            );
        }

        if ($body->getRealBodyLength() >= static::BODY_MAX_LENGTH) {
            // Todo: traduction.
            $bodyMaxLength = static::BODY_MAX_LENGTH;
            $this->addConditionExecutionTraceString(<<<INFO
The body is too big for evaluation. It must be smaller than $bodyMaxLength characters.
INFO);
            throw new ConditionException(<<<INFO
The body is too big for evaluation. It must be smaller than $bodyMaxLength characters.
INFO);
        }

        parse_str($this->options['expectedValues'], $decodedExpectedValues);
        parse_str($body->getBody(), $decodedBody);

        if (static::MATCH_TYPE__ALL === $this->options['matchType']) {
            if (!PropertiesComparer::compareAny($decodedBody, $decodedExpectedValues)) {
                // Todo: traduction.
                $this->addConditionExecutionTraceString(
                    'The body does not match the expected values. The condition is not met.'
                );
                return false;
            }

            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                'The body matches the expected values. The condition is met.'
            );
            return true;
        }

        if (static::MATCH_TYPE__CONTAINS === $this->options['matchType']) {
            if (!PropertiesComparer::containsAny($decodedBody, $decodedExpectedValues)) {
                // Todo: traduction.
                $this->addConditionExecutionTraceString(
                    'The body does not contain all of the expected values. The condition is not met.'
                );
                return false;
            }

            // Todo: traduction.
            $this->addConditionExecutionTraceString(
                'The body contains all of the expected values. The condition is met.'
            );
            return false;
        }

        // This should never happen.
        // Todo: traduction.
        $this->addConditionExecutionTraceString(
            "Unknown match type: {$this->options['matchType']}"
        );
        throw new ConditionException("Unknown match type: {$this->options['matchType']}");
    }
}
