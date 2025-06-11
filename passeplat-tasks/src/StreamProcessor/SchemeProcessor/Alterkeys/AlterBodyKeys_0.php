<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\Alterkeys;

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
class AlterBodyKeys_0 extends TaskHandlerBase
{
    /**
    * Execute the event.
    */
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {

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
            default:
                return;
        }

        if (!isset($options['keys'])) {
            return;
        }
        

        // Get all header and body. 
        /** @var Header $responseHeaders */
        $headerList = $baseInfo->getComponentByClassName(Header::class);

        /** @var Body $responseBody */
        $baseBody = $baseInfo->getComponentByClassName(Body::class);

        // Retrieve the string payload of the stream.
        if (empty($baseBody) || !$baseBody->isBodyAnalyzable()) {
            // The body is truncated. Indeed, we are not working on terabyte-long bodies!
            // So we work only on what the Body component allows to.
            return;
        }

        // Proceed.
        foreach ($options['keys'] as $key) {
            // The old key is precisely nested.
            if ($key['key'][0] == '[') {
                $nestedOldKey = $this->getNestedArrayFromToken($key['key']);
                
                if ($key['new_key'][0] == '[') {
                    $nestednewKey = $this->getNestedArrayFromToken($key['new_key']);
                }
            }

            // The old key is not nested, aggregate all modifications.
            else {
                $recursiveChange [$key['key']] = $key['new_key'];
            }

        }

        // Do all the recursive changes in the body.
        $this->alterBody($headerList, $baseBody, $keys, $options['length']);      


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
                    $this->alterArrayRecursivly($jsonArray, $exclusions, $length);
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
                    $this->alterArrayRecursivly($query, $exclusions, $length);
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
    *   Alter an array.
    */
    public function alterArrayRecursivly(array &$array, array &$keys) : void
    {
        foreach( $array as $key => $value ){    
            if(is_array($value)) {    
                $this->alterArrayRecursivly($array[$key], $exclusions, $length);
            }    
            else {//if(array_search($key, $exclusions) === false){   
                if ($length) {
                    $array[$key] = str_repeat('*', strlen($value));
                }
                else {
                    $array[$key] = '*****';
                }
            }    
        }            
    }

    /**
    *   Transform [key1][key2]... into a PHP array.
    */
    public function getNestedArrayFromToken(string $key): array
    {    
        $array = [];
        // Get rid of first and last brackets.
        $key = substr($key, 1);
        $key = substr($key, 0, -1);
        $array = explode('][', $oldKey);
        return $array;
    }

    public static function hasEnableForm(): bool
    {
        // TODO Implement the form methods for this task and delete this one.
        return false;
    }
}
