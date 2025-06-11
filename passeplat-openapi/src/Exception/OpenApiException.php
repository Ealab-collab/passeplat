<?php

namespace PassePlat\Openapi\Exception;

use PassePlat\Core\Exception\Exception;
use Throwable;

class OpenApiException extends Exception
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
