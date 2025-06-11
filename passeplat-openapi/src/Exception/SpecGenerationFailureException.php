<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception is thrown when generating an OpenAPI specification is not possible.
 */
class SpecGenerationFailureException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_GENERATION,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
