<?php

namespace PassePlat\Forms\Exception;

use PassePlat\Core\Exception\Exception;
use Throwable;

/**
 * Exception thrown if there is an issue generating forms for conditions.
 */
class ConditionException extends Exception
{
    public function __construct(
        $message = '',
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_FORM_CONDITION,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
