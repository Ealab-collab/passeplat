<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Obfuscator related exception.
 */
class ObfuscatorException extends Exception
{
    public function __construct(
        $message = '',
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OBFUSCATOR,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
