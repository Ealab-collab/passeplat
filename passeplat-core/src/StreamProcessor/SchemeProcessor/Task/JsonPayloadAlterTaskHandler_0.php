<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header\Header;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\ResponseInfo;

/**
 * Alters JSON payload.
 */
class JsonPayloadAlterTaskHandler_0 extends TaskHandlerBase
{
    public function execute(AnalyzableContent $analyzableContent, array $options, string $eventName): void
    {
        if (empty($options)) {
            // Nothing to do.
            return;
        }

        try {
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

            /** @var Header $headerList */
            $headerList = $baseInfo->getComponentByClassName(Header::class);

            /** @var Body $body */
            $body = $baseInfo->getComponentByClassName(Body::class);
        } catch (UnmetDependencyException $e) {
            // Nothing to work on.
            return;
        }

        if (!$this->isOfAllowedContentType($headerList->getHeadersForRequest(), ['application/json'])) {
            // Do not work on non JSON content.
            return;
        }

        // Retrieve the string payload of the stream.
        if (empty($body) || !$body->isBodyAnalyzable()) {
            // The body is truncated. Indeed, we are not working on terabyte-long bodies!
            // So we work only on what the Body component allows to.
            return;
        }

        $bodyAsString = $body->getBody();

        try {
            // Decode.
            $decodedJson = json_decode($bodyAsString, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            // Not a well formatted JSON.
            return;
        }

        if (empty($decodedJson) || !is_array($decodedJson)) {
            // Only work on JSON real objects / arrays with data, not empty or single scalar values.
            return;
        }

        // TODO: performance task: try to guess if the limit may be reached before doing the replacements,
        //       by doing an addition of the base length and the replacement length.
        foreach ($options as $action => $item) {
            switch ($action) {
                case 'append':
                    // TODO: ajouter / remplacer au tableau décodé.
                    // $item['location']
                    // $item['value']
                    // $item['replace']
                    break;

                case 'remove':
                    // TODO: retirer du tableau décodé.
                    break;
            }
        }

        try {
            // Reencode to string.
            $newBodyAsString = json_encode($decodedJson, JSON_THROW_ON_ERROR);
        } catch (\JsonException $jsonException) {
            // Failed to encode. Very weird.
            return;
        }
        $body->resetBody();
        $body->write($newBodyAsString);

        if (!$body->isBodyAnalyzable()) {
            // The performed change exceeds the size limit. Reset it to old value.
            // TODO: the check must be done before. See previous comment.
            $body->resetBody();
            $body->write($bodyAsString);
        }
    }

    public static function hasEnableForm(): bool
    {
        //TODO
        // Implement the three form methods for this task and delete this one.
        return false;
    }
}
