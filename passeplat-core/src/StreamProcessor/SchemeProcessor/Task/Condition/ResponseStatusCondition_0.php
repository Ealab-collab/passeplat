<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use Generator;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\Exception\ConditionException;

/**
 * Condition which checks server's HTTP response status code.
 */
class ResponseStatusCondition_0 extends ConditionBase
{
    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'status' => 'normal',
            'options' => [
                'invertResult' => false,
                'statuses' => '',
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
                        'content' => 'Statuses',
                    ],
                    [
                        'type' => 'TextField',
                        'attributes' => [
                            'class' => 'pb-2',
                        ],
                        'dataLocation' => $rootPath . '.options.statuses',
                        'placeholder' => 'Please input statuses as follows: 5XX, 200, 404',
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
        $description['publicName'] = 'Response status';
        $description['id'] = 'response_status';
        $description['version'] = '0';
        $description['appliesTo'] = ['task'];
        $description['optionsSchema']['statuses'] = [
            'type' => 'string',
            'max_chars' => 300,
            'default' => '',
        ];
        return $description;
    }

    /**
     * Check if there is a match between the server status and the status from the options.
     *
     * @param string $statusServer
     *   The server's HTTP response status code.
     *
     * @param string $statusFromOptions
     *   A status from the options.
     *
     * @return bool
     *   True if matched, false otherwise.
     */
    private function matchStatus(string $statusServer, string $statusFromOptions): bool
    {
        for ($i = 0; $i < 3; $i++) {
            if ($statusFromOptions[$i] === 'X' || $statusFromOptions[$i] === 'x') {
                continue;
            }

            if ($statusFromOptions[$i] === $statusServer[$i]) {
                continue;
            }

            return false;
        }

        return true;
    }

    protected function selfEvaluate(AnalyzableContent $analyzableContent): bool
    {
        /** @var ResponseInfo $response */
        $response = $analyzableContent->getComponentByClassName(ResponseInfo::class);

        // Note: validating the HTTP response status code from the server is not necessary.
        $statusCode = $response->getStatusCode();

        foreach ($this->statusGenerator() as $statusFromOptions) {
            // statusGenerator() generates only valid status codes.
            if ($this->matchStatus((string) $statusCode, $statusFromOptions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The generator yields the statuses from the options, one after another.
     *
     * @return Generator<string>
     *   Status codes.
     *
     * @throws ConditionException
     *   Invalid status code.
     */
    private function statusGenerator(): Generator
    {
        $statusesOption = $this->options['statuses'] ?? '';
        $statuses= explode(',', $statusesOption);

        if (count($statuses) > 100) {
            throw new ConditionException('Invalid Status Code.');
        }

        foreach ($statuses as $status) {
            $status = trim($status);

            if (!$this->validateStatus($status)) {
                throw new ConditionException('Invalid Status Code.');
            }

            yield $status;
        }
    }

    /**
     * Validates the given status code.
     *
     * Allowed values are HTTP standard response status codes
     * or status codes like 2XX.
     *
     * @param string $status
     *   The status code to validate.
     *
     * @return bool
     *   True if valid, false otherwise.
     */
    private function validateStatus(string $status): bool
    {
        return preg_match('/^[1-5](xx|\d{2})$/i', $status);
    }
}
