<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * An attempt to login on an non existing user.
 */
class UserNotFoundException extends UserException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_USER_NOT_FOUND,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
