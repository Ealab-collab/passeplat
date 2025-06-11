<?php

namespace PassePlat\Openapi\Tool\Har;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Openapi\Exception\MissingParameterException;

/**
 * This class implements the HarEntryBuilderStrategy interface and provides a strategy for building entries
 * in the Har object based on information from both the request and the response.
 */
class FromRequestResponseHarEntryBuilder implements HarEntryBuilderStrategy
{
    /**
     * Build an entry using information from the request and response parameters.
     *
     * @param array $parameters
     *   The parameters containing information about the request and response.
     *   - 'requestBody': Request body.
     *   - 'requestHeader': Request headers.
     *   - 'requestInfo': Request information.
     *   - 'responseBody': Response body.
     *   - 'responseHeader': Response headers.
     *   - 'responseInfo': Response information.
     *
     * @return array<string, mixed>
     *   The entry in HAR format to be added.
     *
     * @throws MissingParameterException
     *   If any required parameter is missing.
     */
    public function buildEntry(array $parameters) : array
    {
        // Check the presence of all required parameters.
        $this->validateParams($parameters);

        // Build the HTTP request part of the entry.
        $httpRequest = [
            'httpVersion' => "",
            'url' => $parameters['requestInfo']->getDestinationUrl(),
            'method' => $parameters['requestInfo']->getRequest()->getMethod(),
            'headersSize' => -1,
            'headers' => $this->transformHeaders($parameters['requestHeader']),
            'bodySize' => $parameters['requestBody']->getRealBodyLength(),
            'body' => $parameters['requestBody']->getBody(),
            'cookies' => [],
            'queryString' => $parameters['requestInfo']->getQueryParams(),
        ];

        // Build the HTTP response part of the entry.
        $httpResponse = [
            'httpVersion' => "",
            'status' => $parameters['responseInfo']->getStatusCode(),
            'statusText' => "",
            'headersSize' => -1,
            'headers' => $this->transformHeaders($parameters['responseHeader']),
            'bodySize' => $parameters['responseBody']->getRealBodyLength(),
            'body' => $parameters['responseBody']->getBody(),
            'cookies' => [],
            'content' => [
                'size' => -1,
                'mimeType' => ""
            ],
            'redirectURL' => "",
        ];

        // Additional parts of the entry.
        $cache = [
            "beforeRequest" => null,
            "afterRequest" => null,
        ];

        $timings = [
            "blocked" => -1,
            "dns" => -1,
            "connect" => -1,
            "send" => -1,
            "wait" => -1,
            "receive" => -1,
        ];

        // Construct and return the complete entry.
        return [
            'startedDateTime' => date('c'),
            'time' => -1,
            'request' => $httpRequest,
            'response' => $httpResponse,
            'cache' => $cache,
            'timings' => $timings,
        ];
    }

    /**
     * Transforms a Header object into an array suitable for the HAR format.
     *
     * @param Header $header
     *   The Header object to transform.
     *
     * @return array
     *   The transformed header.
     */
    private function transformHeaders(Header $header): array
    {
        $res = [];
        foreach ($header->getHeadersForRequest() as $key => $value) {
            $res[] = [
                "name" => $key,
                "value" => $header->getHeaderFieldValue($key),
            ];
        }

        return $res;
    }

    /**
     * Validates the presence of required parameters in the input array.
     *
     * @param array $parameters
     *   The input array of parameters.
     *
     * @throws MissingParameterException
     *   If any required parameter is missing.
     */
    private function validateParams(array $parameters): void
    {
        $requiredParameters = [
            'requestInfo' => RequestInfo::class,
            'requestHeader' => Header::class,
            'requestBody' => Body::class,
            'responseInfo' => ResponseInfo::class,
            'responseHeader' => Header::class,
            'responseBody' => Body::class,
        ];

        foreach ($requiredParameters as $param => $expectedClassName) {
            if (!($parameters[$param] ?? null) instanceof $expectedClassName) {
                throw new MissingParameterException("Missing required param: $param");
            }
        }
    }
}
