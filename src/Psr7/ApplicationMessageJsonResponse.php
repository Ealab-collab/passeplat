<?php

namespace PassePlat\App\Psr7;

use PassePlat\Core\Psr7\JsonResponse;

class ApplicationMessageJsonResponse extends JsonResponse
{
    /**
     * {@inheritdoc}
     */
    public function __construct(
        $status = 200,
        array $headers = [],
        $body = null,
        $version = '1.1',
        $reason = null
    ) {
        // Encapsulate in array.
        $body = ['message' => $body];
        parent::__construct($status, $headers, $body, $version, $reason);
    }
}
