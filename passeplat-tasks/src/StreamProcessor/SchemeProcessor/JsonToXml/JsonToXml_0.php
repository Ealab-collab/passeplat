<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\JsonToXml;

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
class JsonToXml_0 extends TaskHandlerBase
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

        /** @var Header $headerList */
        $headerList = $responseInfo->getComponentByClassName(Header::class);

        /** @var Body $body */
        $responseBody = $responseInfo->getComponentByClassName(Body::class);

        if (!$this->isOfAllowedContentType($headerList->getHeadersForRequest(), ['application/json'])) {
            // Do not work on non JSON content.
            return;
        }

        // Retrieve the string payload of the stream.
        if (empty($responseBody) || !$responseBody->isBodyAnalyzable()) {
            // The body is truncated. Indeed, we are not working on terabyte-long bodies!
            // So we work only on what the Body component allows to.
            return;
        }

        // Turn body into 
        $body = $responseBody->getBody();
        // @todo : better testing if error.
        $jsonArray = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $xml = '';
            $root = isset($options['root']) ?  $options['root'] : '<root>';
            $xmlVersion = isset($options['xmlVersion']) ?  $options['xmlVersion'] : '1.0';

            $xml = new SimpleXMLElement('<?xml version="1' . $xmlVersion . '"?><' . $root . '></' . $root . '>');
            array_to_xml( $jsonArray, $xml);

            // Reset body.
            $responseBody->resetBody();
            $responseBody->write($xm->asXML);

            // Change content-type header or add it if it does not exist.
            $changeHeader = $headerList->replaceHeader('Content-Type', 'application/xml');
            if (!$changeHeader) {
                $headerList->addHeaderFieldEntry('Content-Type', 'application/xml');
            }

        }
        else {
           // @todo : log error if is not JSON
        }

    }     
    
    // function defination to convert array to xml
    public function array_to_xml( $data, &$xml_data ) : void
    {
        foreach( $data as $key => $value ) {
            if( is_array($value) ) {
                if( is_numeric($key) ){
                    $key = 'item'.$key; //dealing with <0/>..<n/> issues
                }
                $subnode = $xml_data->addChild($key);
                array_to_xml($value, $subnode);
            } else {
                $xml_data->addChild("$key",htmlspecialchars("$value"));
            }
        }
    }

    /**
     * Generate XML from array. @todo : does not work in all cases. Better function to seek for.
     *
     * @param array $array
     *   PHP of array to transform into XML.
     * @param string $level
     *   Level of the XML block.
     * @param string $root
     *   Tag of the first root.
     * @param string $xmlVersion
     *   Version of the XML.
     */
    public function arrayToXml($array, $level = 1, $root = 'root', $xmlVersion = '1.0') : string
    {
        $xml = '';
    if (!empty($arr)) {
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                if (array_key_exists(0, $value)) {
                    foreach ($value as $kk => $vv) {
                        $xml .= '<' . $key . ' id="' . $key . '_' . $kk . '">' . "\n";
                        $xml .= $this->arrayToXml($vv);
                        $xml .= '</' . $key . '>' . "\n";
                    }
                } else {
                    if (!is_numeric($key)) {
                        $xml .= '<' . $key . '>' . "\n";
                    }
                    $xml .= $this->arrayToXml($value);
                    if (!is_numeric($key)) {
                        $xml .= '</' . $key . '>' . "\n";
                    }
                }
            } else {
                $xml .= '<' . $key . '>' . $value . '</' . $key . '>' . "\n";
            }
        }
    }
    return $xml;
    }

    /**
     * Returns a single line formatted in an XML type.
     *
     * @param string $key
     *   Key of the XML tag.
     * @param string $value
     *   Value of the XML tag.
     * @param string $level
     *   Level of the XML line.
     * 
     */
    public function addXmlTag($key, $value, $level) : string
    {
        // @todo : implication to study with security in mind.
        if (htmlspecialchars($value) != $value) {
            $value = '<![CDATA[{$value2}]]>';
        }
        if (is_numeric($key)) {
            return str_repeat("\t", $level) . "<item>{$value}</item>\n";
        }
        else {
            return str_repeat("\t", $level) . "<{$key}>{$value}</{$key}>\n";
        }
    }

    public static function hasEnableForm(): bool
    {
        // TODO: Implement the form methods for this task and delete this one.
        return false;
    }
}
