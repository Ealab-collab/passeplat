<?php

namespace PassePlat\Openapi\Tool\Validation;

use Dakwamine\Component\ComponentBasedObject;

/**
 * Represents a validation error.
 *
 * This class encapsulates information about a validation error,
 * including its category, code, message, severity level, timestamp, etc.
 */
class ValidationError extends ComponentBasedObject
{
    private const CATEGORY_PARSING = 'Parsing';
    private const CATEGORY_SCHEMA = 'Schema';
    private const CATEGORY_VALUE = 'Value';

    private const ITEM_BODY = 'Body';
    private const ITEM_COOKIES = 'Cookies';
    private const ITEM_HEADERS = 'Headers';
    private const ITEM_PATH = 'Path';
    private const ITEM_QUERY = 'Query';
    private const ITEM_SECURITY = 'Security';

    /**
     * Maximum severity level allowed.
     *
     * Defines the upper limit for severity_level, ensuring errors do not exceed this level.
     */
    private const MAX_SEVERITY_LEVEL = 3;

    private const TYPE_REQUEST = 'Request';
    private const TYPE_RESPONSE = 'Response';

    /**
     * Category of error ("Parsing", "Schema", or "Value").
     *
     * Classifies the error into Parsing, schema, or value validation issues.
     */
    private string $category = '';

    /**
     * Represents the numeric code associated with the error.
     *
     * It stores a numeric identifier that categorizes and specifies the type of error encountered.
     * It follows a structured format where:
     * - Thousands indicate specific error types:
     *   - 1xxx: Indicates a "Request" error.
     *   - 2xxx: Indicates a "Response" error.
     *   - 0xxx: Indicates an uncategorized or undefined error.
     * - Hundreds classify errors into specific categories:
     *   - x1xx: Parsing errors.
     *   - x2xx: Schema errors.
     *   - x3xx: Value validation errors.
     * - Tens classify errors into specific elements:
     *   - xx1x: Body errors.
     *   - xx2x: Cookies errors.
     *   - xx3x: Headers errors.
     *   - xx4x: Path errors.
     *   - xx5x: Query errors.
     *   - xx6x: Security errors.
     * - Units digits are used to effectively classify various error messages.
     * - A digit 0 in any position signifies "not yet classified.
     */
    private int $code = 0;

    /**
     * Error message.
     *
     * Holds the descriptive message of the error.
     */
    private string $message = '';

    /**
     * HTTP method associated with the error (GET, POST, etc.).
     */
    private string $method = '';

    /**
     * Type of error ("Request" or "Response").
     *
     * Indicates whether the error is related to a request or a response.
     */
    private string $request_or_response = '';

    //TODO
    // The severity level should be done by IA.
    /**
     * Severity level of the error.
     *
     * Measures the severity level of the error, ranging from 0 (least severe) to MAX_SEVERITY_LEVEL (most severe).
     */
    private int $severity_level = 0;

    /**
     * Specification path associated with the error.
     *
     * Stores the request path that triggered the error.
     */
    private string $specPath = 'N/A';

    /**
     * The timestamp of error.
     *
     * It is assigned when the error message is set.
     *
     * @var string|null
     */
    private ?string $timestamp = null;

    /**
     * Indicate whether the date_default_timezone is well configured or not.
     *
     * @var bool
     */
    private static bool $timezoneConfigured = false;

    /**
     * Represents the specific item where the error occurred (e.g., "Body", "Cookies", "Headers", etc.).
     */
    private string $item = '';

    /**
     * Gets the error category.
     *
     * @return string
     *   The error category.
     */
    public function getCategory(): string
    {
        return $this->category;
    }

    /**
     * Gets the error code.
     *
     * @return int
     *   The error code.
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * Gets the specific item where the error occurred.
     *
     * @return string
     *   The specific item.
     */
    public function getItem(): string
    {
        return $this->item;
    }

    /**
     * Gets the error message.
     *
     * @return string
     *   The error message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Gets the HTTP method associated with the error.
     *
     * @return string
     *   The HTTP method (e.g., GET, POST).
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Gets the severity level of the error.
     *
     * @return int
     *   The severity level.
     */
    public function getSeverityLevel(): int
    {
        return $this->severity_level;
    }

    /**
     * Gets the specification path associated with the error.
     *
     * @return string
     *   The request path.
     */
    public function getSpecPath(): string
    {
        return $this->specPath;
    }

    /**
     * Gets the timestamp of the error.
     *
     * It is assigned when the message error be set.
     *
     * @return int|null
     */
    public function getTimestamp(): ?int
    {
        return $this->timestamp;
    }

    /**
     * Gets the error type (request or response).
     *
     * @return string
     *   The error type.
     */
    public function getType(): string
    {
        return $this->request_or_response;
    }

    /**
     * Increments the severity level of the error, capped at the maximum severity level.
     */
    public function incrementSeverityLevel(): void
    {
        if ($this->severity_level < self::MAX_SEVERITY_LEVEL) {
            $this->severity_level++;
        }
    }

    /**
     * Resets all attributes to their default values.
     */
    public function reset()
    {
        $this->category = '';
        $this->code = 0;
        $this->message = '';
        $this->request_or_response = '';
        $this->severity_level = 0;
        $this->timestamp = null;
        $this->item = '';
        $this->specPath = '';
        $this->method = '';
    }

    /**
     * Sets the error item to body.
     */
    public function setAsBody(): void
    {
        // Ensure the code has 10 for body errors.
        $this->code = intdiv($this->code, 100) * 100 + 10 + ($this->code % 10);
        $this->item = static::ITEM_BODY;
    }

    /**
     * Sets the error item to cookies.
     */
    public function setAsCookies(): void
    {
        // Ensure the code has 20 for cookies errors.
        $this->code = intdiv($this->code, 100) * 100 + 20 + ($this->code % 10);
        $this->item = static::ITEM_COOKIES;
    }

    /**
     * Sets the error item to headers.
     */
    public function setAsHeaders(): void
    {
        // Ensure the code has 30 for headers errors.
        $this->code = intdiv($this->code, 100) * 100 + 30 + ($this->code % 10);
        $this->item = static::ITEM_HEADERS;
    }

    /**
     * Sets the error category to parsing.
     */
    public function setAsParsing(): void
    {
        // Ensure the code has 100 for parsing errors.
        $this->code = intdiv($this->code, 1000) * 1000 + 100 + ($this->code % 100);
        $this->category = static::CATEGORY_PARSING;
    }

    /**
     * Sets the error item to Path.
     */
    public function setAsPath(): void
    {
        // Ensure the code has 40 for path errors.
        $this->code = intdiv($this->code, 100) * 100 + 40 + ($this->code % 10);
        $this->item = static::ITEM_PATH;
    }

    /**
     * Sets the error item to Query.
     */
    public function setAsQuery(): void
    {
        // Ensure the code has 50 for query errors.
        $this->code = intdiv($this->code, 100) * 100 + 50 + ($this->code % 10);
        $this->item = static::ITEM_QUERY;
    }

    /**
     * Sets the error type to request.
     */
    public function setAsRequest(): void
    {
        // Ensure the code starts with 1000 for request errors.
        $this->code = 1000 + ($this->code % 1000);
        $this->request_or_response = static::TYPE_REQUEST;
    }

    /**
     * Sets the error type to response.
     */
    public function setAsResponse(): void
    {
        // Ensure the code starts with 2000 for response errors.
        $this->code = 2000 + ($this->code % 1000);
        $this->request_or_response = static::TYPE_RESPONSE;
    }

    /**
     * Sets the error category to schema.
     */
    public function setAsSchema(): void
    {
        // Ensure the code has 200 for schema errors.
        $this->code = intdiv($this->code, 1000) * 1000 + 200 + ($this->code % 100);
        $this->category = static::CATEGORY_SCHEMA;
    }

    /**
     * Sets the error item to Security.
     */
    public function setAsSecurity(): void
    {
        // Ensure the code has 60 for security errors.
        $this->code = intdiv($this->code, 100) * 100 + 60 + ($this->code % 10);
        $this->item = static::ITEM_SECURITY;
    }

    /**
     * Sets the error category to value.
     */
    public function setAsValue(): void
    {
        // Ensure the code has 300 for value errors.
        $this->code = intdiv($this->code, 1000) * 1000 + 300 + ($this->code % 100);
        $this->category = static::CATEGORY_VALUE;
    }

    /**
     * Sets the error message and the message code.
     *
     * @param string $message
     *   The error message.
     * @param int $code_message
     *   The message code, must be between 0 and 9.
     */
    public function setMessage(string $message, int $code_message): void
    {
        if ($code_message > 9 || $code_message < 0) {
            $code_message = 0;
        }

        if (empty($this->timestamp)) {
            $this->setTimezone();
            $this->setTimestamp(time());
        }

        // Update the message code while keeping the rest of the code intact
        $this->code = ($this->code - $this->code % 10) + $code_message;
        $this->message = $message;
    }

    /**
     * Sets the HTTP method associated with the error.
     *
     * @param string $method
     *   The HTTP method (e.g., GET, POST).
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    /**
     * Sets the specification path associated with the error.
     *
     * @param string $path
     *   The request path.
     */
    public function setSpecPath(string $path): void
    {
        $this->specPath = $path;
    }

    /**
     * Sets the timestamp of the error.
     *
     * @param int $timestamp
     *   The timestamp to be set.
     */
    public function setTimestamp(int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    /**
     * Sets the default timezone to UTC if it hasn't been set already.
     */
    public function setTimezone(): void
    {
        if (!static::$timezoneConfigured) {
            date_default_timezone_set('UTC');
            static::$timezoneConfigured = true;
        }
    }
}
