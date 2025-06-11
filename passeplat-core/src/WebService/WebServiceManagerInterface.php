<?php

namespace PassePlat\Core\WebService;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\Exception\UserNotFoundException;
use PassePlat\Core\Exception\WebServiceInvalidUriException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for web service managers.
 */
interface WebServiceManagerInterface
{
    const QUERY_STRING__PP_DESTINATION_SCHEME_AND_HOST = 'PP_DESTINATION_SCHEME_AND_HOST';
    const QUERY_STRING__PP_DESTINATION_URL = 'PP_DESTINATION_URL';
    const QUERY_STRING__PP_USER = 'PP_USER';

    /**
     * Gets a WebService object.
     *
     * @param ServerRequestInterface $serverRequest
     *   The request.
     *
     * @return WebServiceInterface
     *   The WebService object.
     *
     * @throws UserNotFoundException
     * @throws WebServiceInvalidUriException
     * @throws UnmetDependencyException
     */
    public function getWebServiceFromRequest(
        ServerRequestInterface $serverRequest
    ): WebServiceInterface;
}
