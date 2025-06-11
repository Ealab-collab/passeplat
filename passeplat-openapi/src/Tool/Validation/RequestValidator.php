<?php

namespace PassePlat\Openapi\Tool\Validation;

use Dakwamine\Component\ComponentBasedObject;
use League\OpenAPIValidation\PSR7\MessageValidator;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\Validators\BodyValidator\BodyValidator;
use League\OpenAPIValidation\PSR7\Validators\CookiesValidator\CookiesValidator;
use League\OpenAPIValidation\PSR7\Validators\HeadersValidator;
use League\OpenAPIValidation\PSR7\Validators\PathValidator;
use League\OpenAPIValidation\PSR7\Validators\QueryArgumentsValidator;
use League\OpenAPIValidation\PSR7\RequestValidator as LeagueRequestValidator;
use League\OpenAPIValidation\PSR7\Validators\SecurityValidator;
use PassePlat\Openapi\Exception\InitializationFailureException;
use PassePlat\Openapi\Exception\MissingParameterException;
use PassePlat\Openapi\Tool\OpenApi\OpenApiSpecHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * This class provides the capability to validate the request
 * as a whole or specific elements independently against an OpenAPI specification.
 */
class RequestValidator extends ComponentBasedObject
{
    /**
     * An OpenApiSpecHandler that presents the OpenAPI specification file and offers various methods to process it.
     *
     * @var OpenApiSpecHandler
     */
    private OpenApiSpecHandler $openApiSpecHandler;

    /**
     * An associative array containing path information, that corresponds to the request path.
     *
     * Otherwise, it is empty if no corresponding path is found.
     *
     * @var array
     */
    private array $pathInfo;

    /**
     * The HTTP request interface to be validated.
     *
     * @var RequestInterface
     */
    private RequestInterface $request;

    /**
     * The HTTP method of the request (GET, POST, etc.).
     */
    private string $requestMethod;

    /**
     * The path of the request.
     */
    private string $requestPath;

    /**
     * Validate the request body.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *  False if the request body is valid, otherwise an validation error object with error detail.
     */
    public function checkBodyErrors(ValidationError $error)
    {
        $bodyValidator = new BodyValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($bodyValidator, $error);
    }

    /**
     * Validate the request cookies.
     *
     * @param ValidationError $error
     * @return false|ValidationError
     *   False if the cookies are valid, otherwise a validation error object with error detail.
     */
    public function checkCookiesErrors(ValidationError $error)
    {
        $cookiesValidator = new CookiesValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($cookiesValidator, $error);
    }

    /**
     * Validate the entire request.
     *
     * @param ValidationError $error
     *   A validation error object.
     *
     * @return false|ValidationError
     *   Validation error object with error detail if the request has errors, otherwise false.
     */
    public function checkErrors(ValidationError $error)
    {
        try {
            $requestValidator = new LeagueRequestValidator($this->openApiSpecHandler->getSpec());
            $requestValidator->validate($this->getRequest());
            return false;
        } catch (\Exception $e) {
            $error->setAsRequest();
            ValidationErrorUpdater::update($e, $error);
            return $error;
        }
    }

    /**
     * Validate the request headers.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the headers are valid, otherwise a validation error object with error detail.
     */
    public function checkHeadersErrors(ValidationError $error)
    {
        $headersValidator = new HeadersValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($headersValidator, $error);
    }

    /**
     * Validate a request message.
     *
     * A message is any object that implements the MessageValidator interface.
     *
     * @param MessageValidator $messageValidator
     *   A MessageValidator can be an instance of HeadersValidator, BodyValidator, etc.
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the request message is valid, otherwise a validation error object.
     */
    private function checkMessageErrors(MessageValidator $messageValidator, ValidationError $error)
    {
        try {
            $pathInfo = $this->getPathInfo();

            if (!$pathInfo) {
                $error->setAsRequest();
                $error->setAsSchema();
                $error->setMessage(
                    "No path found in the specification file matching " . $this->getRequestPath(),
                    21
                );

                return $error;
            }

            $error->setSpecPath($pathInfo['pathItem']);

            $opAddr = new OperationAddress($pathInfo['pathSpec'], $this->getRequestMethod());

            $messageValidator->validate($opAddr, $this->getRequest());
            return false;
        } catch (\Exception $e) {
            $error->setAsRequest();
            ValidationErrorUpdater::update($e, $error);
            return $error;
        }
    }

    /**
     * Validate the request path.
     *
     * Validate if the request path matches any specified path in the OpenAPI specification,
     * disregarding the method and parameters.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the path is valid, otherwise a validation error object error detail.
     */
    public function checkPathErrors(ValidationError $error)
    {
        $pathInfo = $this->getPathInfo();

        if ($pathInfo) {
            $error->setSpecPath($pathInfo['pathItem']);
            return false;
        }

        $error->setAsRequest();
        $error->setAsSchema();
        $error->setMessage(
            "No path found in the specification file matching '{$this->getRequestPath()}'",
            21
        );

        return $error;
    }

    /**
     * Validate if the request method matches any specified method for the request path.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the method is valid, otherwise a validation error object explaining the error.
     *
     * @throws MissingParameterException
     */
    public function checkPathMethodErrors(ValidationError $error)
    {
        // Check if the specified method exists for the request path in the OpenAPI specification.
        return $this->openApiSpecHandler->pathHasMethod($this->getRequestPath(), $this->getRequestMethod(), $error);
    }

    /**
     * Validate the complete path, including the method and parameters.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the entire path is valid, otherwise a validation error object explaining the error.
     */
    public function checkPathMethodParametersErrors(ValidationError $error)
    {
        $pathValidator = new PathValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($pathValidator, $error);
    }

    /**
     * Validate the request query arguments.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the query arguments are valid, otherwise a validation object error with error detail.
     */
    public function checkQueryArgumentsErrors(ValidationError $error)
    {
        $queryArgumentsValidator = new QueryArgumentsValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($queryArgumentsValidator, $error);
    }

    /**
     * Validate the request security.
     *
     *  The method checks whether a valid security schema is found; otherwise, it returns false.
     *  This validator functions as a logical OR operator.
     *  Reference: https://swagger.io/docs/specification/authentication/
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if any security schema is valid, otherwise a validation error object with error detail.
     */
    public function checkSecurityErrors(ValidationError $error)
    {
        $securityValidator = new SecurityValidator($this->openApiSpecHandler->getFinder());

        return $this->checkMessageErrors($securityValidator, $error);
    }

    /**
     * Get the operation address corresponding to the request path.
     *
     * An OperationAddress is an object that contains both the path specified
     * in the OpenAPI specification and the HTTP method corresponding to the request path.
     *
     * @return OperationAddress|null
     *  The OperationAddress object if it is found; otherwise, null is returned.
     */
    public function getOperationAddress(): ?OperationAddress
    {
        $pathInfo = $this->getPathInfo();

        if (!$pathInfo) {
            return null;
        }

        return new OperationAddress($pathInfo['pathSpec'], $this->getRequestMethod());
    }

    /**
     * Get information about the path.
     *
     * An associative array contains information about the path,
     * such as the PathItem and the corresponding path specified in the specification file.
     * Otherwise, it returns an empty array if there is no match.
     *
     * @return array<string, mixed>
     *   An associative array with two keys 'pathSpec' and 'pathItem' or an empty array.
     */
    private function getPathInfo(): array
    {
        return $this->pathInfo;
    }

    /**
     * Get the HTTP request.
     *
     * @return RequestInterface
     */
    private function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    private function getRequestMethod(): string
    {
        return $this->requestMethod;
    }

    /**
     * Get the request path.
     *
     * @return string
     */
    private function getRequestPath(): string
    {
        return $this->requestPath;
    }

    /**
     * Get the URI of the request.
     *
     * @return UriInterface
     */
    private function getUri(): UriInterface
    {
        return $this->getRequest()->getUri();
    }

    /**
     * Initialize the RequestValidator object.
     *
     * @param RequestInterface $request
     *   The HTTP request to validate.
     * @param OpenApiSpecHandler $openApiSpecHandler
     *   A OpenApiSpecHandler object.
     *
     * @throws InitializationFailureException
     *   If an error occurs during the initialization.
     */
    public function init(RequestInterface $request, OpenApiSpecHandler $openApiSpecHandler): void
    {
        try {
            $this->openApiSpecHandler = $openApiSpecHandler;

            $this->request = $request;

            $this->requestPath = $this->getUri()->getPath();

            $this->requestMethod = strtolower($this->request->getMethod());

            $this->pathInfo = $this->openApiSpecHandler->pathExists($this->getRequestPath());
        } catch (InitializationFailureException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InitializationFailureException($e->getMessage());
        }
    }
}
