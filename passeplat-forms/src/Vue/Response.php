<?php

namespace PassePlat\Forms\Vue;

/**
 * Provides methods for sending HTTP responses with JSON data and appropriate status codes.
 */
class Response
{
    // Define HTTP status codes.
    private const HTTP_BAD_REQUEST = 400;
    private const HTTP_INTERNAL_SERVER_ERROR = 500;
    private const HTTP_METHOD_NOT_ALLOWED = 405;
    private const HTTP_OK = 200;

    /**
     * Encodes data to JSON.
     *
     * @param mixed $data
     *   The data to encode.
     *
     * @return false|string
     *   The JSON-encoded data, otherwise false.
     */
    private static function encodeJson($data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Sends an HTTP response with a status code and JSON data.
     *
     * @param int $statusCode
     *   The HTTP status code.
     * @param mixed $data
     *   The data to send.
     */
    private static function send(int $statusCode, $data): void
    {
        //TODO fix the different CORS Headers.
        // Replace '*' by a specific domain if necessary.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Referer');

        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo static::encodeJson($data);
    }

    /**
     * Sends a Bad Request response with an error message.
     *
     * @param string $message
     *   The error message.
     */
    public static function sendBadRequest(string $message): void
    {
        static::send(static::HTTP_BAD_REQUEST, ['error' => $message]);
    }

    /**
     * Sends an Internal Server Error response with an error message.
     */
    public static function sendInternalServerError(): void
    {
        static::send(static::HTTP_INTERNAL_SERVER_ERROR, ['error' => 'Internal Server Error']);
    }

    /**
     * Sends a Method Not Allowed response.
     */
    public static function sendMethodNotAllowed(): void
    {
        static::send(static::HTTP_METHOD_NOT_ALLOWED, ['error' => 'Method Not Allowed.']);
    }

    /**
     * Sends response data with a status code 200.
     *
     * @param mixed $data
     *   The data to send.
     */
    public static function sendOk($data): void
    {
        static::send(static::HTTP_OK, $data);
    }
}
