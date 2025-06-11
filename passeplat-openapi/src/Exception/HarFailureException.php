<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception occurs when there is an issue with the HAR format.
 */
class HarFailureException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_HAR,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
