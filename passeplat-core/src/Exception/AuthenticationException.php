<?php

namespace PassePlat\Core\Exception;

/**
 * Error on authentication.
 */
class AuthenticationException extends UserException
{
    public function __construct(
        $message = "",
        \Throwable $previous = null,
        $ppCode = ErrorCode::PP_USER_AUTHENTICATION,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
