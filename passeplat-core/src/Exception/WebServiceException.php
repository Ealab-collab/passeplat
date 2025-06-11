<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Web service related exception.
 */
class WebServiceException extends Exception
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_WEB_SERVICE,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
