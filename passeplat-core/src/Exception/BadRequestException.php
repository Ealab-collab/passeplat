<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Exception when the request which arrived on PassePlat is invalid.
 */
class BadRequestException extends Exception
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_BAD_REQUEST,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
