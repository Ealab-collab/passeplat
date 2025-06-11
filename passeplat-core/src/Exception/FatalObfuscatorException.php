<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * The exception is thrown when fatal obfuscation errors occur.
 */
class FatalObfuscatorException extends ObfuscatorException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OBFUSCATOR_FATAL_ERROR,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
