<?php

namespace PassePlat\Openapi\StreamProcessor\SchemeProcessor\Task;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\Exception\Exception;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;
use PassePlat\Openapi\Tool\Har\FromRequestResponseHarEntryBuilder;
use PassePlat\Openapi\Tool\Har\Har;
use PassePlat\Openapi\Tool\OpenApi\OpenApiSpecHandler;
use PassePlat\Openapi\Tool\OpenApi\SpecUpdaterFromOpenApi;

/**
 * This task involves updating (or constructing if it doesn't exist) an OpenAPI specification file
 * by monitoring requests and responses.
 *
 * It is highly recommended to perform the task with a "rand" condition (a form of random sampling of streams).
 * This helps to avoid identical bulk requests by introducing variability in the selection of data to monitor.
 */
class OpenApiWatcher_0 extends TaskHandlerBase
{
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {
        //A temporary file name, initially not set.
        $addFileName = null;

        try {
            //To build a relevant OpenAPI, it is necessary to have both the request and the response, unlike validation.
            /** @var RequestInfo $requestInfo */
            $requestInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);
            if (empty($requestInfo)) {
                return;
            }

            /** @var ResponseInfo $responseInfo */
            $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);
            if (empty($responseInfo) || $this->isChunkedTransferEncoding($responseInfo)) {
                return;
            }

            // Get all headers and bodies from request and response.
            /** @var Header $requestHeader */
            $requestHeader = $requestInfo->getComponentByClassName(Header::class);

            /** @var Body $requestBody */
            $requestBody = $requestInfo->getComponentByClassName(Body::class);

            /** @var Header $responseHeader */
            $responseHeader = $responseInfo->getComponentByClassName(Header::class);

            /** @var Body $responseBody */
            $responseBody = $responseInfo->getComponentByClassName(Body::class);

            if (empty($responseBody) || !$responseBody->isBodyAnalyzable()) {
                return;
            }

            /** @var Har $har */
            $har = $analyzableContent->getComponentByClassName(Har::class, true);
            $har->init(1);

            // Set a strategy to add an entry.
            $requestHarEntry = new FromRequestResponseHarEntryBuilder();
            $har->setHarEntryBuilderStrategy($requestHarEntry);

            $params = [
                'requestInfo' => $requestInfo,
                'requestHeader' => $requestHeader,
                'requestBody' => $requestBody,
                'responseInfo' => $responseInfo,
                'responseHeader' => $responseHeader,
                'responseBody' => $responseBody
            ];

            $har->addEntry($params);

            if ($har->isFull()) {
                // Convert the HAR content into a temporary additional OpenAPI specification file.
                /** @var OpenApiSpecHandler $additionalOpenApi */
                $additionalOpenApi = $analyzableContent->addComponentByClassName(OpenApiSpecHandler::class);
                // Obtain a unique file name to avoid collision issues.
                $addFileName = $additionalOpenApi->getTemporaryFileName('yaml');
                $additionalOpenApi->init($addFileName);
                $additionalOpenApi->open();
                $har->generateOpenAPI($additionalOpenApi);

                // Updating the original OpenAPI specification file,
                // by merging it with the temporary additional specification file.
                /** @var OpenApiSpecHandler $baseOpenApi */
                $baseOpenApi = $analyzableContent->addComponentByClassName(OpenApiSpecHandler::class);
                $baseFileName = $additionalOpenApi->getHashId();
                $baseOpenApi->init($baseFileName);
                $baseOpenApi->open();

                $baseOpenApi->setSpecUpdaterStrategy(new SpecUpdaterFromOpenApi());
                $params['baseOpenAPI'] = $baseOpenApi;
                $params['additionalOpenAPI'] = $additionalOpenApi;
                $params['extensionOutput'] = 'yaml';
                $params['resolveReference'] = true;
                $baseOpenApi->update($params);

                // Deleting the temporary additional specification file.
                $additionalOpenApi->delete();
            }
        } catch (Exception|UnmetDependencyException $e) {
            // Attempt to remove the temporary file related to the additional OpenAPI, but not the base OpenAPI.
            if (file_exists($addFileName)) {
                unlink($addFileName);
            }
        }
    }

    public static function hasEnableForm(): bool
    {
        //TODO
        // Implement the jsonForm methods and delete this one.
        return false;
    }
}
