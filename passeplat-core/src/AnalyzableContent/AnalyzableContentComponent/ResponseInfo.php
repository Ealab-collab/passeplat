<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent;

use Psr\Http\Message\ResponseInterface;

/**
 * Contains response basic info.
 */
class ResponseInfo extends AnalyzableContentComponentBase
{
    /**
     * Response HTTP status code sent by the server.
     *
     * @var ResponseInterface
     */
    private $response;

    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        if (empty($this->response)) {
            return $data;
        }

        $data['destination_response_http_status_code'] = (int)$this->response->getStatusCode();

        return $data;
    }

    /**
     * Gets the response object.
     *
     * @return ResponseInterface
     *   Response object.
     */
    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }

    /**
     * Gets the response status code.
     *
     * @return int
     *   The response status code.
     */
    public function getStatusCode(): int
    {
        if (isset($this->statusCode)) {
            // The status code was set manually to replace the one from the response object.
            return (int)$this->statusCode;
        }
        return (int)$this->response->getStatusCode();
    }

    /**
     * Sets the response object to analyse.
     *
     * @param ResponseInterface $response
     *   Response object to analyse.
     */
    public function setResponse(ResponseInterface $response): void
    {
        $this->response = $response;
    }
}
