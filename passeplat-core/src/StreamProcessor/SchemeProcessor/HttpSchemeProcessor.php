<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor;

use Dakwamine\Component\RootDependencyDefinition;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Handler\Proxy;
use GuzzleHttp\Handler\StreamHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\RequestOptions;
use GuzzleHttp\TransferStats;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\InitiatorRequestBodyAnalyzer;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\HeaderAnalyzer;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\HeaderType;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\JsonHeaderAnalyzer;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\HttpWebServiceStatus;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\WebServiceStatusBase;
use PassePlat\Core\PassePlatResponseFactory;
use PassePlat\Core\PassePlatResponseFactoryInterface;
use PassePlat\Core\WebService\WebServiceInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * HTTP(s) scheme processor.
 */
class HttpSchemeProcessor extends SchemeProcessor
{
    /**
     * Guzzle PSR7 HTTP Client.
     *
     * @var Client|null
     */
    private ?Client $httpClient;

    /**
     * PassePlat response factory.
     *
     * @var PassePlatResponseFactoryInterface|null
     */
    private ?PassePlatResponseFactoryInterface $passePlatResponseFactory;

    /**
     * This is a reimplementation of Utils::chooseHandler() from GuzzleHttp.
     *
     * It will return the cURL handlers if available,
     * and only if not, it will return the stream handler.
     *
     * @return callable(RequestInterface, array): PromiseInterface
     *   Returns the best handler for PassePlat: cURL, and if not available, the native PHP stream handler.
     *
     * @throws \RuntimeException
     *   If no viable Handler is available.
     */
    public static function chooseHandler(): callable
    {
        $handler = null;
        if (\function_exists('curl_multi_exec') && \function_exists('curl_exec')) {
            $handler = Proxy::wrapSync(new CurlMultiHandler(), new CurlHandler());
        } elseif (\function_exists('curl_exec')) {
            $handler = new CurlHandler();
        } elseif (\function_exists('curl_multi_exec')) {
            $handler = new CurlMultiHandler();
        }

        if (!$handler && \ini_get('allow_url_fopen')) {
            $handler = new StreamHandler();
        }

        if (!$handler) {
            throw new \RuntimeException(
                'GuzzleHttp requires cURL, or the allow_url_fopen ini setting.'
            );
        }

        return $handler;
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(PassePlatResponseFactory::class, $this->passePlatResponseFactory);
        return $definitions;
    }

    protected function getHandledSchemes(): array
    {
        return ['http', 'https'];
    }

    /**
     * Gets the HTTP client.
     *
     * @return Client
     */
    protected function getHttpClient(): Client
    {
        if (empty($this->httpClient)) {
            // TODO: cette méthode permet de switcher vers cURL. À rendre configurable.
            if (true) {
                // TODO: pour l'instant, le client par défaut de Guzzle (PHP Stream) est utilisé prioritairement.
                //  cURL fonctionne, mais sur un test local d'un contenu de 1.25Mo,
                //  il a été 4x plus lent que le client par défaut.
                //  À étudier pourquoi avant de mettre cURL en production par défaut.
                $this->httpClient = new Client();
                return $this->httpClient;
            }

            // We use a custom handler stack to enforce the use of cURL.
            $handler = HandlerStack::create(static::chooseHandler());
            $this->httpClient = new Client(['handler' => $handler]);
        }

        return $this->httpClient;
    }

    public function processRequest(
        ServerRequestInterface $request,
        AnalyzableContent $analyzableContent,
        $destinationUrl,
        WebServiceInterface $webService
    ): void {
        // HTTP method.
        $method = $request->getMethod();

        // Request general info, contains additional info in sub-components (headers, body, etc).
        $requestInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);

        /** @var Body $body */
        $body = $requestInfo->addComponentByClassName(Body::class);
        $body->addComponentByClassName(InitiatorRequestBodyAnalyzer::class);

        // Info: Calling this here could potentially delay request sending to destination service.
        // (Depends on how the server handles the incoming request stream: does the script execution starts when the
        // stream is complete, or may it start with an incomplete data? For now, we suppose the former is what happens.)
        $body->write($request->getBody()->getContents());

        // Header analysis / logging component.
        /** @var Header $headerListComponent */
        $headerListComponent = $requestInfo->addComponentByClassName(Header::class);

        /** @var HeaderAnalyzer $initiatorHeadersAnalyzer */
        $initiatorHeadersAnalyzer = $headerListComponent->addComponentByClassName(JsonHeaderAnalyzer::class);
        $initiatorHeadersAnalyzer->setHeaderType(HeaderType::INITIATOR_REQUEST_HEADERS);

        // TODO: allow x-passeplat headers.
        // Copy the headers.
        $originalHeaders = $request->getHeaders();

        // Those headers must be removed for passeplat to work.
        // They are handled by the new connection created by the http client.
        // TODO: make those disabled headers configurable (with host and connection mandatory).
        $originalHeadersToDisable = [
            // Unset the host header because it corresponds to the passeplat server host name, not the destination one.
            'host',
            // Connection header (especially keep-alive, other values untested) blocks the back stream read.
            'connection',
        ];

        // Filter out unwanted headers.
        foreach ($originalHeaders as $originalHeaderKey => $originalHeaderValues) {
            if (in_array(strtolower($originalHeaderKey), $originalHeadersToDisable, true)) {
                continue;
            }

            $headerListComponent->addHeaderFieldEntry($originalHeaderKey, $originalHeaderValues);
        }

        /** @var Timing $destinationResponseWaitComponent */
        $destinationResponseWaitComponent = $analyzableContent
            ->getComponentByClassName(Timing::class, true);

        /** @var HttpWebServiceStatus $webServiceStatus */
        $webServiceStatus = $analyzableContent->addComponentByClassName(HttpWebServiceStatus::class);

        // Allow other code parts to alter some request options before calling the destination web service.
        $webService->executeTasksForEvent(
            WebServiceInterface::PHASE__DESTINATION_REQUEST_PREPARATION,
            $analyzableContent
        );

        $sendFallbackOrFailureResponse = function () use (
            $analyzableContent,
            $webService,
            $webServiceStatus,
            $destinationResponseWaitComponent
        ) {
            // Start by setting the web service status to not reachable.
            // The NOT_REACHABLE status is a special one, useful to distinguish a 504 Gateway Timeout error sent
            // by the destination service from either a real connection failure or an anticipated cut due to
            // a task that decided to stop the request.
            // This can be overriden by the fallback response if there is one.
            $webServiceStatus->setStatus(WebServiceStatusBase::NOT_REACHABLE);

            // We first try to check if we have a fallback response to send to the initiator.
            // If not, we send a generic error response and trigger the destination reach failure event.
            /** @var ResponseInfo $responseInfo */
            $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);

            if (empty($responseInfo) || empty($responseInfo->getResponse())) {
                // No response info component was added by the previous tasks.
                // We don't have any fallback response to send to the initiator.
                // Send the generic error response.
                $this->passePlatResponseFactory->emitDestinationFailureResponse($analyzableContent);
                $webService->executeTasksForEvent(
                    WebServiceInterface::PHASE__DESTINATION_REACH_FAILURE,
                    $analyzableContent
                );

                return;
            }

            $response = $responseInfo->getResponse();

            // Emit the fallback response.
            $this->passePlatResponseFactory->emitResponse(
                $response,
                $analyzableContent,
                function () use ($webService, $analyzableContent) {
                    // Launch the tasks on started receiving, as if it was a real response.
                    // @todo : collistion possible avec d'autres événements qui doivent éviter de réagir dans ce cas.
                    $webService->executeTasksForEvent(
                        WebServiceInterface::PHASE__STARTED_RECEIVING,
                        $analyzableContent
                    );
                }
            );
            
            $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STOP); 

            // Launch the tasks on emitted response, as if it was a real response.
            $webService->executeTasksForEvent(WebServiceInterface::PHASE__EMITTED_RESPONSE, $analyzableContent);
        };

        if ($analyzableContent->getExecutionInfo('stopRequest')) {
            // One of the tasks prevented PassePlat to send the request to the destination service.
            $destinationResponseWaitComponent->setMicrotime(Timing::STEP__START);
            $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STARTED_RECEIVING);
                 

            $sendFallbackOrFailureResponse();
            return;
        }

        // We can now send the request to the destination service.
        // The STEP__START time will be set again by the http client when it will return the response handle.
        // But for now, we set it to the current time.
        $destinationResponseWaitComponent->setMicrotime(Timing::STEP__START);

        try {
            // Send the request to the real destination.
            $response = $this->getHttpClient()->request(
                $method,
                $destinationUrl,
                [
                    RequestOptions::BODY => $requestInfo
                        ->getComponentByClassName(Body::class)
                        ->getBody(),
                    RequestOptions::HEADERS => $headerListComponent->getHeadersForRequest(),

                    // Disable exceptions on HTTP errors (>= 400) because their failure may be expected by the
                    // initiator.
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::ON_STATS => function (TransferStats $stats) use (
                        $destinationResponseWaitComponent
                    ) {
                        // Set the request duration.
                        $destinationResponseWaitComponent
                            ->setMicrotime(Timing::DURATION__DESTINATION_ROUND_TRIP, $stats->getTransferTime());
                    },
                    // This option simplifies chunks handling: we don't need nor want to care about chunked
                    // Transfer-Encoding header coming from the destination service (they have special handling which is
                    // cumbersome, see https://en.wikipedia.org/wiki/Chunked_transfer_encoding#Format).
                    // If we MUST handle "Transfer-Encoding: chunked" header coming from the destination service, simply
                    // remove this option. PassePlatResponseFactory::emitResponse() already handles chunked bodies with
                    // a simple implementation which could be enhanced if we really want to support chunks (this means
                    // we have to intercept the response chunks coming from the destination service before Guzzle
                    // rewrites them into a proper stream).
                    RequestOptions::STREAM => true,
                ]
            );
        } catch (GuzzleException $e) {
            // This means we did not receive any response or Guzzle could not do anything useful.
            $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STARTED_RECEIVING);
            $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STOP);

            $sendFallbackOrFailureResponse();
            return;
        }
        // At this stage, the response started streaming back to passeplat from the destination web service.
        $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STARTED_RECEIVING);

        // Emit the destination response.
        $this->passePlatResponseFactory->emitResponse(
            $response,
            $analyzableContent,
            function () use ($webService, $analyzableContent) {
                // Allow tasks to edit the destination service response
                // before sending them to the origin service.
                // Some tasks may defer response sending such as the ones which alter content
                // (e.g. full content is needed to replace strings).
                $webService->executeTasksForEvent(
                    WebServiceInterface::PHASE__STARTED_RECEIVING,
                    $analyzableContent
                );
            }
        );

        // Data transferred.
        $destinationResponseWaitComponent->setMicrotime(Timing::STEP__STOP);

        // Sets the web service status (typically one of 1XX, 2XX, 3XX, 4XX, 5XX).
        $webServiceStatus->setStatusFromResponse($response);

        // Launch the tasks on emitted response.
        $webService->executeTasksForEvent(WebServiceInterface::PHASE__EMITTED_RESPONSE, $analyzableContent);
    }
}
