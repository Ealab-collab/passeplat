<?php

namespace PassePlat\Core\Psr7;

use GuzzleHttp\Psr7\Response;

/**
 * Wrapper for Guzzle\Psr7\Response with JSON encoding support.
 */
class JsonResponse extends Response
{
    public function __construct(
        $status = 200,
        array $headers = [],
        $body = null,
        $version = '1.1',
        $reason = null
    ) {
        $headers['Content-Type'] = 'application/json';
        $body = json_encode($body);
        parent::__construct($status, $headers, $body, $version, $reason);
    }
}
