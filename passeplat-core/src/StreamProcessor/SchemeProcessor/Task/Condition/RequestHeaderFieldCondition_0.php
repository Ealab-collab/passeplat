<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\AnalyzableContentComponentBase;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;

/**
 * Condition plugin to check if a request header is set with a specific value.
 */
class RequestHeaderFieldCondition_0 extends HeaderFieldConditionBase_0
{
    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'status' => 'normal',
            'options' => [
                'invertResult' => false,
                'headerFieldName' => '',
                'headerFieldValue' => '',
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
                            'content' => 'Name:',
                        ],
                        [
                            'type' => 'TextField',
                            'placeholder' => 'Header Field Name',
                            'dataLocation' => $rootPath . '.options.headerFieldName',
                        ],
                        [
                            'type' => 'div',
                            'attributes' => ['class' => 'fw-bold'],
                            'content' => 'Value:',
                        ],
                        [
                            'type' => 'TextField',
                            'placeholder' => 'Header Field Value',
                            'dataLocation' => $rootPath . '.options.headerFieldValue',
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
                                            'content' => ['InvertResult?'],
                                        ],
                                    ],
                                    'value' => true,
                                ],
                            ],
                        ],
                        [
                            'type' => 'div',
                            'attributes' => ['class' => 'fw-bold'],
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
                                    'value' => 'disable',
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
        $description['publicName'] = 'Request header';
        $description['id'] = 'request_header';
        // Todo: réfléchir à ce système de "appliesTo" pour limiter la visibilité des conditions dans l'UI.
        $description['appliesTo'][] = 'request';
        return $description;
    }

    protected function getStreamInfoComponent(AnalyzableContent $analyzableContent): ?AnalyzableContentComponentBase
    {
        /** @var RequestInfo|null $streamInfo */
        $streamInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);
        return $streamInfo;
    }
}
