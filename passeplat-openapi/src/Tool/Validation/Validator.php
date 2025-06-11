<?php

namespace PassePlat\Openapi\Tool\Validation;

use Dakwamine\Component\ComponentBasedObject;
use PassePlat\Openapi\Exception\InitializationFailureException;
use PassePlat\Openapi\Exception\MissingParameterException;
use PassePlat\Openapi\Exception\ValidationFailureException;
use PassePlat\Openapi\Tool\OpenApi\OpenApiSpecHandler;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * This is a class that provides several methods for validating certain elements,
 * both in request and in response.
 */
class Validator extends ComponentBasedObject
{
    /**
     * A validator for HTTP requests, responsible for validating request elements against the OpenAPI specification.
     *
     * @var RequestValidator
     */
    private RequestValidator $requestValidator;

    /**
     * A validator for HTTP responses, responsible for validating response elements against the OpenAPI specification.
     *
     * @var ResponseValidator
     */
    private ResponseValidator $responseValidator;

    /**
     * Validates the request cookies.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the cookies are valid; otherwise, a validation error object.
     */
    public function checkCookiesErrors(ValidationError $error)
    {
        return $this->requestValidator->checkCookiesErrors($error);
    }

    /**
     * Validates the request path.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the path is valid; otherwise, a validation error object.
     */
    public function checkPathErrors(ValidationError $error)
    {
        return $this->requestValidator->checkPathErrors($error);
    }

    /**
     * Validates the request path method.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the method is valid; otherwise, a validation error object.
     *
     * @throws MissingParameterException
     */
    public function checkPathMethodErrors(ValidationError $error)
    {
        return $this->requestValidator->checkPathMethodErrors($error);
    }

    /**
     * Validate the complete path, including the method and parameters.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the complete path is valid; otherwise, a validation object error.
     */
    public function checkPathMethodParametersErrors(ValidationError $error)
    {
        return $this->requestValidator->checkPathMethodParametersErrors($error);
    }

    /**
     * Validates the request query arguments.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the query arguments are valid; otherwise, a validation error object.
     */
    public function checkQueryArgumentsErrors(ValidationError $error)
    {
        return $this->requestValidator->checkQueryArgumentsErrors($error);
    }

    /**
     * Validates the request body.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the body is valid; otherwise, a validation object error.
     */
    public function checkRequestBodyErrors(ValidationError $error)
    {
        return $this->requestValidator->checkBodyErrors($error);
    }

    /**
     * Validates the entire request.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the request is valid, otherwise a validation error object.
     */
    public function checkRequestErrors(ValidationError $error)
    {
        return $this->requestValidator->checkErrors($error);
    }

    /**
     * Validates the request headers.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the headers are valid; otherwise, a validation object error.
     */
    public function checkRequestHeadersErrors(ValidationError $error)
    {
        return $this->requestValidator->checkHeadersErrors($error);
    }

    /**
     * Validates the response body.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the body is valid; otherwise, a validation object error.
     *
     * @throws ValidationFailureException
     *   If the ResponseValidator object is unavailable.
     *   It's likely because the response, which is optional, has not been provided.
     */
    public function checkResponseBodyErrors(ValidationError $error)
    {
        if (empty($this->responseValidator)) {
            throw new ValidationFailureException('It is impossible to validate the response body.');
        }

        return $this->responseValidator->checkBodyErrors($error);
    }

    /**
     * Validates the entire response.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the response is valid; otherwise, an error message.
     *
     * @throws ValidationFailureException
     *   If the ResponseValidator object is unavailable.
     *   It's likely because the response, which is optional, has not been provided.
     */
    public function checkResponseErrors(ValidationError $error)
    {
        if (empty($this->responseValidator)) {
            throw new ValidationFailureException('It is impossible to validate the response.');
        }

        return $this->responseValidator->checkErrors($error);
    }

    /**
     * Validates the response headers.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the headers are valid; otherwise, a validation object error.
     *
     * @throws ValidationFailureException
     *   If the ResponseValidator object is unavailable.
     *   It's likely because the response, which is optional, has not been provided.
     */
    public function checkResponseHeadersErrors(ValidationError $error)
    {
        if (empty($this->responseValidator)) {
            throw new ValidationFailureException('It is impossible to validate the response headers.');
        }

        return $this->responseValidator->checkHeadersErrors($error);
    }

    /**
     * Validates the security schemas of the request.
     *
     * It checks whether any of the security schemas are valid for the request.
     *
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the security is valid; otherwise, a validation error object.
     */
    public function checkSecurityErrors(ValidationError $error)
    {
        return $this->requestValidator->checkSecurityErrors($error);
    }

    /**
     * Initializes the Validator object using a OpenApiSpecHandler object.
     *
     * @param OpenApiSpecHandler $openApiSpecHandler
     *   A OpenApiSpecHandler object.
     * @param RequestInterface $request
     *   The HTTP request to validate.
     * @param ResponseInterface|null $response
     *   The HTTP response to validate.
     *
     * @throws InitializationFailureException
     *   If an error occurs during the initialization.
     */
    private function initFromOpenApiSpecHandler(
        OpenApiSpecHandler $openApiSpecHandler,
        RequestInterface $request,
        ?ResponseInterface $response
    ): void {
        try {
            $this->requestValidator = $this->getComponentByClassName(RequestValidator::class, true);
            $this->requestValidator->init($request, $openApiSpecHandler);

            $opAddr = $this->requestValidator->getOperationAddress();

            if (!empty($response) && !empty($opAddr)) {
                $this->responseValidator = $this->getComponentByClassName(ResponseValidator::class, true);
                $this->responseValidator->init($opAddr, $openApiSpecHandler, $response);
            }
        } catch (InitializationFailureException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InitializationFailureException($e->getMessage());
        }
    }

    /**
     * Initializes the Validator object using a YAML content.
     *
     * @param string $yamlContent
     *   A string containing an OpenAPI specification in YAML format.
     * @param RequestInterface $request
     *   The HTTP request to validate.
     * @param ResponseInterface|null $response
     *   The HTTP response to validate.
     *
     * @throws InitializationFailureException
     *   If an error occurs during the initialization.
     */
    public function initFromYamlContent(
        string $yamlContent,
        RequestInterface $request,
        ?ResponseInterface $response
    ): void {
        try {
            $openApiSpecHandler = $this->getComponentByClassName(OpenApiSpecHandler::class, true);

            $openApiSpecHandler->initFromYamlContent($yamlContent);

            $this->initFromOpenApiSpecHandler($openApiSpecHandler, $request, $response);
        } catch (InitializationFailureException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InitializationFailureException($e->getMessage());
        }
    }

    /**
     * Initializes the Validator object using a YAML file.
     *
     * @param string $relativeYamlFilePath
     *   The relative path to the OpenAPI specification YAML file.
     * @param RequestInterface $request
     *   The HTTP request to validate.
     * @param ResponseInterface|null $response
     *   The HTTP response to validate.
     *
     * @throws InitializationFailureException
     *   If an error occurs during the initialization.
     */
    public function initFromYamlFile(
        string $relativeYamlFilePath,
        RequestInterface $request,
        ?ResponseInterface $response
    ): void {
        try {
            $openApiSpecHandler = $this->getComponentByClassName(OpenApiSpecHandler::class, true);

            $openApiSpecHandler->initFromYamlFile($relativeYamlFilePath);

            $this->initFromOpenApiSpecHandler($openApiSpecHandler, $request, $response);
        } catch (InitializationFailureException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InitializationFailureException($e->getMessage());
        }
    }
}
