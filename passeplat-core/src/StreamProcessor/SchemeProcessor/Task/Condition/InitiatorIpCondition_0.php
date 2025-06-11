<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ConditionException;

/**
 * Condition which checks the IP address of the initiator.
 */
class InitiatorIpCondition_0 extends ConditionBase
{
    use ExecutionTraceProviderTrait;

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'status' => 'normal',
            'options' => [
                'ipAddress' => [],
                'invertResult' => false,
            ],
        ];

        return static::replaceFormData($defaultData, $providedData);
    }

    public static function getFormDefinition(string $rootPath = '~'): array
    {
        return [
            'renderView' => [
                'type' => 'div',
                'content' => [
                    [
                        'type' => 'Tabs',
                        'attributes' => [
                            'defaultActiveKey' => 'ipAddress',
                        ],
                        'tabs' => [
                            [
                                'attributes' => [
                                    'eventKey' => 'ipAddress',
                                    'title' => 'IP Address',
                                ],
                                'content' => [
                                    'load' => 'ipAddress_tab',
                                ],
                            ],
                            [
                                'attributes' => [
                                    'eventKey' => 'otherOptions',
                                    'title' => 'Other Options',
                                ],
                                'content' => [
                                    'load' => 'otherOptions_tab',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'listForms' => [
                'otherOptions_tab' => [
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
                                        'content' => ['InvertResult?'],
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
                        'content' => 'Status',
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
                                'value' => 'disable',
                            ],
                        ],
                    ],
                ],
                'ipAddress_tab' => [
                    [
                        'type' => 'div',
                        'content' => [
                            [
                                'type' => 'table',
                                'attributes' => [
                                    'class' => 'table',
                                ],
                                'content' => [
                                    [
                                        'type' => 'thead',
                                        'content' => [
                                            [
                                                'type' => 'tr',
                                                'content' => [
                                                    ['type' => 'th', 'content' => 'IP Address'],
                                                    ['type' => 'th', 'content' => 'Actions'],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'tbody',
                                        'content' => [
                                            'type' => 'Switch',
                                            'content' => $rootPath . '.options.ipAddress',
                                            'singleOption' => [
                                                'load' => 'ipAddress_item_row',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'div',
                                'content' => 'There are no IP address.',
                                'attributes' => [
                                    'class' => 'text-muted fst-italic text-center m-2',
                                ],
                                'actions' => [
                                    [
                                        'what' => 'hide',
                                        'whenDataCountOf' =>
                                            static::setWhenDataCountOf($rootPath),
                                        'inContext' => 'global',
                                        'isNot' => 0,
                                    ],
                                ],
                            ],
                            [
                                'type' => 'div',
                                'attributes' => [
                                    'class' => 'd-flex justify-content-center',
                                ],
                                'content' => [
                                    'type' => 'div',
                                    'attributes' => [
                                        'class' => 'd-flex',
                                    ],
                                    'content' => [
                                        'type' => 'BsButton',
                                        'attributes' => [
                                            'class' => 'btn-sm btn-secondary',
                                        ],
                                        'content' => '+',
                                        'actions' => [
                                            [
                                                'what' => 'addData',
                                                'on' => 'click',
                                                'path' => $rootPath . '.options.ipAddress',
                                                'value' => [
                                                    'address' => '',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'ipAddress_item_row' => [
                    'type' => 'tr',
                    'content' => [
                        [
                            'type' => 'td',
                            'content' => [
                                [
                                    'type' => 'TextField',
                                    'placeholder' => 'Enter an IP address.',
                                    'dataLocation' => '~.address',
                                ],
                            ],
                        ],
                        [
                            'type' => 'td',
                            'content' => [
                                [
                                    'type' => 'div',
                                    'attributes' => [
                                        'class' => 'd-flex gap-3',
                                    ],
                                    'content' => [
                                        [
                                            'type' => 'BsButton',
                                            'content' => '▲',
                                            'actions' => [
                                                [
                                                    'what' => 'moveData',
                                                    'on' => 'click',
                                                    'target' => 'currentTemplateData',
                                                    'parentLevel' => 0,
                                                    'increment' => -1,
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'BsButton',
                                            'content' => '▼',
                                            'actions' => [
                                                [
                                                    'what' => 'moveData',
                                                    'on' => 'click',
                                                    'target' => 'currentTemplateData',
                                                    'parentLevel' => 0,
                                                    'increment' => 1,
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'BsButton',
                                            'content' => '❌',
                                            'actions' => [
                                                [
                                                    'what' => 'removeData',
                                                    'on' => 'click',
                                                    'target' => 'currentTemplateData',
                                                ],
                                            ],
                                        ],
                                    ],
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
        $description['publicName'] = 'Initiator IP';
        $description['id'] = 'initiator_ip';
        $description['version'] = '0';
        // Todo: réfléchir à ce système de "appliesTo" pour limiter la visibilité des conditions dans l'UI.
        $description['appliesTo'] = ['task'];
        $description['optionsSchema']['ipAddress'] = [
            'address' => 'string',
        ];
        return $description;
    }

    protected function selfEvaluate(AnalyzableContent $analyzableContent): bool
    {
        if (empty($this->options['ipAddress'])) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('Invalid configuration: no IP address provided.');
            throw new ConditionException('Invalid configuration: no IP address provided.');
        }

        if (!is_array($this->options['ipAddress'])) {
            // This is an implementation error.
            $this->addConditionExecutionTraceString(<<<INFO
Could not read the IP addresses to match.
INFO);
            throw new ConditionException('Could not read the IP addresses to match.');
        }

        /** @var RequestInfo|null $streamInfo */
        $streamInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);

        if (!$streamInfo) {
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No stream info found.');
            throw new ConditionException('No stream info found. This condition may be misplaced.');
        }

        $initiatorIp = $streamInfo->getIpAddress();

        if (empty($initiatorIp)) {
            // This should not happen, but we never know, edge cases may exist (reforged stream, etc.).
            // Todo: traduction.
            $this->addConditionExecutionTraceString('No IP info found on current stream.');
            throw new ConditionException('No IP info found on current stream.');
        }

        foreach ($this->options['ipAddress'] as $ipObject) {
            $ipAddress = $ipObject['address'];

            if ($initiatorIp === $ipAddress) {
                // Todo: traduction.
                $this->addConditionExecutionTraceString(
                    "Value matched for the IP address: {$ipAddress}"
                );
                return true;
            }
        }

        // Todo: traduction.
        $this->addConditionExecutionTraceString(<<<INFO
The IP address of the current stream does not match the required IP address. The condition is not met.
INFO);
        return false;
    }

    /**
     * Generates a JSONPath expression based on the provided root path.
     *
     * The expression is used to count the number of elements in the 'ipAddress' array.
     *
     * @param string $rootPath
     *   The root path used to build the JSONPath expression.
     *
     * @return string
     *   The generated JSONPath expression.
     */
    private static function setWhenDataCountOf(string $rootPath): string
    {
        if (str_starts_with($rootPath, '~~.')) {
            return '$.' . substr($rootPath, 3) . '.options.ipAddress[*]';
        }

        if (str_starts_with($rootPath, '~.')) {
            return '$..' . substr($rootPath, 2) . '.options.ipAddress[*]';
        }

        return '$..options.ipAddress[*]';
    }
}

/*
 * Polyfill for PHP8's str_starts_with.
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
    }
}
