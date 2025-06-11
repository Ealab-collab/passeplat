<?php

namespace PassePlat\App\Exception;

/**
 * Error codes collection for passeplat-app.
 */
class ErrorCode
{
    /**
     * Generic error when the error type is undetermined.
     */
    const UNKNOWN = 'PP-A_0';

    /**
     * Generic error when a response message could not be sent or processed.
     */
    const MALFORMED_MESSAGE = 'PP-A_1';

    /**
     * Generic error for passeplat errors.
     */
    const PASSEPLAT = 'PP-A_50';

    /**
     * PassePlat App Configuration load error.
     */
    const CONFIGURATION_ERROR = 'PP-A_100';
}
