<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * User related exception.
 */
class UserException extends Exception
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_USER,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
