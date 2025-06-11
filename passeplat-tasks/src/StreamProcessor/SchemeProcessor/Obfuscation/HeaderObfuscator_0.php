<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\Obfuscation;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;

/**
 * Obfuscates headers containing a sensitive key.
 */
class HeaderObfuscator_0 extends TaskHandlerBase
{
    /**
     * The list of sensitive keys, in lowercase, to obfuscate in logging.
     *
     * @var string[]
     */
    private array $keysToObfuscate = [
        'access-control-expose-headers',
        'authorization',
        'content-disposition',
        'cookie',
        'etag',
        'location',
        'proxy-authorization',
        'referer',
        'server',
        'set-cookie',
        #'user-agent',
        'www-authenticate',
        'x-forwarded-for',
        'x-request-id',
    ];

    public static function getFormData(?array $providedData = null): array
    {
        $defaultData = [];

        return static::replaceFormData($defaultData, $providedData);
    }

    public static function getFormDefinition(string $rootPath = '~'): array
    {
        return [
            'renderView' => [
                'type' => 'div',

                'attributes' => [
                    'class' => '',
                ],

                'content' => [
                    [
                        'type' => 'h1',
                        'content' => 'The list of sensitive keys:',
                    ],
                    [
                        'type' => 'ul',
                        'content' => [
                            ['type' => 'li', 'content' => 'access-control-expose-headers'],
                            ['type' => 'li', 'content' => 'authorization'],
                            ['type' => 'li', 'content' => 'content-disposition'],
                            ['type' => 'li', 'content' => 'cookie'],
                            ['type' => 'li', 'content' => 'etag'],
                            ['type' => 'li', 'content' => 'location'],
                            ['type' => 'li', 'content' => 'proxy-authorization'],
                            ['type' => 'li', 'content' => 'referer'],
                            ['type' => 'li', 'content' => 'server'],
                            ['type' => 'li', 'content' => 'set-cookie'],
                            ['type' => 'li', 'content' => 'www-authenticate'],
                            ['type' => 'li', 'content' => 'x-forwarded-for'],
                            ['type' => 'li', 'content' => 'x-request-id'],
                        ],

                    ],
                ],
            ],
            'listForms' => [],
        ];
    }

    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {
        $infoComponentClasses = [
            RequestInfo::class,
            ResponseInfo::class,
        ];

        foreach ($infoComponentClasses as $infoComponentClass) {
            $infoComponent = $analyzableContent->getComponentByClassName($infoComponentClass);

            if (!empty($infoComponent)) {
                // Get all headers from request and response.
                /** @var Header $infoHeader */
                $infoHeader = $infoComponent->getComponentByClassName(Header::class);

                if (!empty($infoHeader)) {
                    $this->obfuscateHeader($infoHeader);
                }
            }
        }
    }

    /**
     * Obfuscates headers containing a sensitive key.
     *
     * @param Header $header
     *   The header list object.
     *
     * @throws UnmetDependencyException
         Failed to replace header.
     */
    public function obfuscateHeader(Header $header): void
    {
        foreach ($header->getHeadersForRequest() as $key => $value) {
            if (in_array(strtolower($key), $this->keysToObfuscate)) {
                $header->replaceHeader($key, '#######');
            }
        }
    }
}
