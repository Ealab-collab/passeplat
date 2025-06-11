<?php

namespace PassePlat\Core\StreamProcessor;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\Exception\BadRequestException;
use PassePlat\Core\Exception\HttpException;
use GuzzleHttp\Psr7\Response;
use PassePlat\Core\Exception\UserNotFoundException;
use PassePlat\Core\Exception\WebServiceInvalidUriException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Interface for PassePlat stream processor.
 */
interface StreamProcessorInterface
{
    /**
     * Processes the request.
     *
     * @param ServerRequestInterface $request
     *   The request to process.
     * @param AnalyzableContent $analyzableContent
     *   Main object containing data for analysis.
     *
     * @throws BadRequestException
     *   The request PassePlat received did not satisfy all conditions to be processed (missing params, auth, etc).
     * @throws HttpException
     *   Exceptions thrown on HTTP errors (e.g.: destination server unreachable).
     * @throws UserNotFoundException
     *   The user authentication failed.
     * @throws WebServiceInvalidUriException
     *   No valid URI found for contacting the destination service.
     * @throws \Exception
     *   All unmanageable errors.
     */
    public function processRequest(ServerRequestInterface $request, AnalyzableContent $analyzableContent): void;

    /**
     * Processes the request using globals (ServerRequest).
     *
     * @param AnalyzableContent $analyzableContent
     *   Main object containing data for analysis.
     *
     * @throws BadRequestException
     *   The request PassePlat received did not satisfy all conditions to be processed (missing params, auth, etc).
     * @throws HttpException
     *   Exceptions thrown on HTTP errors (e.g.: destination server unreachable).
     * @throws UserNotFoundException
     *   The user authentication failed.
     * @throws WebServiceInvalidUriException
     *   No valid URI found for contacting the destination service.
     * @throws \Exception
     *   All unmanageable errors.
     */
    public function processRequestFromGlobals(AnalyzableContent $analyzableContent): void;
}
