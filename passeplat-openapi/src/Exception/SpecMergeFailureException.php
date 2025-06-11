<?php

namespace PassePlat\Openapi\Exception;

use Throwable;

/**
 * This exception occurs when merging multiple OpenAPI documents is not possible.
 */
class SpecMergeFailureException extends OpenApiException
{
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::PP_OPENAPI_MERGE,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
