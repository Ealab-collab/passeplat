<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\StopOnCondition;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\HttpWebServiceStatus;
use PassePlat\Core\Psr7\JsonResponse;

/**
 * Stop the requestion on condtion.
 */
class StopOnCondition_0 extends TaskHandlerBase
{
    
    /**
    * Execute the event.
    */
    public function execute(
        AnalyzableContent $analyzableContent,
        array $options,
        string $eventName
        ): void
    {  
        $stopRequest = FALSE;
        if ($eventName == 'destinationRequestPreparation') {


            /*
            if (isset($options['conditions'][0]['value'])) {           
                foreach($options['conditions'][0]['value'] AS $type => $subConditions) {
                    if (is_array($subConditions)) {
                        // @todo : multiple key / value.
                        switch($type) {
                            case 'request_get':
                                // @todo handle GET that is not present. 
                                if(count($_GET) && $this->testArrayRecursivly($_GET, $subConditions)) {
                                    $stopRequest = TRUE;
                                    break 2;
                                }
                            break;
                            case 'request_header':
                                /** @var RequestInfo $requestInfo *
                                $requestInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);
                                /** @var Header $requestHeaders *
                                $requestHeaders = $requestInfo->getComponentByClassName(Header::class);
                                $requestHeadersArray = $listHeaders->getHeadersForRequest();
                                if(count($requestHeadersArray) && $this->testArrayRecursivly($requestHeadersArray, $subConditions)) {
                                    $stopRequest = TRUE;
                                    break 2;                                    
                                }
                            break;
                            case 'request_body':
                                /** @var RequestInfo $requestInfo *
                                $requestInfo = $analyzableContent->getComponentByClassName(RequestInfo::class);
                                /** @var Header $requestHeaders *
                                $requestHeaders = $requestInfo->getComponentByClassName(Header::class);
                                /** @var Body $requestBody *
                                $requestBody = $requestInfo->getComponentByClassName(Body::class);
                                if($this->testBody($requestHeaders, $requestBody, $subConditions)) {
                                    $stopRequest = TRUE;
                                    break 2;                                    
                                }
                            break;
                            /* useless in this case. See how to handle this depending on the event.
                            case 'response_header':

                            break;
                            case 'response_body':

                            break;
                            */

                            /*
                            default: 
                            break;
                        }
                    }
                } 
            }*/

            $analyzableContent->setExecutionInfo('stopRequest', TRUE);
            $responseInfo = $analyzableContent->getComponentByClassName(ResponseInfo::class, true);
            // @todo : option sur le message et le statut.
            $body = [$options['status_code'] => $options['message']];
            $response = new JsonResponse($options['status_code'], [], $body);
            $responseInfo->setResponse($response);
            // Add header. @todo : make it optional ?
            $headerList = $responseInfo->getComponentByClassName(Header::class, true);
            $headerList->addHeaderFieldEntry('x-Refused-by-Wsolution', $options['header_message']);

            $httpWebServiceStatus = $analyzableContent->getComponentByClassName(HttpWebServiceStatus::class, true);
            $httpWebServiceStatus->setStatusFromResponse($response, true, 'WS');
        }
    }

    public static function hasEnableForm(): bool
    {
        // TODO: Implement the form methods for this task and delete this one.
        return false;
    }

    /**
    *   @todo : service à créer pour travailler récursivement.
    *   Utilisation RecursiveArrayIterator ?
    *   
    */

    /**
    *   Test body depending on format.
    *   @todo : to be finished.
    */
    public function testBody(
        Header $headerList,
        Body $requestBody, 
        array $test
        ): bool
    {    
        $body = $requestBody->getBody();
        $contentType = $headerList->getHeaderFieldValue('Content-Type');
        // @todo : if no header, auto-defin format.
        switch ($contentType) {
            case 'application/json':
                $jsonArray = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->testArrayRecursivly($jsonArray, $test);
                }
                else {
                    // @todo : log error if is not JSON
                }
                break;
            // @todo.
            case 'application/xml':
                return FALSE;
                break;
            case 'application/x-www-form-urlencoded':
                parse_str($body, $query);
                if (is_array($query)) {
                    return $this->testArrayRecursivly($query, $test);
                }
                break;

            case 'default':
                return FALSE;
                break;
        }
        return FALSE;
    }

    /**
    *   Test recursivly an array.
    */
    public function testArrayRecursivly(
        array &$array,
        array $test
        ) : bool
    {
        foreach( $array as $key => $value ){    
            if(is_array($value)) {    
                $this->testArrayRecursivly($array[$key], $test, $length);
            }    
            else {
                return ($key == $test['key'] && $value == $test['value']);
            }    
        }            
    }
}
