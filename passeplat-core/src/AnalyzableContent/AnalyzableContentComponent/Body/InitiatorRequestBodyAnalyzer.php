<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body;

/**
 * Analyzer for initiator request body.
 */
class InitiatorRequestBodyAnalyzer extends BodyAnalyzer
{
    /**
     * {@inheritdoc}
     */
    public function analyze(Body $body): array
    {
        $data = [];

        if (!$body->isBodyAnalyzable()) {
            $data['initiator_request_truncated_body'] = $body->getBody();
        } else {
            $data['initiator_request_body'] = $body->getBody();
        }

        $data['initiator_request_body_length'] = $body->getRealBodyLength();

        return $data;
    }
}
