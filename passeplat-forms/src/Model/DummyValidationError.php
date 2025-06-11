<?php

namespace PassePlat\Forms\Model;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Openapi\Tool\Validation\ValidationError;

/**
 * This class is used to randomly generate validation errors based on various types of request and response errors.
 * It uses predefined constants for error messages associated with different components (body, cookies, headers, etc.),
 * and it allows the creation of simulated errors for testing or other internal purposes.
 */
class DummyValidationError extends ComponentBasedObject
{
    private ValidationError $validationError;

    private const BODY_ERRORS = [
        1 => ['Body does not match schema for content-type X for Y', 'Schema'],
        2 => ['Multipart body does not match schema for part X with content-type Y for Z', 'Schema'],
        3 => ['JSON parsing failed with X for Y', 'Parsing'],
    ];

    private const COOKIE_ERRORS = [
        1 => ['Missing required cookie X for Y', 'Schema'],
        2 => ['Value X for cookie Y is invalid for Z', 'Value'],
    ];

    private const END_DATE = '2024-12-31';

    private const HEADER_ERRORS = [
        1 => ['Missing required header X for Y', 'Schema'],
        2 => ['Value X for header Y is invalid for Z', 'Value'],
        3 => ['Header X is not allowed for Y', 'Schema'],
        4 => ['Content-Type X is not expected for Y', 'Schema'],
    ];

    private const PATH_ERRORS = [
        1 => ['No path found in the specification file matching X', 'Schema'],
        2 => ["X hasn't Y method", 'Schema'],
        3 => ['Value X for parameter Y is invalid for Z', 'Value'],
        4 => ['Unable to parse X against the pattern Y for Z', 'Parsing'],
    ];

    private const QUERY_ERRORS = [
        1 => ['Missing required argument X for Y', 'Schema'],
        2 => ['Value X for argument Y is invalid for Z', 'Value'],
        3 => ['Argument X is not allowed for Y', 'Schema'],
    ];

    private const REQUEST_METHODS = [
        'GET',
        'POST',
        'PUT'
    ];

    private const SECURITY_ERRORS = [
        1 => ['Header X should match pattern Y for Z', 'Value'],
        2 => ['None of security schemas did match for X', 'Schema'],
        3 => ['Security schema X did not match for Y', 'Schema'],
    ];

    private const SPEC_PATHS = [
        '/api/v1/users',
        '/api/v1/orders',
        '/api/v1/cats'
    ];

    private const START_DATE = '2023-01-01';

    /**
     * Assigns details to the validation error.
     *
     * @param string $message
     *   The error message.
     * @param int $code
     *   The error code of the message.
     * @param string $type
     *   The type of error (Schema, Value, Parsing).
     */
    private function assignValidationErrorDetails(string $message, int $code, string $type): void
    {
        $this->validationError->setMessage($message, $code);

        $method = 'setAs' . $type;
        if (method_exists($this->validationError, $method)) {
            $this->validationError->$method();
        }
    }

    /**
     * Simulates a coin flip.
     *
     * @return bool
     *   True or false randomly.
     * @throws \Random\RandomException
     */
    private function flipCoin(): bool
    {
        return (bool) random_int(0, 1);
    }

    /**
     * Generates a random HTTP method (GET, POST, PUT).
     *
     * @return string
     *   A random HTTP method.
     *
     * @throws \Random\RandomException
     */
    private function getRandomRequestMethod():string
    {
        $index = random_int(0, count(static::REQUEST_METHODS) - 1);
        return static::REQUEST_METHODS[$index];
    }

    /**
     * Generates a random specification path.
     *
     * @return string
     *   A random request path.
     *
     * @throws \Random\RandomException
     */
    private function getRandomSpecPath(): string
    {
        $index = random_int(0, count(static::SPEC_PATHS) - 1);
        return static::SPEC_PATHS[$index];
    }

    /**
     * Generates a random timestamp between two constant dates.
     *
     * @return int
     *   The random timestamp.
     *
     * @throws \Random\RandomException
     */
    private function getRandomTimestamp(): int
    {
        $start_time = strtotime(static::START_DATE);
        $end_time = strtotime(static::END_DATE);

        return random_int($start_time, $end_time);
    }

    /**
     * Generates a random validation error.
     *
     * @return ValidationError
     *   The generated validation error.
     * @throws UnmetDependencyException
     * @throws \Random\RandomException
     */
    public function getRandomValidationError(): ValidationError
    {
        /** @var ValidationError $validationError */
        $validationError = ComponentBasedObject::getRootComponentByClassName(
            ValidationError::class,
            true
        );

        $validationError->reset();
        $this->validationError = $validationError;

        // Randomize it!
        $this->randomiseRequestResponseType();
        $this->randomizeCategory();
        $this->randomizeItem();
        $this->randomizeTimestamp();
        $this->randomizeSpecPath();

        return $this->validationError;
    }

    /**
     * Randomizes the body of the validation error.
     *
     * @throws \Random\RandomException
     */
    private function randomizeBody(): void
    {
        $choice = random_int(1, count(static::BODY_ERRORS));
        $this->setErrorBody($choice);
    }

    /**
     * Randomizes the category of the validation error.
     *
     * @throws \Random\RandomException
     */
    private function randomizeCategory(): void
    {
        $this->setErrorCategory(random_int(1, 3));
    }

    /**
     * Randomizes the cookies.
     *
     * @throws \Random\RandomException
     */
    private function randomizeCookies(): void
    {
        $choice = random_int(1, count(static::COOKIE_ERRORS));
        $this->setErrorCookies($choice);
    }

    /**
     * Randomizes the headers.
     *
     * @throws \Random\RandomException
     */
    private function randomizeHeaders(): void
    {
        $choice = random_int(1, count(static::HEADER_ERRORS));
        $this->setErrorHeaders($choice);
    }

    /**
     * Randomizes the item.
     *
     * @throws \Random\RandomException
     */
    private function randomizeItem(): void
    {
        $this->setErrorItem(random_int(1, 6));
    }

    /**
     * Randomizes the path item.
     *
     * @throws \Random\RandomException
     */
    private function randomizePath(): void
    {
        $choice = random_int(1, count(static::PATH_ERRORS));
        $this->setErrorPath($choice);
    }

    /**
     * Randomizes the query item.
     *
     * @throws \Random\RandomException
     */
    private function randomizeQuery(): void
    {
        $choice = random_int(1, count(static::QUERY_ERRORS));
        $this->setErrorQuery($choice);
    }

    /**
     * Randomizes whether the validation error is related to a request or a response.
     *
     * @throws \Random\RandomException
     */
    private function randomiseRequestResponseType(): void
    {
        if ($this->flipCoin()) {
            $this->validationError->setAsRequest();
        } else {
            $this->validationError->setAsResponse();
        }
    }

    /**
     * Randomizes the security item.
     *
     * @throws \Random\RandomException
     */
    private function randomizeSecurity(): void
    {
        $choice = random_int(1, count(static::SECURITY_ERRORS));
        $this->setErrorSecurity($choice);
    }

    /**
     * Randomizes the specification path.
     *
     * @throws \Random\RandomException
     */
    private function randomizeSpecPath()
    {
        $this->validationError->setSpecPath($this->getRandomSpecPath());
    }

    /**
     * Randomizes the timestamp.
     *
     * @throws \Random\RandomException
     */
    private function randomizeTimestamp(): void
    {
        $this->validationError->setTimestamp($this->getRandomTimestamp());
    }

    /**
     * Sets a body error.
     *
     * @param int $choice
     *   The selected body error.
     */
    private function setErrorBody(int $choice): void
    {
        [$message, $category] = static::BODY_ERRORS[$choice];
        $this->assignValidationErrorDetails($message, $choice, $category);
    }

    /**
     * Sets a category error.
     *
     * @param int $choice
     *   The selected category.
     */
    private function setErrorCategory(int $choice): void
    {
        switch ($choice) {
            case 1:
                $this->validationError->setAsParsing();
                break;
            case 2:
                $this->validationError->setAsSchema();
                break;
            case 3:
                $this->validationError->setAsValue();
                break;
        }
    }

    /**
     * Sets a cookie error.
     *
     * @param int $choice
     *   The selected cookie error.
     */
    private function setErrorCookies(int $choice): void
    {
        [$message, $category] = static::COOKIE_ERRORS[$choice];
        $this->assignValidationErrorDetails($message, $choice, $category);
    }

    /**
     * Sets a header error.
     *
     * @param int $choice
     *   The selected header error.
     */
    private function setErrorHeaders(int $choice): void
    {
        [$message, $category] = static::HEADER_ERRORS[$choice];
        $this->assignValidationErrorDetails($message, $choice, $category);
    }

    /**
     * Sets an item error.
     *
     * @param int $choice
     *   The selected item error.
     *
     * @throws \Random\RandomException
     */
    private function setErrorItem(int $choice): void
    {
        switch ($choice) {
            case 1:
                $this->validationError->setAsBody();
                $this->randomizeBody();
                break;
            case 2:
                $this->validationError->setAsCookies();
                $this->randomizeCookies();
                break;
            case 3:
                $this->validationError->setAsHeaders();
                $this->randomizeHeaders();
                break;
            case 4:
                $this->validationError->setAsPath();
                $this->randomizePath();
                break;
            case 5:
                $this->validationError->setAsQuery();
                $this->randomizeQuery();
                break;
            case 6:
                $this->validationError->setAsSecurity();
                $this->randomizeSecurity();
                break;
        }
    }

    /**
     * Sets a path error.
     *
     * @param int $choice
     *   The selected path error.
     */
    private function setErrorPath(int $choice): void
    {
        [$message, $category] = static::PATH_ERRORS[$choice];
        $this->assignValidationErrorDetails($message, $choice, $category);
    }

    /**
     * Sets a query error.
     *
     * @param int $choice
     *   The selected query error.
     */
    private function setErrorQuery(int $choice): void
    {
        [$message, $category] = static::QUERY_ERRORS[$choice];
        $this->assignValidationErrorDetails($message, $choice, $category);
    }

    /**
     * Sets a security error.
     *
     * @param int $choice
     *   The selected security error.
     */
    private function setErrorSecurity(int $choice): void
    {
        [$message, $category] = static::SECURITY_ERRORS[$choice];
        $this->assignValidationErrorDetails($message, $choice, $category);
    }
}
