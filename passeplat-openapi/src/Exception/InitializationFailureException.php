<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception is thrown when an object cannot be initialized.
 */
class InitializationFailureException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_INIT,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
