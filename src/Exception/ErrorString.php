<?php

namespace PassePlat\App\Exception;

/**
 * Collection of error strings.
 */
class ErrorString
{
    const UNKNOWN_ERROR = 'Unknown error. (%s)';
    const CRITICAL_ERROR = 'Critical error. (%s)';

    /**
     * Gathers error codes into a single string.
     *
     * @param array|mixed $errorCodes
     *   Array of error codes.
     *
     * @return string
     *   The formatted string containing error codes.
     */
    private static function assembleErrorCodes($errorCodes)
    {
        if (is_array($errorCodes)) {
            // Remove empty items.
            $errorCodes = array_filter($errorCodes);

            return implode(',', $errorCodes);
        }

        // Force cast to string.
        return (string) $errorCodes;
    }

    /**
     * Builds a critical error message.
     *
     * @param array|mixed $errorCodes
     *   Error codes typically coming from ErrorCode, or custom ones.
     *
     * @return string
     *   Critical error string.
     */
    public static function buildCriticalError($errorCodes)
    {
        return sprintf(static::CRITICAL_ERROR, static::assembleErrorCodes($errorCodes));
    }

    /**
     * Builds an unknown error message.
     *
     * @param array|mixed $errorCodes
     *   Error codes typically coming from ErrorCode, or custom ones.
     *
     * @return string
     *   Unknown error string.
     */
    public static function buildUnknownError($errorCodes)
    {
        return sprintf(static::UNKNOWN_ERROR, static::assembleErrorCodes($errorCodes));
    }
}
