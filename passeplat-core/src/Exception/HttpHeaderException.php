<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * HTTP header exception.
 */
class HttpHeaderException extends HttpException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_HTTP_HEADER,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
