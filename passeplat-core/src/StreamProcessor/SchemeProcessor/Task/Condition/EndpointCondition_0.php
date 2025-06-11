<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use GuzzleHttp\Psr7\Uri;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\Tool\StringTools;

/**
 * Condition which checks the endpoint.
 */
class EndpointCondition_0 extends ConditionBase
{
    /**
     * This is the execution time limit for the matcher.
     */
    private const TIME_BOUND = 1e6;

    /**
     * The matcher execution start time.
     *
     * @var float
     */
    private float $startTime;

    /**
     * It escapes special characters, asterisks excluded.
     *
     * @param $input string
     *   String to escape.
     *
     * @return string
     *   Escaped string.
     */
    private function escapeUrlPattern(string $input): string
    {
        return StringTools::preg_quote($input, ['*']);
    }

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [
            'status' => 'normal',
            'options' => [
                'urlPatterns' => [],
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
                            'defaultActiveKey' => 'urlPatterns',
                        ],
                        'tabs' => [
                            [
                                'attributes' => [
                                    'eventKey' => 'urlPatterns',
                                    'title' => 'URL Patterns',
                                ],
                                'content' => [
                                    'load' => 'urlPatterns_tab',
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
                'urlPatterns_tab' => [
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
                                                    ['type' => 'th', 'content' => 'URL Pattern'],
                                                    ['type' => 'th', 'content' => 'Actions'],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'type' => 'tbody',
                                        'content' => [
                                            'type' => 'Switch',
                                            'content' => $rootPath . '.options.urlPatterns',
                                            'singleOption' => [
                                                'load' => 'urlPattern_item_row',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            [
                                'type' => 'div',
                                'content' => 'There is no urlPattern definition.',
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
                                                'path' => $rootPath . '.options.urlPatterns',
                                                'value' => [
                                                    'pattern' => '',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'urlPattern_item_row' => [
                    'type' => 'tr',
                    'content' => [
                        [
                            'type' => 'td',
                            'content' => [
                                [
                                    'type' => 'TextField',
                                    'placeholder' => 'Enter a pattern such as /*/**/abc/',
                                    'dataLocation' => '~.pattern',
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
        $description['publicName'] = 'Endpoint';
        $description['id'] = 'endpoint';
        $description['version'] = '0';
        $description['appliesTo'] = ['task'];
        $description['optionsSchema']['urlPatterns'] = [
            'pattern' => 'string',
        ];
        return $description;
    }

    /**
     * Check if we have exceeded the TIME_BOUND since $startTime.
     *
     * @return bool
     *   True if the TIME_BOUND expired. False otherwise.
     */
    private function isTimeExpired(): bool
    {
        $elapsedTime = hrtime(true) - $this->startTime;
        return ($elapsedTime > static::TIME_BOUND);
    }

    /**
     * Matches the tokens with pattern recursively.
     *
     * It runs in an exponential time of O(1.6181^(|$pattern|+|$tokens|)).
     * Hence, there is the TIME_BOUND.
     *
     * @param string[] $pattern
     *   The pattern against which the tokens are matched.
     * @param int $i
     *   Index, initially set to zero, of an element in the array $pattern.
     * @param string[] $tokens
     *   The tokens against which the pattern is matched.
     * @param int $j
     *   Index, initially set to zero, of an element in the array $tokens.
     *
     * @return bool
     *   True if the pattern matches. False otherwise.
     *
     * @throws ConditionException The time expired.
     */
    private function matchRecursive(array $pattern, int $i, array $tokens, int $j): bool
    {
        if ($this->isTimeExpired()) {
            $message = 'The recursive matching from '
                . $this->getPluginDescription()['publicName']
                . ' has reached the time limit.';

            $this->addConditionExecutionTraceString($message);
            throw new ConditionException($message);
        }

        if ($j === count($tokens)) {
            return $i === count($pattern);
        }

        if ($i === count($pattern)) {
            return false;
        }

        if ($pattern[$i] === '*') {
            return $this->matchRecursive($pattern, $i + 1, $tokens, $j + 1);
        }

        // The case of '**' is the worst-case, leading to exponential time.
        if ($pattern[$i] === '**') {
            if ($this->matchRecursive($pattern, $i + 1, $tokens, $j + 1)) {
                return true;
            }

            return $this->matchRecursive($pattern, $i, $tokens, $j + 1);
        }

        // The case of tokens like 'abc', 'def', etc.
        if ($tokens[$j] === $pattern[$i]) {
            return $this->matchRecursive($pattern, $i + 1, $tokens, $j + 1);
        }

        return false;
    }

    protected function selfEvaluate(AnalyzableContent $analyzableContent): bool
    {
        /**
         * Checks if the URL path matches any of the patterns in the list.
         * Each pattern (specified by the 'pattern' key) is in the form '/asterisk/double asterisk/abc' where:
         * - 'abc' exactly matches an 'abc' token in the path,
         * - '*' matches any one token,
         * - '**' matches one or more tokens.
         */

        /** @var RequestInfo $requestInfo */
        $requestInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);

        $url = $requestInfo->getDestinationUrl();

        if (empty($url)) {
            throw new ConditionException('URL should not be empty.');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ConditionException('Invalid URL.');
        }

        // Retrieve the path from the URL.
        $uri = new Uri($url);
        $path = $uri->getPath();

        // Delete the slash both from the beginning and the end.
        $path = trim($path, '/');

        if ($path === '') {
            return false;
        }

        // Escape the special characters in the path.
        $escapedPath = preg_quote($path);

        // Segment the path.
        $tokens = explode('/', $escapedPath);

        // Retrieve the list of patterns from options.
        $urlPatterns = $this->options['urlPatterns'] ?? [];

        // If the option does not exist, or it is empty.
        if (empty($urlPatterns)) {
            return true;
        }

        // Start the timer which will be used to stop the recursive processing eventually.
        $this->startTime = hrtime(true);

        foreach ($urlPatterns as $urlPatternObject) {
            $urlPattern = $urlPatternObject['pattern'];

            // Delete the slash both from the beginning and the end.
            $urlPattern = trim($urlPattern, '/');

            $escapedUrlPattern = $this->escapeUrlPattern($urlPattern);

            // Segment the pattern.
            $pattern = explode('/', $escapedUrlPattern);

            if ($this->matchRecursive($pattern, 0, $tokens, 0)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generates a JSONPath expression based on the provided root path.
     *
     * The expression is used to count the number of elements in the 'urlPatterns' array.
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
            return '$.' . substr($rootPath, 3) . '.options.urlPatterns[*]';
        }

        if (str_starts_with($rootPath, '~.')) {
            return '$..' . substr($rootPath, 2) . '.options.urlPatterns[*]';
        }

        return '$..options.urlPatterns[*]';
    }
}
