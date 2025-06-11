<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception occurs when there is an issue with a remote microservice.
 */
class RemoteServiceException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_REMOTE,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
