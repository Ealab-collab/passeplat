<?php

namespace PassePlat\Openapi\Tool\Validation;

use League\OpenAPIValidation\PSR7\MessageValidator;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ResponseAddress;
use League\OpenAPIValidation\PSR7\ResponseValidator as LeagueResponseValidator;
use League\OpenAPIValidation\PSR7\Validators\BodyValidator\BodyValidator;
use League\OpenAPIValidation\PSR7\Validators\HeadersValidator;
use PassePlat\Openapi\Exception\InitializationFailureException;
use PassePlat\Openapi\Tool\OpenApi\OpenApiSpecHandler;
use Psr\Http\Message\ResponseInterface;

/**
 * This class provides the capability to validate the response
 * as a whole or specific elements independently against an OpenAPI specification.
 */
class ResponseValidator
{
    /**
     * An OpenApiSpecHandler that presents the OpenAPI specification file and offers various methods to process it.
     *
     * @var OpenApiSpecHandler
     */
    private OpenApiSpecHandler $openApiSpecHandler;

    /**
     * The HTTP response interface to be validated.
     *
     * @var ResponseInterface
     */
    private ResponseInterface $response;

    /**
     * An object representing the address of the response, containing the path, method, and status code.
     *
     * @var ResponseAddress
     */
    private ResponseAddress $responseAddress;

    /**
     * Validate the response body.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the response body is valid, otherwise a validation error object explaining the error.
     */
    public function checkBodyErrors(ValidationError $error)
    {
        $bodyValidator = new BodyValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($bodyValidator, $error);
    }

    /**
     * Validate the entire response.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the response is valid, otherwise a validation error object explaining the error.
     */
    public function checkErrors(ValidationError $error)
    {
        try {
            $responseValidator = new LeagueResponseValidator($this->openApiSpecHandler->getSpec());
            $responseValidator->validate($this->responseAddress, $this->response);
            return false;
        } catch (\Exception $e) {
            $error->setAsResponse();
            ValidationErrorUpdater::update($e, $error);
            return $error;
        }
    }

    /**
     * Validate the response headers.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the response headers are valid, otherwise a validation error object explaining the error.
     */
    public function checkHeadersErrors(ValidationError $error)
    {
        $headersValidator = new HeadersValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($headersValidator, $error);
    }

    /**
     * Validate a response message.
     *
     * @param MessageValidator $messageValidator
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the response message is valid, otherwise a validation error object.
     */
    private function checkMessageErrors(MessageValidator $messageValidator, ValidationError $error)
    {
        try {
            $messageValidator->validate($this->responseAddress, $this->response);
            return false;
        } catch (\Exception $e) {
            $error->setAsResponse();
            ValidationErrorUpdater::update($e, $error);
            return $error;
        }
    }

    /**
     * Initialize the object.
     *
     * @param OperationAddress $opAddr
     *   An OperationAddress object that represents the path of the request.
     * @param OpenApiSpecHandler $openApiSpecHandler
     *   A OpenApiSpecHandler object.
     * @param ResponseInterface $response
     *   The HTTP response to validate.
     *
     * @throws InitializationFailureException
     *   If the object is not properly initialized
     */
    public function init(
        OperationAddress $opAddr,
        OpenApiSpecHandler $openApiSpecHandler,
        ResponseInterface $response
    ): void {
        try {
            $this->openApiSpecHandler = $openApiSpecHandler;

            $this->response = $response;

            $this->responseAddress = new ResponseAddress(
                $opAddr->path(),
                $opAddr->method(),
                $response->getStatusCode()
            );
        } catch (\Exception $e) {
            throw new InitializationFailureException($e->getMessage());
        }
    }
}
