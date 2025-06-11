<?php

namespace PassePlat\Openapi\Tool\Validation;

use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidCookies;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidHeaders;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidPath;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidQueryArgs;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidSecurity;

/**
 * Updates ValidationError objects based on various types of validation exceptions.
 *
 * This class contains static methods for handling different types of validation exceptions and
 * updating a ValidationError object's attributes based on exception details.
 */
class ValidationErrorUpdater
{
    /**
     * Updates the ValidationError object based on the given exception.
     *
     * @param \Exception $e
     *   The league validation exception used to update the error.
     * @param ValidationError $error
     *   The ValidationError object to be updated.
     */
    public static function update(\Exception $e, ValidationError $error): void
    {
        if ($e instanceof InvalidCookies) {
            self::updateForInvalidCookies($e, $error);
        } elseif ($e instanceof InvalidPath) {
            self::updateForInvalidPath($e, $error);
        } elseif ($e instanceof InvalidQueryArgs) {
            self::updateForInvalidQueryArgs($e, $error);
        } elseif ($e instanceof InvalidBody) {
            self::updateForInvalidBody($e, $error);
        } elseif ($e instanceof InvalidHeaders) {
            self::updateForInvalidHeaders($e, $error);
        } elseif ($e instanceof InvalidSecurity) {
            self::updateForInvalidSecurity($e, $error);
        } else {
            // Not known error.
            $error->setMessage($e->getMessage(), 0);
        }
    }

    /**
     * Updates the error attributes for InvalidBody exceptions.
     *
     * @param InvalidBody $e
     *   The League validation exception used to update the error.
     * @param ValidationError $error
     *   The ValidationError object to be updated.
     */
    private static function updateForInvalidBody(InvalidBody $e, ValidationError $error): void
    {
        $error->setAsBody();

        $char = $e->getMessage()[0];
        if ($char === 'B') {
            // BodyDoesNotMatchSchema.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 1);
        } elseif ($char === 'M') {
            // BodyDoesNotMatchSchemaMultipart.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 2);
        } elseif ($char === 'J') {
            // BodyIsNotValidJson.
            $error->setAsParsing();
            $error->setMessage($e->getMessage(), 3);
            $error->incrementSeverityLevel();
        }
    }

    /**
     * Updates the error attributes for InvalidCookies exceptions.
     *
     * @param InvalidCookies $e
     *   The League validation exception used to update the error.
     * @param ValidationError $error
     *   The ValidationError object to be updated.
     */
    private static function updateForInvalidCookies(InvalidCookies $e, ValidationError $error): void
    {
        $error->setAsCookies();

        $char = $e->getMessage()[0];
        if ($char === 'M') {
            // MissingRequiredCookie.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 1);
            $error->incrementSeverityLevel();
        } elseif ($char === 'V') {
            // ValueDoesNotMatchSchema.
            $error->setAsValue();
            $error->setMessage($e->getMessage(), 2);
        }
    }

    /**
     * Updates the error attributes for InvalidHeaders exceptions.
     *
     * @param InvalidHeaders $e
     *   The League validation exception used to update the error.
     * @param ValidationError $error
     *   The ValidationError object to be updated.
     */
    private static function updateForInvalidHeaders(InvalidHeaders $e, ValidationError $error): void
    {
        $error->setAsHeaders();

        $char = $e->getMessage()[0];
        if ($char === 'M') {
            // MissingRequiredHeader & MissingRequiredHeaderMupripart.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 1);
            $error->incrementSeverityLevel();
        } elseif ($char === 'V') {
            // ValueDoesNotMatchSchema & ValueDoesNotMatchSchemaMultipart.
            $error->setAsValue();
            $error->setMessage($e->getMessage(), 2);
        } elseif ($char === 'H') {
            // UnexpectedHeaderIsNotAllowed.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 3);
        } elseif ($char === 'C') {
            // ContentTypeIsNotExpected.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 4);
        }
    }

    /**
     * Updates the error attributes for InvalidPath exceptions.
     *
     * @param InvalidPath $e
     *   The League validation exception used to update the error.
     * @param ValidationError $error
     *   The ValidationError object to be updated.
     */
    private static function updateForInvalidPath(InvalidPath $e, ValidationError $error): void
    {
        $error->setAsPath();
        $error->incrementSeverityLevel();

        $char = $e->getMessage()[0];
        if ($char === 'V') {
            // ValueDoesNotMatchSchema.
            $error->setAsValue();
            $error->setMessage($e->getMessage(), 3);
        } elseif ($char === 'U') {
            // PathDoesNotMatchPattern.
            $error->setAsParsing();
            $error->setMessage($e->getMessage(), 4);
        }
    }

    /**
     * Updates the error attributes for InvalidQueryArgs exceptions.
     *
     * @param InvalidQueryArgs $e
     *   The League validation exception used to update the error.
     * @param ValidationError $error
     *   The ValidationError object to be updated.
     */
    private static function updateForInvalidQueryArgs(InvalidQueryArgs $e, ValidationError $error): void
    {
        $error->setAsQuery();

        $char = $e->getMessage()[0];

        if ($char === 'M') {
            // MissingRequiredArgument.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 3);
            $error->incrementSeverityLevel();
        } elseif ($char === 'V') {
            // ValueDoesNotMatchSchema.
            $error->setAsValue();
            $error->setMessage($e->getMessage(), 1);
        } elseif ($char === 'A') {
            // UnexpectedArgumentIsNotAllowed.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 2);
        }
    }

    /**
     * Updates the error attributes for InvalidSecurity exceptions.
     *
     * @param InvalidSecurity $e
     *   The League validation exception used to update the error.
     * @param ValidationError $error
     *   The ValidationError object to be updated.
     */
    private static function updateForInvalidSecurity(InvalidSecurity $e, ValidationError $error): void
    {
        $error->setAsSecurity();
        $error->incrementSeverityLevel();

        $char = $e->getMessage()[0];
        if ($char === 'H') {
            // AuthHeaderValueDoesNotMatchExpectedPattern.
            $error->setAsValue();
            $error->setMessage($e->getMessage(), 1);
        } elseif ($char === 'N') {
            // RequestDidNotMatchAnySchema.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 2);
        } elseif ($char === 'S') {
            // RequestDidNotMatchSchema.
            $error->setAsSchema();
            $error->setMessage($e->getMessage(), 3);
        }
    }
}
