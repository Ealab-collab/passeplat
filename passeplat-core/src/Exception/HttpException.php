<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Generic HTTP exception.
 */
class HttpException extends Exception
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_HTTP,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
