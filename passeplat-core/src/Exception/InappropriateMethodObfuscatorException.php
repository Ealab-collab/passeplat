<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * The exception is thrown when trying to obfuscate data using an inappropriate method.
 */
class InappropriateMethodObfuscatorException extends ObfuscatorException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OBFUSCATOR_INAPPROPRIATE,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
