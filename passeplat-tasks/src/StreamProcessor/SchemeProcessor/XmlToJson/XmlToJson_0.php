<?php

namespace PassePlat\Tasks\StreamProcessor\SchemeProcessor\XmlToJson;

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
class XmlToJson_0 extends TaskHandlerBase
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

        if (!$this->isOfAllowedContentType($headerList->getHeadersForRequest(), ['application/xml'])) {
            // Do not work on non XML content.
            // @todo : log it.
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

        $xml = simplexml_load_string($body);
        $json = json_encode($xml);

        // Reset body.
        $responseBody->resetBody();
        $responseBody->write($json);

        // Change content-type header or add it if it does not exist.
        $changeHeader = $headerList->replaceHeader('Content-Type', 'application/json');
        if (!$changeHeader) {
            $headerList->addHeaderFieldEntry('Content-Type', 'application/json');
        }
    }

    public static function hasEnableForm(): bool
    {
        // TODO: Implement the form methods for this task and delete this one.
        return false;
    }
}
