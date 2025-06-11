<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception is thrown when a strategy is missing.
 */
class MissingStrategyException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_STRATEGY,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
