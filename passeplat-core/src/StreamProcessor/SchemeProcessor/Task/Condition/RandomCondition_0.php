<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;

/**
 * Condition that randomizes task execution using the threshold option.
 */
class RandomCondition_0 extends ConditionBase
{
    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'status' => 'normal',
            'options' => [
                'invertResult' => false,
                'threshold' => 50,
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
                        'type' => 'div',
                        'attributes' => [
                            'class' => 'fw-bold',
                        ],
                        'content' => 'Threshold',
                    ],
                    [
                        'type' => 'TextField',
                        'attributes' => [
                            'class' => 'pb-2',
                        ],
                        'dataLocation' => $rootPath . '.options.threshold',
                        'placeholder' => 'Enter an integer between 1 and 99.',
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
            ],
            'listForms' => [],
        ];
    }

    protected function getPluginDescription(): array
    {
        // Todo: traduction.
        $description = parent::getPluginDescription();
        $description['publicName'] = 'Random condition';
        $description['id'] = 'random';
        $description['version'] = '0';
        $description['appliesTo'] = ['task'];
        $description['optionsSchema']['threshold'] = [
            'type' => 'int',
            'max' => 99,
            'min' => 1,
            'default' => 50
        ];
        return $description;
    }

    protected function selfEvaluate(AnalyzableContent $analyzableContent): bool
    {
        // By default, it's heads or tails.
        $default = 50;

        $threshold = $this->options['threshold'] ?? $default;

        if (!is_numeric($threshold)) {
            $threshold = $default;
        }

        if (($threshold > 99) || ($threshold < 1)) {
            $threshold = $default;
        }

        return mt_rand(0, 100) <= $threshold;
    }
}
