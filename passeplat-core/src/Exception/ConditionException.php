<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Generic exception for the condition plugins system errors.
 */
class ConditionException extends Exception
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_CONDITION,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
