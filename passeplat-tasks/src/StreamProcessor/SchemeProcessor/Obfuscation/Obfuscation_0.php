<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\Obfuscation;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;
use PassePlat\Core\Tool\LengthPreservingObfuscator;
use PassePlat\Core\Tool\UltimateObfuscator;
use Symfony\Component\Yaml\Yaml;

/**
 * Task to obfuscate header and body of an HTTP request and response.
 *
 * Options for this task:
 *  - 'preserve_length': Enables length-preserving obfuscation method (1 for enabled, 0 for disabled).
 *  - 'request_body': Obfuscate request body (1 for enabled, 0 for disabled).
 *  - 'request_header': Obfuscate request header (1 for enabled, 0 for disabled).
 *  - 'response_body': Obfuscate response body (1 for enabled, 0 for disabled).
 *  - 'response_header': Obfuscate response header (1 for enabled, 0 for disabled).
 *  - 'keys_exclusions': List of exclusion keys, each key described by:
 *    - 'key': The key to be excluded.
 *    - 'request_body':
 *          Indicates if the key should be excluded from the request body (1 for enabled, 0 for disabled).
 *    - 'request_header':
 *          Indicates if the key should be excluded from the request header (1 for enabled, 0 for disabled).
 *    - 'response_body':
 *          Indicates if the key should be excluded from the response body (1 for enabled, 0 for disabled).
 *    - 'response_header':
 *          Indicates if the key should be excluded from the response header (1 for enabled, 0 for disabled).
 *
 * In future versions:
 *  - It will be necessary to obfuscate the $_POST variable.
 *  - Modify certain methods to lambda expressions.
 */
class Obfuscation_0 extends TaskHandlerBase
{
    /**
     * Sets the exclusion list in the obfuscation configuration based on the provided options.
     *
     * If exclusions are specified in the options, updates the configuration with these exclusions.
     * Otherwise, initializes the exclusion list as an empty array.
     *
     * @param array $options
     *   The task options.
     * @param string $exclusionScope
     *   The scope of exclusions to check (e.g., 'request_body', 'request_header', etc.).
     * @param array $config
     *   The obfuscation configuration array is passed by reference.
     */
    private function configureExclusionsFromOptions(array $options, string $exclusionScope, array &$config): void
    {
        $config['exclusions'] = [];

        if (! empty($options[$exclusionScope])) {
            $exclusions = $this->getExclusions($options, $exclusionScope);
            $config['exclusions']  = $exclusions;
        }
    }

    /**
     * Sets the 'expectedContentType' configuration value based on the header.
     *
     * If 'Content-Type' is present in the header, updates the configuration with the corresponding value.
     * Otherwise, resets it to an empty string.
     *
     * @param Header $header
     *   The header of the HTTP request or response.
     * @param $config
     *  The obfuscation configuration array is passed by reference.
     */
    private function configureExpectedContentType(Header $header, &$config)
    {
        $contentType = $header->getHeaderFieldValue('Content-Type');

        if (!empty($contentType)) {
            $config['expectedContentType'] = $contentType;
        } else {
            $this->resetExpectedConfigContentType($config);
        }
    }

    /**
     * Execute the event.
     */
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {
        // Instantiation of the appropriate obfuscator.
        if (!empty($options['preserve_length'])) {
            $config['obfuscator'] = $analyzableContent
                ->getComponentByClassName(LengthPreservingObfuscator::class, true);
        } else {
            $config['obfuscator'] = $analyzableContent
                ->getComponentByClassName(UltimateObfuscator::class, true);
        }

        $config['char'] = '*';

        /** @var RequestInfo $requestInfo */
        $requestInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);

        if (!empty($requestInfo)) {
            /** @var Header $requestHeader */
            $requestHeader = $requestInfo->getComponentByClassName(Header::class);

            if (!empty($requestHeader)) {
                $this->processHeader(
                    $options,
                    'request_header',
                    $requestHeader,
                    $config
                );
            }

            /** @var Body $requestBody */
            $requestBody = $requestInfo->getComponentByClassName(Body::class);

            if (!empty($requestBody)) {
                $this->processBody(
                    $options,
                    'request_body',
                    $requestHeader,
                    $requestBody,
                    $config
                );
            }
        }

        /** @var ResponseInfo $responseInfo */
        $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);

        if (!empty($responseInfo)) {
            /** @var Header $responseHeader */
            $responseHeader = $responseInfo->getComponentByClassName(Header::class);

            if (!empty($responseHeader)) {
                $this->processHeader(
                    $options,
                    'response_header',
                    $responseHeader,
                    $config
                );
            }

            /** @var Body $responseBody */
            $responseBody = $responseInfo->getComponentByClassName(Body::class);

            if (!empty($responseBody)) {
                $this->processBody(
                    $options,
                    'response_body',
                    $responseHeader,
                    $responseBody,
                    $config
                );
            }
        }
    }

    /**
     * Retrieves the exclusion list based on the specified options and exclusion scope.
     *
     * This method checks the task options for exclusion configurations and returns
     * an array of keys that should be excluded from obfuscation for the given scope.
     *
     * @param array $options
     *   The task options, including exclusion configurations.
     * @param string $exclusionScope
     *   The scope of exclusions to retrieve (e.g., 'request_body', 'request_header', etc.).
     *
     * @return array
     *   An array of keys to be excluded from obfuscation based on the provided options and scope.
     */
    private function getExclusions(array $options, string $exclusionScope): array
    {
        if (empty($options[$exclusionScope]) || empty($options['keys_exclusions'])) {
            return [];
        }

        $res = [];
        foreach ($options['keys_exclusions'] as $exclusion) {
            if (!empty($exclusion['key']) && !empty($exclusion[$exclusionScope])) {
                $res[] = $exclusion['key'];
            }
        }

        return $res;
    }

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'preserve_length' => 1,
            'request_header' => 1,
            'request_body' => 1,
            'response_header' => 1,
            'response_body' => 1,
            'keys_exclusions' => []
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
                        // First div.
                        [
                            'type' => 'div',
                            'attributes' => [
                                'class' => 'fw-bold',
                            ],
                            'content' => 'Obfuscation options:',
                        ],
                        // Second div.
                        [
                            'type' => 'div',
                            'attributes' => [
                                'class' => 'ms-2',
                            ],
                            'content' => [
                                [
                                    'type' => 'div',
                                    'class' => 'row',
                                    'content' => [
                                        [
                                            'type' => 'input',
                                            'attributes' => [
                                                'id' => 'request_header',
                                                'type' => 'checkbox',
                                                'checked' => $rootPath . '.options.request_header',
                                                'class' => 'me-1',
                                            ],
                                            'actions' => [
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.request_header',
                                                    'path' => $rootPath . '.options.request_header',
                                                    'is' => 0,
                                                    'on' => 'click',
                                                    'value' => 1,
                                                ],
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.request_header',
                                                    'path' => $rootPath . '.options.request_header',
                                                    'is' => 1,
                                                    'on' => 'click',
                                                    'value' => 0,
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'label',
                                            'actions' => [
                                                [
                                                    'what' => 'tooltip',
                                                    'content' => 'Obfuscate the request header.',
                                                ],
                                            ],
                                            'attributes' => [
                                                'for' => 'request_header',
                                            ],
                                            'content' => 'Request Header',
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'div',
                                    'class' => 'row',
                                    'content' => [
                                        [
                                            'type' => 'input',
                                            'attributes' => [
                                                'id' => 'request_body',
                                                'type' => 'checkbox',
                                                'checked' => $rootPath . '.options.request_body',
                                                'class' => 'me-1',
                                            ],
                                            'actions' => [
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.request_body',
                                                    'path' => $rootPath . '.options.request_body',
                                                    'is' => 0,
                                                    'on' => 'click',
                                                    'value' => 1,
                                                ],
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.request_body',
                                                    'path' => $rootPath . '.options.request_body',
                                                    'is' => 1,
                                                    'on' => 'click',
                                                    'value' => 0,
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'label',
                                            'actions' => [
                                                [
                                                    'what' => 'tooltip',
                                                    'content' => 'Obfuscate the request body.',
                                                ],
                                            ],
                                            'attributes' => [
                                                'for' => 'request_body',
                                            ],
                                            'content' => 'Request Body',
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'div',
                                    'class' => 'row',
                                    'content' => [
                                        [
                                            'type' => 'input',
                                            'attributes' => [
                                                'id' => 'response_header',
                                                'type' => 'checkbox',
                                                'checked' => $rootPath . '.options.response_header',
                                                'class' => 'me-1',
                                            ],
                                            'actions' => [
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.response_header',
                                                    'path' => $rootPath . '.options.response_header',
                                                    'is' => 0,
                                                    'on' => 'click',
                                                    'value' => 1,
                                                ],
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.response_header',
                                                    'path' => $rootPath . '.options.response_header',
                                                    'is' => 1,
                                                    'on' => 'click',
                                                    'value' => 0,
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'label',
                                            'actions' => [
                                                [
                                                    'what' => 'tooltip',
                                                    'content' => 'Obfuscate the response header.',
                                                ],
                                            ],
                                            'attributes' => [
                                                'for' => 'response_header',
                                            ],
                                            'content' => 'Response Header',
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'div',
                                    'class' => 'row',
                                    'content' => [
                                        [
                                            'type' => 'input',
                                            'attributes' => [
                                                'id' => 'response_body',
                                                'type' => 'checkbox',
                                                'checked' => $rootPath . '.options.response_body',
                                                'class' => 'me-1',
                                            ],
                                            'actions' => [
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.response_body',
                                                    'path' => $rootPath . '.options.response_body',
                                                    'is' => 0,
                                                    'on' => 'click',
                                                    'value' => 1,
                                                ],
                                                [
                                                    'what' => 'setData',
                                                    'when' => $rootPath . '.options.response_body',
                                                    'path' => $rootPath . '.options.response_body',
                                                    'is' => 1,
                                                    'on' => 'click',
                                                    'value' => 0,
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'label',
                                            'actions' => [
                                                [
                                                    'what' => 'tooltip',
                                                    'content' => 'Obfuscate the response body.',
                                                ],
                                            ],
                                            'attributes' => [
                                                'for' => 'response_body',
                                            ],
                                            'content' => 'Response Body',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        // Third div.
                        [
                            'type' => 'div',
                            'attributes' => [
                                'class' => 'my-3 fw-bold',
                            ],
                            'content' => 'Exclusion Keys:',
                        ],
                        // Div number 4.
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
                                                        [
                                                            'type' => 'th',
                                                            'content' => 'Key',
                                                        ],
                                                        [
                                                            'type' => 'th',
                                                            'content' => 'Exclude From',
                                                        ],
                                                        [
                                                            'type' => 'th',
                                                            'content' => 'Actions',
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'tbody',
                                            'content' => [
                                                'type' => 'Switch',
                                                'content' => $rootPath . '.options.keys_exclusions',
                                                'singleOption' => [
                                                    'load' => 'task_obfuscation_2_key_exclusion_item_row',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'div',
                                    'content' => 'There are no keys to exclude.',
                                    'attributes' => [
                                        'class' => 'text-muted fst-italic text-center m-2',
                                    ],
                                    'actions' => [
                                        [
                                            'what' => 'hide',
                                            'whenDataCountOf' => static::setWhenDataCountOf($rootPath),
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
                                                    'path' => $rootPath . '.options.keys_exclusions',
                                                    'value' => [
                                                        'key' => '',
                                                        'request_header' => 1,
                                                        'request_body' => 1,
                                                        'response_header' => 1,
                                                        'response_body' => 1,
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],

                        // Div number 5
                        [
                            'type'=> 'div',
                            'class' => 'row',
                            'content' => [
                                [
                                    'type' => 'input',
                                    'attributes' => [
                                        'id' => 'preserve_length',
                                        'type' => 'checkbox',
                                        'class' => 'me-1',
                                        'checked' => $rootPath . '.options.preserve_length',
                                    ],
                                    'actions' => [
                                        [
                                            'what' => 'setData',
                                            'when' => $rootPath . '.options.preserve_length',
                                            'path' => $rootPath . '.options.preserve_length',
                                            'is' => 0,
                                            'on' => 'click',
                                            'value' => 1,
                                        ],
                                        [
                                            'what' => 'setData',
                                            'when' => $rootPath . '.options.preserve_length',
                                            'path' => $rootPath . '.options.preserve_length',
                                            'is' => 1,
                                            'on' => 'click',
                                            'value' => 0,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'label',
                                    'actions' => [
                                        [
                                            'what' => 'tooltip',
                                            'content' =>
                                                'Check to preserve data size. Uncheck for fixed-size obfuscation.',
                                        ],
                                    ],
                                    'attributes' => [
                                        'for' => 'preserve_length',
                                    ],
                                    'content' => 'Preserve Length',
                                ],
                            ],
                        ],
                    ],
                ],
            ],

            'listForms' => [
                'task_obfuscation_2_key_exclusion_item_row' => [
                    'type' => 'tr',
                    'content' => [
                        [
                            'type' => 'td',
                            'content' => [
                                [
                                    'type' => 'TextField',
                                    'placeholder' => 'Enter a key to exclude.',
                                    'dataLocation' => '~.key',
                                ],
                            ],
                        ],
                        [
                            'type' => 'td',
                            'content' => [
                                [
                                    'type' => 'div',
                                    'content' => [
                                        [
                                            'type' => 'div',
                                            'actions' => [
                                                [
                                                    'what' => 'hide',
                                                    'when' => $rootPath. '.options.request_header',
                                                    'is' => 0,
                                                ],
                                            ],
                                            'content' => [
                                                [
                                                    'type' => 'input',
                                                    'attributes' => [
                                                        'class' => 'mx-1',
                                                        'type' => 'checkbox',
                                                        'checked' => '~.request_header',
                                                    ],
                                                    'actions' => [
                                                        [
                                                            'what' => 'tooltip',
                                                            'content' => 'Request header.',
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.request_header',
                                                            'path' => '~.request_header',
                                                            'is' => 0,
                                                            'on' => 'click',
                                                            'value' => 1,
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.request_header',
                                                            'path' => '~.request_header',
                                                            'is' => 1,
                                                            'on' => 'click',
                                                            'value' => 0,
                                                        ],
                                                    ],
                                                ],
                                                [
                                                    'type' => 'label',
                                                    'content' => 'Request Header',
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'div',
                                            'actions' => [
                                                [
                                                    'what' => 'hide',
                                                    'when' => $rootPath . '.options.request_body',
                                                    'is' => 0,
                                                ],
                                            ],
                                            'content' => [
                                                [
                                                    'type' => 'input',
                                                    'attributes' => [
                                                        'class' => 'mx-1',
                                                        'type' => 'checkbox',
                                                        'checked' => '~.request_body',
                                                    ],
                                                    'actions' => [
                                                        [
                                                            'what' => 'tooltip',
                                                            'content' =>
                                                                'Check to exclude the key from'
                                                                    . ' request body obfuscation.'
                                                                    . ' Uncheck to obfuscate it.',
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.request_body',
                                                            'path' => '~.request_body',
                                                            'is' => 0,
                                                            'on' => 'click',
                                                            'value' => 1,
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.request_body',
                                                            'path' => '~.request_body',
                                                            'is' => 1,
                                                            'on' => 'click',
                                                            'value' => 0,
                                                        ],
                                                    ],
                                                ],
                                                [
                                                    'type' => 'label',
                                                    'content' => 'Request Body',
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'div',
                                            'actions' => [
                                                [
                                                    'what' => 'hide',
                                                    'when' => $rootPath . '.options.response_header',
                                                    'is' => 0,
                                                ],
                                            ],
                                            'content' => [
                                                [
                                                    'type' => 'input',
                                                    'attributes' => [
                                                        'class' => 'mx-1',
                                                        'type' => 'checkbox',
                                                        'checked' => '~.response_header',
                                                    ],
                                                    'actions' => [
                                                        [
                                                            'what' => 'tooltip',
                                                            'content' =>
                                                                'Check to exclude the key from response header'
                                                                . ' obfuscation. Uncheck to obfuscate it.',
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.response_header',
                                                            'path' => '~.response_header',
                                                            'is' => 0,
                                                            'on' => 'click',
                                                            'value' => 1,
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.response_header',
                                                            'path' => '~.response_header',
                                                            'is' => 1,
                                                            'on' => 'click',
                                                            'value' => 0,
                                                        ],
                                                    ],
                                                ],
                                                [
                                                    'type' => 'label',
                                                    'content' => 'Response Header',
                                                ],
                                            ],
                                        ],
                                        [
                                            'type' => 'div',
                                            'actions' => [
                                                [
                                                    'what' => 'hide',
                                                    'when' => $rootPath . '.options.response_body',
                                                    'is' => 0,
                                                ],
                                            ],
                                            'content' => [
                                                [
                                                    'type' => 'input',
                                                    'attributes' => [
                                                        'class' => 'mx-1',
                                                        'type' => 'checkbox',
                                                        'checked' => '~.response_body',
                                                    ],
                                                    'actions' => [
                                                        [
                                                            'what' => 'tooltip',
                                                            'content' =>
                                                                'Check to exclude the key from response'
                                                                 . ' body obfuscation. Uncheck to obfuscate it.',
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.response_body',
                                                            'path' => '~.response_body',
                                                            'is' => 0,
                                                            'on' => 'click',
                                                            'value' => 1,
                                                        ],
                                                        [
                                                            'what' => 'setData',
                                                            'when' => '~.response_body',
                                                            'path' => '~.response_body',
                                                            'is' => 1,
                                                            'on' => 'click',
                                                            'value' => 0,
                                                        ],
                                                    ],
                                                ],
                                                [
                                                    'type' => 'label',
                                                    'content' => 'Response Body',
                                                ],
                                            ],
                                        ],
                                    ],
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

    /**
     * Obfuscates the body of an HTTP request or response using the specified obfuscation configuration.
     *
     * @param Body $streamBody
     *   The body of the HTTP request or response to be obfuscated.
     * @param array $config
     *   The obfuscation configuration, including the obfuscator and other parameters.
     */
    private function obfuscateBody(Body $streamBody, array $config): void
    {
        $clearBody = $streamBody->getBody();

        if (!$streamBody->isBodyAnalyzable()) {
            $obfuscatedBody = 'Body is not analyzable to be obfuscated adequately.';
        } else {
            $obfuscatedBody = $config['obfuscator']->obfuscate($clearBody, $config);
        }

        $streamBody->replaceBody($obfuscatedBody);
    }

    /**
     * Obfuscates the header of an HTTP request or response using the specified obfuscation configuration.
     *
     * @param Header $header
     *   The header of the HTTP request or response to be obfuscated.
     * @param array $config
     *   The obfuscation configuration, including the obfuscator and exclusions.
     *
     * @throws UnmetDependencyException
     */
    private function obfuscateHeader(Header $header, array $config): void
    {
        $config['exclusions'] = $config['exclusions'] ?? [];

        foreach ($header->getHeadersForRequest() as $key => $value) {
            if (!in_array($key, $config['exclusions'])) {
                $obfuscatedValue = $config['obfuscator']->obfuscate($value, $config);
                $header->replaceHeader($key, $obfuscatedValue);
            }
        }
    }

    /**
     * Processes and obfuscates the body of an HTTP request or response.
     *
     * If the specified body scope is enabled in the options, it configures the exclusions
     * and the expected content type, then obfuscates the body content using the provided obfuscation configuration.
     *
     * @param array $options
     *   The task options, including body-related configurations.
     * @param string $bodyScope
     *   The scope of the body ('request_body' or 'response_body').
     * @param Header $header
     *   The header of the HTTP request or response, used to configure content type.
     * @param Body $body
     *   The body of the HTTP request or response to be obfuscated.
     * @param array $config
     *   The obfuscation configuration, including the obfuscator and exclusions.
     */
    private function processBody(
        array $options,
        string $bodyScope,
        Header $header,
        Body &$body,
        array $config
    ): void {
        if (!empty($options[$bodyScope])) {
            $this->configureExclusionsFromOptions($options, $bodyScope, $config);
            $this->configureExpectedContentType($header, $config);
            $this->obfuscateBody($body, $config);
        }
    }

    /**
     * Processes and obfuscates the header of an HTTP request or response.
     *
     * If the specified header scope is enabled in the options, it configures the exclusions and
     * resets the expected content type, then obfuscates the header values using the provided obfuscation configuration.
     *
     * @param array $options
     *   The task options, including header-related configurations.
     * @param string $headerScope
     *   The scope of the header ('request_header' or 'response_header').
     * @param Header $header
     *   The header of the HTTP request or response to be obfuscated.
     * @param array $config
     *   The obfuscation configuration, including the obfuscator and exclusions.
     *
     * @throws UnmetDependencyException
     */
    private function processHeader(
        array $options,
        string $headerScope,
        Header &$header,
        array $config
    ): void {
        if (!empty($options[$headerScope])) {
            $this->configureExclusionsFromOptions($options, $headerScope, $config);
            $this->resetExpectedConfigContentType($config);
            $this->obfuscateHeader($header, $config);
        }
    }

    /**
     * Resets the 'expectedContentType' configuration value to an empty string.
     *
     * @param array $config
     *   The obfuscation configuration array is passed by reference.
     *   Resets the 'expectedContentType' key to an empty string, ensuring it is not considered during obfuscation.
     */
    private function resetExpectedConfigContentType(array &$config)
    {
        $config['expectedContentType'] = '';
    }

    /**
     * Generates a JSONPath expression based on the provided root path.
     *
     * The expression is used to count the number of elements in the 'keys_exclusions' array.
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
            return '$.' . substr($rootPath, 3) . '.options.keys_exclusions[*]';
        }

        if (str_starts_with($rootPath, '~.')) {
            return '$..' . substr($rootPath, 2) . '.options.keys_exclusions[*]';
        }

        return '$..options.keys_exclusions[*]';
    }
}
