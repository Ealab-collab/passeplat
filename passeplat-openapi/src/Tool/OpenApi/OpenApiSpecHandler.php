<?php

namespace PassePlat\Openapi\Tool\OpenApi;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\PathItem;
use cebe\openapi\SpecBaseObject;
use League\OpenAPIValidation\PSR7\SpecFinder;
use PassePlat\Openapi\Exception\InitializationFailureException;
use PassePlat\Openapi\Exception\MissingParameterException;
use PassePlat\Openapi\Exception\MissingStrategyException;
use PassePlat\Openapi\Tool\Validation\ValidationError;

/**
 * Handles operations related to OpenAPI specifications, such as initialization, updating,
 * clearing, and verifying paths and methods within the specification.
 */
class OpenApiSpecHandler
{
    //TODO
    // Each user should have a separate sub-folder.
    /**
     * Default folder for saving openapi files.
     */
    const DEFAULT_FOLDER = 'config/app/spec/';

    /**
     * The path to the OpenAPI file.
     */
    private string $filePath;

    /**
     * Instance of SpecFinder from Thephpleague library.
     *
     * Facilitates retrieval of specific parts of an OpenAPI spec.
     *
     * @var SpecFinder
     */
    private SpecFinder $finder;

    /**
     * An instance of the OpenApi class from the Cebe library which represents an OpenAPI specification.
     *
     * @var OpenApi
     */
    private OpenApi $spec;

    /**
     * Strategy used for updating the OpenAPI specification.
     *
     * @var SpecUpdaterStrategy
     */
    private SpecUpdaterStrategy $specUpdaterStrategy;

    /**
     * Clears the content of the OpenAPI file and initializes it with a default structure.
     */
    public function clear(): void
    {
        $openYaml = <<<YAML
        openapi: 3.0.0
        info:
            title: OpenAPI
            version: 1.0.0
        paths: {}
        YAML;

        file_put_contents($this->getPath(), $openYaml);
    }

    /**
     * Deletes the OpenAPI file.
     */
    public function delete(): void
    {
        unlink($this->getPath());
        $this->filePath = '';
    }

    /**
     * Get the SpecFinder object.
     *
     * @return SpecFinder
     */
    public function getFinder(): SpecFinder
    {
        return $this->finder;
    }

    /**
     * Generates a unique hash-based file name using the user ID and API ID.
     *
     * @return string
     *   The generated hash.
     */
    public function getHashId(): string
    {
        //TODO
        // It should be a hash function of user ID and the API ID.
        return static::DEFAULT_FOLDER . 'BaseFile' . '.yaml';
    }

    /**
     * Retrieves the file path of the OpenAPI file.
     *
     * @return string
     *   The file path of the OpenAPI file.
     */
    public function getPath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the OpenAPI specification object.
     *
     * @return OpenApi
     */
    public function getSpec(): OpenApi
    {
        return $this->spec;
    }

    /**
     * Generates a temporary file name for OpenAPI with a unique identifier and the specified extension.
     *
     * @param string $extension
     *   The extension for the temporary file.
     *
     * @return string
     *   The generated temporary file name.
     */
    public function getTemporaryFileName(string $extension): string
    {
        return static::DEFAULT_FOLDER .
            uniqid('_', true) .
            '.' .
            $extension;
    }

    /**
     * Initializes the OpenApiSpecHandler object with the provided file path.
     *
     * @param string $filePath
     *   The path to the OpenAPI file.
     */
    public function init(string $filePath): void
    {
        $this->filePath = $filePath;
    }

    /**
     * Initialize the object from YAML content.
     *
     * @param string $yamlContent
     *   A YAML string representing an OpenAPI specification.
     *
     * @throws InitializationFailureException
     *   If an error occurs during the initialization.
     */
    public function initFromYamlContent(string $yamlContent): void
    {
        try {
            // Read the OpenAPI specification from the YAML content.
            $spec = Reader::readFromYaml($yamlContent);

            // Validate the specification.
            if (!$spec instanceof SpecBaseObject || !$spec->validate()) {
                throw new InitializationFailureException('Invalid OpenAPI specification.');
            }

            $this->spec = $spec;
            $this->finder = new SpecFinder($spec);
        } catch (InitializationFailureException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InitializationFailureException($e->getMessage());
        }
    }

    /**
     * Initialize the object from a YAML file.
     *
     * @param string $relativeYamlFilePath
     *   The relative path that represents the OpenAPI specification file.
     *
     * @throws InitializationFailureException
     *   If an error occurs during the initialization.
     */
    public function initFromYamlFile(string $relativeYamlFilePath): void
    {
        try {
            // Get the absolute path of the YAML file.
            $absoluteYamlFilePath = realpath($relativeYamlFilePath);

            // Check if the file path is valid.
            if (!$absoluteYamlFilePath) {
                throw new InitializationFailureException('Invalid YAML file path.');
            }

            $this->initFromYamlContent(file_get_contents($absoluteYamlFilePath));
        } catch (InitializationFailureException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InitializationFailureException($e->getMessage()) ;
        }
    }

    /**
     * Opens the OpenAPI file, creating it if it doesn't exist.
     */
    public function open(): void
    {
        // If the file doesn't exist, create it and initialize with a default OpenAPI structure.
        if (!file_exists($this->getPath())) {
            touch($this->getPath());
            $this->clear();
        }
    }

    /**
     * Check if a corresponding path exists in the OpenAPI specification.
     *
     * @param string $path
     *   The path to be checked.
     *
     * @return array
     *   An array containing 'pathSpec' and 'pathItem' keys if the path exists; otherwise an empty array.
     */
    public function pathExists(string $path): array
    {
        // Iterates over the paths specified in the OpenAPI specification.
        foreach ($this->getSpec()->paths as $pathSpec => $pathItem) {
            // Converts the specified path into a regular expression.
            $pattern = '#' . preg_replace('#{[^}]+}#', '[^/]+', $pathSpec) . '/?$#';

            // Checks if the request path matches a specified path.
            if (preg_match($pattern, $path)) {
                //return $pathItem;
                // Return both $pathSpec and $pathItem.
                return [
                    'pathSpec' => $pathSpec,
                    'pathItem' => $pathItem
                ];
            }
        }

        // No match found, the path does not exist.
        return [];
    }

    /**
     * Check if a path has the specified method.
     *
     * @param string $path
     *   The path to be checked.
     * @param string $method
     *   The method http to be checked.
     * @param ValidationError $error
     *
     * @return false|ValidationError
     *   False if the path has the method, otherwise a validation error object.
     *
     * @throws MissingParameterException
     *   If path or method are an empty string.
     */
    public function pathHasMethod(string $path, string $method, ValidationError $error)
    {
        if (empty($path)) {
            throw new MissingParameterException('Empty path');
        }

        if (empty($method)) {
            throw new MissingParameterException('Empty method');
        }

        // Get the PathItem corresponding to the specified path.
        $pathInfo = $this->pathExists($path);

        if (empty($pathInfo)) {
            $error->setAsRequest();
            $error->setAsSchema();
            $error->setAsPath();
            $error->setMessage(
                "No path found in the specification file matching '{$path}'",
                1
            );

            return $error;
        }

        $error->setSpecPath($pathInfo['pathItem']);

        // Check if the PathItem has the specified method.
        $check = $this->pathItemHasMethod($pathInfo['pathItem'], $method);

        if ($check === true) {
            return false;
        }

        $error->setAsRequest();
        $error->setAsSchema();
        $error->setAsPath();
        $error->setMessage(
            $pathInfo['pathSpec'] . " hasn't " . $method . " method",
            2
        );

        return $error;
    }

    /**
     * Check if a PathItem has the specified method.
     *
     * @param PathItem $pathItem
     *   The PathItem to be checked.
     * @param string $method
     *   The method http to be checked.
     *
     * @return bool
     *   True if the PathItem has the method, otherwise false.
     */
    private function pathItemHasMethod(PathItem $pathItem, string $method): bool
    {
        $_method = strtolower($method);

        // Check if the specified method exists in the PathItem's operations.
        return array_key_exists($_method, $pathItem->getOperations());
    }

    /**
     * Sets the strategy for updating an openapi specification.
     *
     * @param SpecUpdaterStrategy $specUpdaterStrategy
     *   The updater strategy to be set.
     */
    public function setSpecUpdaterStrategy(SpecUpdaterStrategy $specUpdaterStrategy): void
    {
        $this->specUpdaterStrategy = $specUpdaterStrategy;
    }

    /**
     * Updates the OpenAPI file using the specified strategy.
     *
     * @param array $params
     *   Parameters and data for the update.
     *
     * @throws MissingStrategyException
     *   If no update strategy is set.
     */
    public function update(array $params): void
    {
        if (empty($this->specUpdaterStrategy)) {
            throw new MissingStrategyException('No update strategy set to update the openapi.');
        }

        $this->specUpdaterStrategy->update($params);
    }
}
