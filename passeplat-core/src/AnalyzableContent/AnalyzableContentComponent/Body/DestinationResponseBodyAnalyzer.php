<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body;

/**
 * Analyzer for destination response body.
 */
class DestinationResponseBodyAnalyzer extends BodyAnalyzer
{
    /**
     * {@inheritdoc}
     */
    public function analyze(Body $body): array
    {
        $data = [];

        if (!$body->isBodyAnalyzable()) {
            $data['destination_response_truncated_body'] = $body->getBody();
        } else {
            $data['destination_response_body'] = $body->getBody();
        }

        $data['destination_response_body_length'] = $body->getRealBodyLength();

        return $data;
    }
}
