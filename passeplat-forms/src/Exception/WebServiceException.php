<?php

namespace PassePlat\Forms\Exception;

use PassePlat\Core\Exception\Exception;
use Throwable;

/**
 * Exception thrown if there is an issue generating forms for webservices.
 */
class WebServiceException extends Exception
{
    public function __construct(
        $message = '',
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_FORM_WEBSERVICE,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
