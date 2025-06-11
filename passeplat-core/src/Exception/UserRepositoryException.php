<?php

namespace PassePlat\Core\Exception;

use PassePlat\Core\Exception\ErrorCode;
use PassePlat\Core\Exception\UserException;

/**
 * Exception for user repository.
 */
class UserRepositoryException extends UserException
{
    public function __construct(
        $message = "",
        \Throwable $previous = null,
        $ppCode = ErrorCode::PP_INVALID_USER_REPOSITORY,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
