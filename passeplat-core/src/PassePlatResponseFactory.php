<?php

namespace PassePlat\Core;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Event\EventDispatcher;
use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use GuzzleHttp\Psr7\Utils;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\DestinationResponseBodyAnalyzer;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\HeaderField;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\HeaderAnalyzer;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\HeaderType;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\JsonHeaderAnalyzer;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\Exception\HttpHeaderException;
use PassePlat\Core\Psr7\JsonResponse;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Teapot\StatusCode;

/**
 * Response for PassePlat.
 */
class PassePlatResponseFactory extends ComponentBasedObject implements PassePlatResponseFactoryInterface
{
    /**
     * Bytes amount for each $stream->read() call. Is 10000 a good value?
     * @todo : in a config file.
     */
    const READ_BYTES = 10000;

    /**
     * Event dispatcher.
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Buffers the chunked response body.
     *
     * @param StreamInterface $stream
     *   Response stream.
     * @param AnalyzableContent|null $analyzableContent
     *   Analyzable content object.
     *
     * @return array
     *   The buffer is an array containing items to be echoed. The content may be incomplete, especially when
     *   the Body component couldn't memorize the entire stream content (too much data to process, in which case
     *   the body component returns false on isAnalyzable()).
     *   The buffer is an empty array when the $analyzableContent is not set (buffering is only useful when data
     *   can be collected and analyzed).
     */
    private function bufferChunkedResponseBody(
        StreamInterface $stream,
        AnalyzableContent $analyzableContent = null
    ): array {
        // This is used to retain the chunks while we are retrieving the content.
        // We need this because task handlers may be able to edit the body content, and we don't know in advance the
        // final width.
        $localBuffer = [];

        if (empty($analyzableContent)) {
            // Leave everything to echoChunkedResponseBody().
            return [];
        }

        try {
            /** @var ResponseInfo $responseInfo */
            $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class, true);

            // It's supposed to be the first and only Body component.
            /** @var Body $bodyComponent */
            $bodyComponent = $responseInfo->getComponentByClassName(Body::class, true);
        } catch (UnmetDependencyException $e) {
            // Do not buffer anything. Leave the rest of the code handle the stream.
            return [];
        }

        while (true) {
            $chunk = $this->buildNextChunkFromStream($stream);

            if ($chunk === false) {
                // Reached the end of stream.
                break;
            }

            if ($chunk === true) {
                // Not a real chunk. Simply seek the next chunk.
                continue;
            }

            // Retain the info.
            $localBuffer[] = $chunk['length'];
            $localBuffer[] = $chunk['content'];

            // Put this for analysis.
            $bodyComponent->write($chunk['content']);

            if (!$bodyComponent->isBodyAnalyzable()) {
                // We've reached the limit. Don't feed the buffer anymore.
                break;
            }
        }

        return $localBuffer;
    }

    /**
     * Buffers the normal (non-chunked) response body.
     *
     * @param StreamInterface $stream
     *   Response stream.
     * @param AnalyzableContent|null $analyzableContent
     *   Analyzable content object.
     *
     * @return array
     *   The buffer is an array containing items to be echoed. The content may be incomplete, especially when
     *   the Body component couldn't memorize the entire stream content (too much data to process, in which case
     *   the body component returns false on isAnalyzable()).
     *   The buffer is an empty array when the $analyzableContent is not set (buffering is only useful when data
     *   can be collected and analyzed).
     */
    private function bufferNormalResponseBody(
        StreamInterface $stream,
        AnalyzableContent $analyzableContent = null
    ): array {
        if (empty($analyzableContent)) {
            // No need to use this system. echoNormalResponseBody() will echo the body.
            return [];
        }

        // This is used to retain the slices while we are retrieving the content.
        // We need this because task handlers may be able to edit the body content, and we don't know in advance the
        // final width.
        // TODO: les réponses normales peuvent avoir un $stream->getSize() prédéfini. Essayer de faire confiance ?
        $localBuffer = [];

        try {
            /** @var ResponseInfo $responseInfo */
            $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class, true);

            // It's supposed to be the first and only Body component.
            /** @var Body $bodyComponent */
            $bodyComponent = $responseInfo->getComponentByClassName(Body::class, true);
        } catch (UnmetDependencyException $e) {
            // Serious error. Try to at least echo the response.
            return [];
        }

        while (!$stream->eof()) {
            $slice = $stream->read(static::READ_BYTES);
            $localBuffer[] = $slice;
            $bodyComponent->write($slice);

            if (!$bodyComponent->isBodyAnalyzable()) {
                // Stop buffering.
                break;
            }
        }

        return $localBuffer;
    }

    /**
     * Builds the next "chunk" of data for chunked transfer-encoding streams from the given stream.
     *
     * @param StreamInterface $stream
     *   The stream to create the build.
     *
     * @return array|bool
     *   Array containing strings to echo (length and content lines). Returns true if the stream is not ended yet,
     *   and false if the stream reached the end.
     */
    private function buildNextChunkFromStream(StreamInterface $stream)
    {
        if ($stream->eof()) {
            return false;
        }

        // Get the line from stream. One can limit buffer length on readLine if necessary.
        $line = Utils::readLine($stream);

        $length = strlen($line);

        if (empty($length)) {
            // Empty data retrieval.
            // Return value is used to know if we have reached the end of the stream.
            return !$stream->eof();
        }

        return [
            'length' => dechex($length),
            'content' => $line,
        ];
    }

    /**
     * Builds the next "chunk" of data for chunked transfer-encoding streams for the given string.
     *
     * @param string $line
     *   The string to create the build.
     *
     * @return array
     *   Array containing strings to echo (length and content lines).
     */
    private function buildNextChunkFromString(string $line): array
    {
        $length = strlen($line);

        return [
            'length' => dechex($length),
            'content' => $line,
        ];
    }

    /**
     * Echoes the chunked response body.
     *
     * @param $chunkedBodyStartedBuffer
     *   Expected to be an array. It contains the first lines to output.
     * @param StreamInterface $stream
     *   The stream of the content to transfer.
     * @param AnalyzableContent|null $analyzableContent
     *   Analyzable content containing the Body component.
     */
    public function echoChunkedResponseBody(
        $chunkedBodyStartedBuffer,
        StreamInterface $stream,
        AnalyzableContent $analyzableContent = null
    ): void {
        if (!empty($analyzableContent)) {
            try {
                // Get / initialize the body component.
                /** @var ResponseInfo $responseInfo */
                $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class, true);
                /** @var Body $bodyComponent */
                $bodyComponent = $responseInfo->getComponentByClassName(Body::class, true);
            } catch (UnmetDependencyException $e) {
                // Simply ignore the error to ensure the response is sent.
            }
        }

        // TODO: shortcut mode à développer pour utiliser systématiquement le buffer.
        if (!empty($bodyComponent) && $bodyComponent->isBodyAnalyzable()) {
            // The body is considered full and usable. Use this instead of the buffer.
            foreach ($this->buildNextChunkFromString($bodyComponent->getBody()) as $chunkPart) {
                // Echoes the length, then the content.
                echo $chunkPart . "\r\n";
            };

            // Send the termination chunk.
            echo "0\r\n";
            echo "\r\n";

            // The content is fully sent.
            return;
        }

        if (is_array($chunkedBodyStartedBuffer)) {
            foreach ($chunkedBodyStartedBuffer as $item) {
                // Echo the original buffer values.
                echo $item . "\r\n";
            }
        }

        while (true) {
            // Retrieve the next values from the chunked stream.
            $chunk = $this->buildNextChunkFromStream($stream);

            if ($chunk === false) {
                // Reached the end of stream.
                break;
            }

            if ($chunk === true) {
                // Not a real chunk. Simply seek the next chunk.
                continue;
            }

            $line = $chunk['length'] . "\r\n" . $chunk['content'] . "\r\n";

            echo $line;

            if (!empty($bodyComponent)) {
                // Only put the real content for analysis.
                $bodyComponent->write($chunk['content']);
            }
        }

        // Send the termination chunk.
        echo "0\r\n";
        echo "\r\n";
    }

    /**
     * Echoes the normal response body.
     *
     * @param $startedBuffer
     *   Expected to be an array. It contains the first lines to output.
     * @param StreamInterface $stream
     *   The stream of the content to transfer.
     * @param AnalyzableContent|null $analyzableContent
     *   Analyzable content containing the Body component.
     */
    public function echoNormalResponseBody(
        $startedBuffer,
        StreamInterface $stream,
        AnalyzableContent $analyzableContent = null
    ): void {
        if (!empty($analyzableContent)) {
            try {
                // Get / initialize the body component.
                /** @var ResponseInfo $responseInfo */
                $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class, true);
                /** @var Body $bodyComponent */
                $bodyComponent = $responseInfo->getComponentByClassName(Body::class, true);
            } catch (UnmetDependencyException $e) {
                // Simply ignore the error to ensure the response is sent.
            }
        }

        // TODO: shortcut mode à développer pour utiliser systématiquement le buffer.
        if (!empty($bodyComponent) && $bodyComponent->isBodyAnalyzable()) {
            // The body is considered full and usable. Use this instead of the buffer.
            echo $bodyComponent->getBody();

            // The content is fully sent.
            return;
        }

        if (is_array($startedBuffer)) {
            foreach ($startedBuffer as $item) {
                // Echo the original buffer values.
                echo $item;
            }
        }

        // Send the remaining data, if any.
        while (!$stream->eof()) {
            $slice = $stream->read(static::READ_BYTES);
            echo $slice;

            if (!empty($bodyComponent)) {
                $bodyComponent->write($slice);
            }
        }
    }

    public function emitDestinationFailureResponse(
        AnalyzableContent $analyzableContent = null,
        callable $tasksBeforeEmit = null
    ) {
        $body = [
            'message' => 'Destination service could not be reached or did not deliver a timely response.',
        ];
        $response = new JsonResponse(StatusCode::SERVICE_UNAVAILABLE, [], $body);
        $this->emitResponse($response, $analyzableContent, $tasksBeforeEmit);
    }

    public function emitResponse(
        ResponseInterface $response,
        AnalyzableContent $analyzableContent = null,
        callable $tasksBeforeEmit = null
    ) {
        /**
         * Implementation based on https://stackoverflow.com/questions/33304790/emit-a-response-with-psr-7.
         */
        if (headers_sent()) {
            throw new HttpHeaderException('Tried to send a response, but headers were already sent.');
        }

        // Reset the output buffer for safety.
        while (!empty(ob_list_handlers())) {
            ob_end_flush();
        }

        // Start the default output buffer.
        ob_start();

        if (!empty($analyzableContent)) {
            // Prepare the headers for edition.
            /** @var ResponseInfo $responseInfo */
            $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class, true);
            $responseInfo->setResponse($response);

            // Load destination response headers.
            /** @var Header $destinationResponseHeaderList */
            $destinationResponseHeaderList = $responseInfo->getComponentByClassName(Header::class, true);

            /** @var HeaderAnalyzer $destinationResponseHeadersAnalyzer */
            $destinationResponseHeadersAnalyzer = $destinationResponseHeaderList
                ->addComponentByClassName(JsonHeaderAnalyzer::class);
            $destinationResponseHeadersAnalyzer->setHeaderType(HeaderType::DESTINATION_REPONSE_HEADERS);

            foreach ($response->getHeaders() as $headerKey => $headerValues) {
                $destinationResponseHeaderList->addHeaderFieldEntry($headerKey, $headerValues);
            }
        }

        // Get the body stream.
        $stream = $response->getBody();

        $transferEncodingHeader = $response->getHeader('transfer-encoding');
        $isChunkEncoded = in_array('chunked', $transferEncodingHeader, true);

        // TODO: trouver un moyen ("shortcut to buffer mode") pour savoir si on peut skip l'étape du buffering
        //       sans avoir à attendre d'avoir tout reçu du destinataire ou que le body dise qu'il y a trop de données.
        // Initialize the optional buffer.
        $buffer = [];

        if (!empty($tasksBeforeEmit)) {
            // A callback has been defined. Buffer the response before executing the tasks.
            // TODO: faire en sorte que l'étape du buffering puisse être court-circuitée si aucune tâche de callback
            //       n'agit sur le contenu de la réponse.
            if ($isChunkEncoded) {
                // Chunked body special handling.
                $buffer = $this->bufferChunkedResponseBody($stream, $analyzableContent);
            } else {
                $buffer = $this->bufferNormalResponseBody($stream, $analyzableContent);
            }

            $tasksBeforeEmit();
        }

        if (!$analyzableContent) {
            $statusCode = $response->getStatusCode();
        } else {
            // Check if there is a status code override.
            // This override is used to force a specific status code,
            // even if the destination service returned another one.
            $statusCode = $analyzableContent->getExecutionInfo('statusCode');

            if (empty($statusCode)
                || !filter_var($statusCode, FILTER_VALIDATE_INT)
                || $statusCode < 100
                || $statusCode >= 600
            ) {
                // The status code is invalid. Use the original one.
                $statusCode = $response->getStatusCode();
            }
        }

        // Prepare the "status line" header.
        $statusLine = sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $statusCode,
            $response->getReasonPhrase()
        );

        // Set the header with forced header replacement if it was already set.
        // This special header is not editable and will always contain the original value from destination service.
        header($statusLine, true);

        if (!empty($analyzableContent)) {
            // Use the editable header components.
            $writeHeadersFromAnalyzableContent = function () use ($analyzableContent) {
                // Retrieve again the components for safety.
                /** @var ResponseInfo $responseInfo */
                $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);

                if (empty($responseInfo)) {
                    // Very unlikely to happen.
                    return;
                }

                /** @var Header $destinationResponseHeaderList */
                $destinationResponseHeaderList = $responseInfo->getComponentByClassName(Header::class);

                if (empty($destinationResponseHeaderList)) {
                    // Also very unlikely to happen.
                    return;
                }

                /** @var HeaderField[] $finalHeaders */
                $finalHeaders = $destinationResponseHeaderList->getComponentsByClassName(HeaderField::class);

                foreach ($finalHeaders as $h) {
                    $responseHeader = sprintf(
                        '%s: %s',
                        $h->getName(),
                        $h->getValue()
                    );

                    // Headers may cumulate, so we do not need to enforce replacement.
                    header($responseHeader, false);
                }
            };

            $writeHeadersFromAnalyzableContent();
        } else {
            // Simply transfer the original response headers coming from destination.
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeader = sprintf(
                    '%s: %s',
                    $name,
                    $response->getHeaderLine($name)
                );

                // Headers may cumulate, so we do not need to enforce replacement.
                header($responseHeader, false);
            }
        }

        if ($isChunkEncoded) {
            $this->echoChunkedResponseBody($buffer, $stream, $analyzableContent);
        } else {
            $this->echoNormalResponseBody($buffer, $stream, $analyzableContent);
        }

        if (!empty($analyzableContent)) {
            $afterEmit = function () use ($analyzableContent) {
                // Retrieve again the components for safety.
                /** @var ResponseInfo $responseInfo */
                $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);

                if (empty($responseInfo)) {
                    // Very unlikely to happen.
                    return;
                }

                /** @var Body $body */
                $body = $responseInfo->getComponentByClassName(Body::class);

                if (empty($body)) {
                    // Also very unlikely to happen.
                    return;
                }

                // Make sure the analyzer is present.
                $body->getComponentByClassName(DestinationResponseBodyAnalyzer::class, true);
            };

            $afterEmit();
        }

        // Flush the output buffer to quickly send the response.
        // TODO: this does not close the connection to the browser. [WS-38]
        ob_end_flush();
    }

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(EventDispatcher::class, $this->eventDispatcher);
        return $definitions;
    }
}
