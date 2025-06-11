<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;

/**
 * Condition which checks the JSON body of the response.
 */
class ResponseJsonBodyCondition_0 extends JsonBodyConditionBase_0
{
    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'status' => 'normal',
            'options' => [
                'invertResult' => false,
                'jsonpath' => '',
                'expectedValues' => '',
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
                            'attributes' => ['class' => 'fw-bold'],
                            'content' => 'JSON Path:',
                        ],
                        [
                            'type' => 'TextField',
                            'placeholder' => 'Enter the JSON Path',
                            'dataLocation' => $rootPath . '.options.jsonpath',
                        ],
                        [
                            'type' => 'div',
                            'attributes' => ['class' => 'fw-bold'],
                            'content' => 'Expected Values:',
                        ],
                        [
                            'type' => 'TextField',
                            'placeholder' => 'Enter the expected values',
                            'dataLocation' => $rootPath . '.options.expectedValues',
                        ],
                        [
                            'type' => 'CheckBoxField',
                            'attributes' => ['class' => 'pb-2'],
                            'dataLocation' => $rootPath . '.options.invertResult',
                            'defaultFieldValue' => false,
                            'options' => [
                                [
                                    'label' => [
                                        [
                                            'type' => 'span',
                                            'attributes' => ['class' => 'me-2'],
                                            'content' => ['Invert Result?'],
                                        ],
                                    ],
                                    'value' => true,
                                ],
                            ],
                        ],
                        [
                            'type' => 'div',
                            'attributes' => ['class' => 'fw-bold'],
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
                                            'attributes' => ['class' => 'me-2'],
                                            'content' => ['Normal'],
                                        ],
                                    ],
                                    'value' => 'normal',
                                ],
                                [
                                    'label' => [
                                        [
                                            'type' => 'span',
                                            'attributes' => ['class' => 'me-2'],
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
            'listForms' => [],
        ];
    }

    protected function getPluginDescription(): array
    {
        // Todo: traduction.
        $description = parent::getPluginDescription();
        $description['publicName'] = 'Corps de la réponse (JSON)';
        $description['id'] = 'response_json_body';
        return $description;
    }

    protected function getStreamInfoComponent(AnalyzableContent $analyzableContent)
    {
        try {
            /** @var ResponseInfo $requestInfo */
            $requestInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);
        } catch (UnmetDependencyException $e) {
            // This should never happen.
            // Todo: log pour les devs.
            return null;
        }

        return $requestInfo;
    }
}
