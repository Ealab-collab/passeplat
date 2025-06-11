<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Contains request basic info.
 */
class RequestInfo extends AnalyzableContentComponentBase
{
    /**
     * Destination web service URL.
     *
     * @var string
     */
    private string $destinationUrl = '';

    /**
     * Server request object. Contains info of the request passeplat has to transfer.
     *
     * @var ServerRequestInterface|null
     */
    private ?ServerRequestInterface $serverRequest = null;

    /**
     * Loggable data extracted from server request.
     *
     * @return array
     *   Array to log.
     */
    private function extractLoggableDataFromServerRequest(): array
    {
        if (empty($this->serverRequest)) {
            // We don't have (yet?) a request to process.
            return [];
        }

        $data['http_method'] = $this->serverRequest->getMethod();

        $serverParams = $this->serverRequest->getServerParams();

        $mapping = [
            'initiator_addr' => 'REMOTE_ADDR',
            'initiator_port' => 'REMOTE_PORT',
        ];

        foreach ($mapping as $ppField => $phpField) {
            $data[$ppField] = $serverParams[$phpField];
        }

        return $data;
    }

    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        //TODO: body de la requête + secu. header
        if (!empty($this->destinationUrl)) {
            $destinationUri = new Uri($this->destinationUrl);
            $data['destination_uri_scheme'] = $destinationUri->getScheme();

            $destinationUrl = $this->destinationUrl;

            if ($destinationUri->getScheme() === 'ftp') {
                // TODO: obfuscate user info.
                $destinationUrl = '-redacted-';
            }

            $data['destination_url'] = $destinationUrl;
        }

        if (!empty($this->serverRequest)) {
            $data = array_merge($data, $this->extractLoggableDataFromServerRequest());
        }

        return $data;
    }

    /**
     * Gets the destination URL.
     *
     * @return string
     *   The URL. It may be empty if not initialized.
     */
    public function getDestinationUrl(): string
    {
        return $this->destinationUrl;
    }

    /**
     * Gets the IP address of the initiator.
     *
     * @return string|null
     *   The IP address of the initiator, if available.
     */
    public function getIpAddress(): ?string
    {
        if (empty($this->serverRequest)) {
            // We don't have (yet?) a request to process.
            return null;
        }

        $serverParams = $this->serverRequest->getServerParams();

        if ($serverParams['REMOTE_ADDR'] === '127.0.0.1') {
            // A little explanation: when our server is behind a reverse proxy, the
            // REMOTE_ADDR is the IP of the proxy, not the IP of the initiator.
            // We suppose that the proxy is configured to set the X-Real-IP header
            // to the IP of the initiator, and is transferred to the server as the
            // HTTP_X_REAL_IP header.
            return $serverParams['HTTP_X_REAL_IP'] ?? null;
        }

        return $serverParams['REMOTE_ADDR'] ?? null;
    }

    /**
     * Gets the query parameters of the request when it was received.
     *
     * @return array|null
     *   Query parameters.
     */
    public function getQueryParams(): ?array
    {
        if (empty($this->serverRequest)) {
            // We don't have (yet?) a request to process.
            return null;
        }

        // This returns the $_GET value of the request when it was received.
        return $this->serverRequest->getQueryParams();
    }

    /**
     * Gets the raw query parameters of the request when it was received.
     *
     * @return string|null
     *   Raw query parameters. May be null if no request object is set.
     */
    public function getQueryParamsRaw(): ?string
    {
        if (empty($this->serverRequest)) {
            // We don't have (yet?) a request to process.
            return null;
        }

        // This returns the $_GET value of the request when it was received.
        return $this->serverRequest->getUri()->getQuery();
    }

    /**
     * Gets the server request object.
     *
     * Be careful about editing values in this object, as it may be used by
     * other components and the main passeplat functionality.
     *
     * @return ServerRequestInterface
     *   Request object.
     */
    public function getRequest(): ServerRequestInterface
    {
        return $this->serverRequest;
    }

    /**
     * Sets the destination URL.
     *
     * @param string $destinationUrl
     *   URL as used by passeplat.
     */
    public function setDestinationUrl(string $destinationUrl)
    {
        $this->destinationUrl = $destinationUrl;
    }

    /**
     * Sets the server request from which we extract the info.
     *
     * @param ServerRequestInterface $serverRequest
     *   Server request.
     */
    public function setRequest(ServerRequestInterface $serverRequest)
    {
        $this->serverRequest = $serverRequest;
    }
}
