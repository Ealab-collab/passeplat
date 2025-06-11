<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\AlterValues;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;

/**
 * Alter datas.
 */
class AlterValues_0 extends TaskHandlerBase
{
    /**
    * Execute the event.
    */
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {
        $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);
        if ($this->isChunkedTransferEncoding($responseInfo)) {
            // Do not handle chunked content at this stage.
            return;
        }

        switch ($eventName) {
            case 'destinationRequestPreparation':
                /** @var RequestInfo $baseInfo */
                $baseInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);
                break;

            case 'startedReceiving':
                /** @var ResponseInfo $baseInfo */
                $baseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);

                if ($this->isChunkedTransferEncoding($baseInfo)) {
                    // Do not handle chunked content at this stage.
                    return;
                }

                break;

            case 'emittedResponse':
                /** @var ResponseInfo $baseInfo */
                $baseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class);
                break;

            default:
                return;
        }

        // Get all header and body.
        
        /** @var Header $responseHeaders */
        $headers = $baseInfo->getComponentByClassName(Header::class);

        /** @var Body $responseBody */
        $baseBody = $baseInfo->getComponentByClassName(Body::class);

        // Retrieve the string payload of the stream.
        if (empty($baseBody) || !$baseBody->isBodyAnalyzable()) {
            // The body is truncated. Indeed, we are not working on terabyte-long bodies!
            // So we work only on what the Body component allows to.
            return;
        }
        
        // Alter request headers
        if (isset($options['request_headers']) && $options['request_headers']) {
            $this->alterHeaders($requestHeaders, $options['request_headers_exclusions'], $options['length']);
        }

        // Alter request body.
        if (isset($options['request_body']) && $options['request_body']) {
            $this->alterBody($requestHeaders, $requestBody, $options['request_body_exclusions'], $options['length']);     
        }   


        // Alter response body.
        if (isset($options['response_body']) && $options['response_body']) {
            $this->alterBody($responseHeaders, $responseBody, $options['response_body_exclusions'], $options['length']);
        }     

        // Obfuscate response headers
        if (isset($options['response_headers']) && $options['response_headers']) {
            $this->alterHeaders($responseHeaders, $options['response_headers_exclusions'], $options['length']);
        }

    }

    /**
    *   Obfuscate body.
    */
    public function alterBody(Header $headerList, Body $requestBody, array $exclusions, int $length = 1): void
    {    
        $body = $requestBody->getBody();
        $contentType = $headerList->getHeaderFieldValue('Content-Type');
        switch ($contentType) {
            case 'application/json':
                $jsonArray = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->obfuscateArrayRecursivly($jsonArray, $exclusions, $length);
                    $body = json_encode($jsonArray);
                }
                else {
                    // @todo : log error if is not JSON
                }
                break;
            case 'application/xml':

                break;
            case 'application/x-www-form-urlencoded':
                parse_str($body, $query);
                if (is_array($query)) {
                    $this->obfuscateArrayRecursivly($query, $exclusions, $length);
                    $body = http_build_query($query);
                }
                break;

            case 'default':
                break;
        }
        // Reset body.
        $requestBody->resetBody();
        $requestBody->write($body);
    }

    /**
    *   Alter headers.
    */
    public function alterHeaders(Header $listHeaders, array $exclusions, int $length = 1): void
        {
        $newValues = [];
        foreach($listHeaders->getHeadersForRequest() as $key => $header) {        
            if (array_search($key, $exclusions) === false) {
                foreach($header as $value) {
                    if ($length) {         
                        $newValues[] = str_repeat('*', strlen($value));
                    }
                    else {
                        $newValues[] = '*****';
                    }
                }
                $listHeaders->replaceHeader($key, $newValues);
            }
        }
    }

    public static function hasEnableForm(): bool
    {
        // TODO: Implement the form methods for this task and delete this one.
        return false;
    }
}
