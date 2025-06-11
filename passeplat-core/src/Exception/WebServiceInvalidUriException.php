<?php

namespace PassePlat\Core\Exception;

use Throwable;

/**
 * Attempted to use a webservice with an invalid destination URI.
 */
class WebServiceInvalidUriException extends WebServiceException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_WEB_SERVICE_INVALID_URI,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
