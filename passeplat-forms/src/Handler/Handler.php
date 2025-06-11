<?php

namespace PassePlat\Forms\Handler;

use PassePlat\Forms\Vue\Response;

abstract class Handler
{
    /**
     * Handles the DELETE method of HTTP requests.
     *
     * By default, the DELETE method is not allowed and must be overridden in a subclass of Handler to enable it.
     */
    protected function handleDeleteRequest(): void
    {
        Response::sendMethodNotAllowed();
    }

    /**
     * Handles the GET method of HTTP requests.
     *
     * By default, the GET method is not allowed and must be overridden in a subclass of Handler to enable it.
     */
    protected function handleGetRequest(): void
    {
        Response::sendMethodNotAllowed();
    }

    /**
     * Handles the OPTIONS method of HTTP requests.
     *
     * By default, the OPTIONS method is allowed because it is used by the browser for initialization.
     */
    private function handleOptionsRequest(): void
    {
        Response::sendOk('Available REST API');
    }

    /**
     * Handles the POST method of HTTP requests.
     *
     * By default, the POST method is not allowed and must be overridden in a subclass of Handler to enable it.
     */
    protected function handlePostRequest(): void
    {
        Response::sendMethodNotAllowed();
    }

    /**
     * Handles the PUT method of HTTP requests.
     *
     * By default, the PUT method is not allowed and must be overridden in a subclass of Handler to enable it.
     */
    protected function handlePutRequest(): void
    {
        Response::sendMethodNotAllowed();
    }

    /**
     * Handles the HTTP request according to the appropriate method.
     */
    public function handleRequest(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD']);

        // Checking the HTTP method.
        switch ($method) {
            case 'POST':
                $this->handlePostRequest();
                break;
            case 'GET':
                $this->handleGetRequest();
                break;
            case 'PUT':
                $this->handlePutRequest();
                break;
            case 'DELETE':
                $this->handleDeleteRequest();
                break;
            case 'OPTIONS':
                $this->handleOptionsRequest();
                break;
            default:
                Response::sendMethodNotAllowed();
        }
    }
}
