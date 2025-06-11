<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\Alert;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;
use PassePlat\Core\StreamProcessor\SchemeProcessor\Task\TaskHandlerBase;

/**
 * Transform JSON into XML.
 */
class Alert_0 extends TaskHandlerBase
{
    /**
    * Execute the event.
    * @todo : limit the alert
    * @todo : log systematically the alert in ElasticSearch.
    */
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {

        // @todo : delete.
        return;

        $datas = $analyzableContent->getDataToLog();

        // @todo : core utilities.
        // @todo : to be completed.
        $statusMessage = [
            400 => 'Bad request (400)',
            401 => 'Unauthorize (401)',
            402 => 'Payment required (402)',
            403 => 'Forbidden (403)',
            404 => 'Not found (404)',
            405 => 'Method not allowed (405)',
            406 => 'Not acceptable (406)',
            407 => 'Proxy Authentication Required (407)',
            408 => 'Request Time-out (408)',
            409 => 'Gone (409)',
            410 => 'Length required (410)',
            411 => 'Precondition Failed (411)',
            412 => 'Precondition Failed (412)',
            413 => 'Request Entity Too Large (413)',
            414 => 'Request-URI Too Large (414)',
            415 => 'Unsupported Media Type (415)',
            416 => 'Requested range not satisfiable (416)',
            417 => 'Expectation Failed (417)',
            500 => 'Internal Server Error (500)',
            501 => 'Not Implemented (501)',
            502 => 'Bad Gateway (502)',
            503 => 'Service Unavailable (503)',
            504 => 'Gateway Time-out (504)',
            505 => 'HTTP Version not supported (505)'
        ];

        // @todo : test also 4XX or4XX etc. 
        if ( (!isset($options['conditions']['status']) && $datas['web_service_status'] != '2XX') or
            in_array($datas['destination_response_http_status_code'], $options['conditions']['status'])) {

        
            $initiatorRequestHeader = json_decode($datas['initiator_request_headers']);
            $requestHeadersArray = [];
            if (is_array($initiatorRequestHeader)) {
                foreach ($initiatorRequestHeader AS $requestHeader) {
                    $key = $requestHeader->key;
                    $requestHeadersArray[$key] = $requestHeader->value;
                }
            }
    
            $parsedUrl = parse_url($datas['destination_url']);
            $statusCode = $datas['destination_response_http_status_code'];

            $tokens = [
                '[status_code]' => $statusCode,
                '[http_method]' => $datas['http_method'],
                '[date]' => $datas['destination_response_step_started_receiving_time'],
                '[host]' => $parsedUrl['host'],
                '[path]' => $parsedUrl['path'],
                '[query]' => $parsedUrl['query'],
                '[url]' => $datas['destination_url'],
                '[ip]' => isset($requestHeadersArray['X-Real-IP']) ? $requestHeadersArray['X-Real-IP'] : '',
                '[status]' => $statusMessage[$statusCode],
            ];

            // @todo : GET BODY, define if XML or JSON and add the ability of getting values inside.
            // [body:message] or [body:message:nested:array]
            // [header:header-name]
    
            // Replace tokens.
            if (isset($options['email'])) {
                $body['Email'] = $options['email'];
                $body['From'] = 'noreply@wsolution.app';
                $body['Title'] = str_replace(array_keys($tokens), array_values($tokens), $options['sender']);
                $body['Object'] = str_replace(array_keys($tokens), array_values($tokens), $options['subject']);;
                $body['Text'] = str_replace(array_keys($tokens), array_values($tokens), $options['body']);
            }
            else {
                $body['Title'] = str_replace(array_keys($tokens), array_values($tokens), $options['sender']);
                $body['Text'] = str_replace(array_keys($tokens), array_values($tokens), $options['body']);
                $body['Icon'] = 'http://drupal--wilfrid--passeplat.emerya.eu/themes/custom/dessert/logo.png';
                $body['Ping'] = 1;
            }

            $cURLConnection = curl_init($options['url']);
            curl_setopt($cURLConnection, CURLOPT_POSTFIELDS, $body);
            curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);    
            $apiResponse = curl_exec($cURLConnection);
            curl_close($cURLConnection);
        }
    }

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
                'content' => 'The UI for this task is not available yet. Just click on cancel.',
            ],

            'listForms' => [],
        ];
    }
}
