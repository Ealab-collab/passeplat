<?php

namespace PassePlat\Forms\Exception;

use PassePlat\Core\Exception\Exception;
use Throwable;

/**
 * Exception thrown if there is a bad request.
 */
class BadRequestException extends Exception
{
    public function __construct(
        $message = '',
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_FORM,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
