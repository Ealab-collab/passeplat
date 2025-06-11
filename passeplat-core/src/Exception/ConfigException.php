<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Generic config exception.
 */
class ConfigException extends Exception
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_CONFIG,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
