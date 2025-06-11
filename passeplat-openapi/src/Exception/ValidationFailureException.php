<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception is thrown when an element cannot be validated.
 */
class ValidationFailureException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_VALIDATION,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
