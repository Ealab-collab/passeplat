<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception is thrown when an expected parameter is missing.
 */
class MissingParameterException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_PARAMETER,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
