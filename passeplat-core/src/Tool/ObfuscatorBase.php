<?php

namespace PassePlat\Core\Tool;

use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ObfuscatorException;
use PassePlat\Core\Exception\FatalObfuscatorException;
use PassePlat\Core\Exception\InappropriateMethodObfuscatorException;

/**
 * Abstract class for obfuscating various data types while preserving structures.
 *
 * It includes two subclasses:
 *  - LengthPreservingObfuscator, which preserves the original data size,
 *  - UltimateObfuscator, which obfuscates data using a fixed number of characters.
 *
 * Future Perspectives:
 *  Add XML handling.
 */
abstract class ObfuscatorBase
{
    use ExecutionTraceProviderTrait;

    /**
     * Checks if the input is a binary string.
     *
     * @param mixed $input
     *   The input data to be checked.
     *
     * @return bool
     *   True if the input is binary, false otherwise.
     */
    protected function isBinary($input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        return preg_match('/^-?(0[bB])?[01]+$/', $input);
    }

    /**
     * Checks if the input is a boolean value (true or false).
     *
     * @param mixed $input
     *   The input data to be checked.
     *
     * @return bool
     *   True if the input is a boolean value, false otherwise.
     */
    protected function isBoolean($input): bool
    {
        return ($input === true) || ($input === false);
    }

    /**
     * Checks if the input is a hexadecimal string.
     *
     * @param mixed $input
     *   The input data to be checked.
     *
     * @return bool
     *   True if the input is a hexadecimal string, false otherwise.
     */
    protected function isHexadecimal($input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        return preg_match('/^-?(0[xX])?[0-9A-Fa-f\s]+$/', $input);
    }

    /**
     * Checks if the given input is a valid JSON.
     *
     * @param mixed $input
     *   The input to be checked.
     *
     * @return bool
     *   True if the input is a valid JSON, false otherwise.
     */
    protected function isJson($input): bool
    {
        if (!is_string($input)) {
            return false;
        }

        // Attempt to decode the input as JSON and check for errors.
        $decoded = json_decode($input);
        return ($decoded !== null) && (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Checks the validity of the input as an x-www-form-urlencoded string.
     *
     * Ensures security by allowing only valid keys (non-empty, without special characters, etc.).
     *
     * @param mixed $input
     *   The input data to be checked.
     *
     * @return bool
     *   True if the input is a valid secure x-www-form-urlencoded string, false otherwise.
     */
    protected function isXWwwFormUrlEncoded($input): bool
    {
        // Check if the input is a string and contains '='.
        if (!is_string($input) || strpos($input, '=') === false) {
            return false;
        }

        // Parse the input string.
        parse_str($input, $parsedData);

        // Check if parsed data is not empty.
        if (empty($parsedData)) {
            return false;
        }

        // Validate keys and values.
        foreach ($parsedData as $key => $value) {
            // Check for empty key or value and validate key format.
            if (empty($key) || empty($value) || !preg_match('/^[\p{L}\p{N}_\-+~%]{1,255}$/', $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obfuscates the provided input data based on the specified content type and configuration.
     *
     * If a content type is specified in the configuration, it obfuscates the data accordingly;
     * otherwise, it employs the obfuscateData method.
     *
     * @param mixed $input
     *   The input data to be obfuscated.
     * @param array $config
     *   Optional. An array containing configuration parameters.
     *
     * @return mixed
     *   The obfuscated data.
     */
    public function obfuscate($input, array $config = array())
    {
        $config['expectedContentType'] = $config['expectedContentType'] ?? '';

        if (empty($config['expectedContentType'])) {
            return $this->obfuscateData($input, $config);
        }

        try {
            switch ($config['expectedContentType']) {
                case 'application/json':
                    return $this->obfuscateJson($input, $config);

                case 'application/x-www-form-urlencoded':
                    return $this->obfuscateQuery($input, $config);

                case 'application/xml':
                    throw new ObfuscatorException("XML format is not supported by the obfuscator yet.");

                default:
                    return $this->obfuscateData($input, $config);
            }
        } catch (FatalObfuscatorException $e) {
            // If there is an issue with obfuscateDefault, it should never be called again,
            // and we must immediately stop the obfuscation.
            $this->addExecutionTraceDetails($e->getMessage());
            return '';
        } catch (ObfuscatorException $e) {
            // At this stage, we will catch all other exceptions, including InappropriateMethodObfuscatorException,
            // because it indicates that the content type does not match the expected content type.
            $this->addExecutionTraceDetails($e->getMessage());
        }

        // In all exceptions other than FatalObfuscatorException,
        // we must obfuscate the input using obfuscateDefault method.
        try {
            return $this->obfuscateDefault($input, $config);
        } catch (FatalObfuscatorException $e) {
            $this->addExecutionTraceDetails($e->getMessage());
            return '';
        }
    }

    /**
     * Obfuscates an input array while preserving the keys.
     *
     * It obfuscates the values unless a key is specified in the exclusions of the config.
     *
     * @param mixed $array
     *   The input array to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters, including exclusions.
     *
     * @return array
     *   The obfuscated array with preserved keys and obfuscated values based on the configuration.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid array.
     * @throws ObfuscatorException
     *   Thrown if there are other exceptions.
     */
    protected function obfuscateArray($array, array $config): array
    {
        // Validate input as an array.
        if (!is_array($array)) {
            throw new InappropriateMethodObfuscatorException("Invalid array.");
        }

        try {
            $config['exclusions'] = $config['exclusions'] ?? [];
            $result = array();

            // Check if input array is associative or indexed.
            if (array_keys($array) !== range(0, count($array) - 1)) {
                // Associative array.
                foreach ($array as $key => $value) {
                    // Preserve the value if it's in the exclusions, otherwise obfuscate it.
                    if (!in_array($key, $config['exclusions'])) {
                        $result[$key] = $this->obfuscateData($value, $config);
                    } else {
                        $result[$key] = $value;
                    }
                }
            } else {
                // Indexed array.
                for ($i = 0; $i < count($array); $i++) {
                    $result[$i] = $this->obfuscateData($array[$i], $config);
                }
            }

            // Return the obfuscated array with preserved keys.
            return $result;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during array obfuscation.');
        }
    }

    /**
     * Obfuscates the input if it is a binary string.
     *
     * @param mixed $bin
     *   The input to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated binary string.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid binary string.
     * @throws FatalObfuscatorException
     *    Thrown if a non-string passed to obfuscateDefault.
     * @throws ObfuscatorException
     *     Thrown if there are other exceptions.
     */
    abstract protected function obfuscateBinary($bin, array $config) : string;

    /**
     * Obfuscates 'true' and 'false' by returning 'false'.
     *
     * @param mixed $input
     *   The input data to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return bool
     *   The obfuscated boolean value (false).
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid boolean.
     */
    protected function obfuscateBool($input, array $config): bool
    {
        if (!$this->isBoolean($input)) {
            throw new InappropriateMethodObfuscatorException("Invalid boolean");
        }

        return false;
    }

    /**
     * Obfuscates the input data using a variety of obfuscation methods based on the data type.
     *
     * @param mixed $input
     *   The input data to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return mixed
     *   The obfuscated data.
     */
    protected function obfuscateData($input, array $config)
    {
        // List of methods to be tried one after another until the appropriate method is found;
        // otherwise, the obfuscateDefault (the last one) method will be used.
        // It is necessary to maintain this order (not alphabetical order).
        // The most specific obfuscators must be placed before the more general ones.
        $methods = array(
            "obfuscateEmpty",
            "obfuscateSurrounded",
            "obfuscateBool",
            "obfuscateEmail",
            "obfuscateBinary",
            "obfuscateNumeric",
            "obfuscateHexadecimal",
            "obfuscateIp",
            "obfuscateUrl",
            "obfuscateArray",
            "obfuscateJson",
            "obfuscateQuery",
            "obfuscateObject",
            "obfuscateDefault"
            );

        foreach ($methods as $method) {
            try {
                return $this->$method($input, $config);
            } catch (InappropriateMethodObfuscatorException $e) {
                // Pass to the next obfuscator.
            } catch (FatalObfuscatorException $e) {
                // Immediately stop the overall obfuscation task.
                $this->addExecutionTraceDetails($e->getMessage());
                return '';
            } catch (ObfuscatorException $e) {
                // The obfuscator was appropriate for the value type, but it could not handle it.
                // Trace the error.
                $this->addExecutionTraceDetails($e->getMessage());
                break;
            }
        }

        try {
            // Usually, an ObfuscatorException has just been thrown.
            return $this->obfuscateDefault($input, $config);
        } catch (FatalObfuscatorException $e) {
            $this->addExecutionTraceDetails($e->getMessage());
            return '';
        }
    }

    /**
     * Obfuscates input independently of its type by returning an obfuscated string.
     *
     * The returned string is composed of repetitions of a character specified in the configuration.
     *
     * @param mixed $input
     *   The input data to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated data.
     *
     * @throws FatalObfuscatorException
     *    Thrown if a non-string passed to obfuscateDefault.
     */
    abstract protected function obfuscateDefault($input, array $config): string;

    /**
     * Obfuscates the input if it is a valid email address.
     *
     * @param mixed $email
     *   The input data to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated email.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid email address.
     * @throws FatalObfuscatorException
     *   Thrown if a non-string passed to obfuscateDefault.
     * @throws ObfuscatorException
     *   Thrown if there are other exceptions.
     */
    abstract protected function obfuscateEmail($email, array $config): string;

    /**
     * Obfuscates empty input by returning an empty string.
     *
     * @param mixed $input
     *   The input data to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     * @return string
     *   Returns an empty string.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not empty.
     */
    protected function obfuscateEmpty($input, array $config): string
    {
        if ($input !== '' && $input !== null) {
            throw new InappropriateMethodObfuscatorException("Invalid empty input.");
        }

        return '';
    }

    /**
     * Obfuscates the input if it is a hexadecimal string.
     *
     * @param mixed $hex
     *   The input to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated hexadecimal string.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid hexadecimal string.
     * @throws FatalObfuscatorException
     *   Thrown if a non-string passed to obfuscateDefault.
     * @throws ObfuscatorException
     *   Thrown if there are other exceptions.
     */
    abstract protected function obfuscateHexadecimal($hex, array $config): string;

    /**
     * Obfuscates the input if it is a valid IP string.
     *
     * @param mixed $ip
     *   The input to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated IP address string but not the first part of the IP address.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid IP string.
     * @throws FatalObfuscatorException
     *   Thrown if a non-string passed to obfuscateDefault.
     * @throws ObfuscatorException
     *   Thrown if there are other exceptions.
     */
    abstract protected function obfuscateIp($ip, array $config): string;

    /**
     * Obfuscates the given JSON input, preserving the structure but obfuscating the data values.
     *
     * @param mixed $jsonInput
     *   The JSON input to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated JSON.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid Json.
     * @throws FatalObfuscatorException
     *   Thrown if a non-string passed to obfuscateDefault.
     * @throws ObfuscatorException
     *   Thrown if there are other exceptions.
     */
    protected function obfuscateJson($jsonInput, array $config): string
    {
        // Validate if the input is a valid JSON.
        if (!$this->isJson($jsonInput)) {
            throw new InappropriateMethodObfuscatorException('Invalid array.');
        }

        try {
            // Decode the JSON input.
            $decoded = json_decode($jsonInput, false);

            if (is_object($decoded)) {
                $obfuscatedObject = $this->obfuscateObject($decoded, $config);

                // Preserve the JSON format.
                return json_encode($obfuscatedObject, JSON_UNESCAPED_UNICODE| JSON_PRETTY_PRINT);
            } elseif (is_array($decoded)) {
                $obfuscatedArray = $this->obfuscateArray($decoded, $config);
                return json_encode($obfuscatedArray, JSON_UNESCAPED_UNICODE| JSON_PRETTY_PRINT);
            }

            // If it's neither an object nor an array.
            return $this->obfuscateDefault($jsonInput, $config);
        } catch (FatalObfuscatorException $e) {
            // Do nothing here to treat this specific exception.
            // It will not be handled and will propagate up to a higher level.
            throw $e;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during JSON obfuscation.');
        }
    }

    /**
     * Obfuscates the input if it is a numeric.
     *
     * @param mixed $number
     *   The input data to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated numeric data.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid numeric.
     */
    abstract protected function obfuscateNumeric($number, array $config) : string;

    /**
     * Obfuscate properties of an object based on the provided configuration.
     *
     * @param mixed $object
     *   The object to obfuscate.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return object
     *   The obfuscated object.
     *
     * @throws InappropriateMethodObfuscatorException
     *    Thrown if the input is not a valid object.
     * @throws ObfuscatorException
     *    Thrown if there are other exceptions.
     */
    protected function obfuscateObject($object, array $config): object
    {
        if (!is_object($object)) {
            throw new InappropriateMethodObfuscatorException('Invalid object.');
        }

        try {
            $config['exclusions'] = $config['exclusions'] ?? [];

            foreach ($object as $propertyName => $propertyValue) {
                if (!in_array($propertyName, $config['exclusions'])) {
                    $object->$propertyName = $this->obfuscateData($propertyValue, $config);
                }
            }

            return $object;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during object obfuscation.');
        }
    }

    /**
     * Abstract method that obfuscates the given port number.
     *
     * @param mixed $port
     *   The input port number to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   Obfuscated port number.
     *
     * @throws ObfuscatorException
     *   Thrown if the $port is not numeric.
     */
    abstract protected function obfuscatePort($port, array $config): string;

    /**
     * Obfuscates the input query parameters (isXWwwFormUrlEncoded).
     *
     * It preserves the keys but obfuscates the values if the key is not in the exclusions of the config.
     *
     * @param mixed $query
     *   The input query parameters to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated query parameters.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid x-www-form-urlencoded.
     * @throws ObfuscatorException
     *   Thrown if there are other exceptions.
     */
    protected function obfuscateQuery($query, array $config): string
    {
        // Validate the query parameters.
        if (!$this->isXWwwFormUrlEncoded($query)) {
            throw new InappropriateMethodObfuscatorException("Invalid query.");
        }

        try {
            // Parse the query parameters into an associative array.
            parse_str($query, $params);

            // Obfuscate the array of parameters.
            $obfuscatedParams = $this->obfuscateArray($params, $config);

            // Rebuild the obfuscated query parameters string and decode any special characters.
            return urldecode(http_build_query($obfuscatedParams));
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during Query obfuscation.');
        }
    }

    /**
     * Obfuscates the middle part of a string surrounded by whitespace, preserving leading and trailing whitespaces.
     *
     * @param mixed $input
     *  The input string to be obfuscated.
     * @param array $config
     *  An array containing configuration parameters.
     * @return string
     *  The obfuscated string with clear leading and trailing whitespaces.
     *
     * @throws InappropriateMethodObfuscatorException
     *  Thrown if the input is not a string surrounded by whitespaces.
     */
    protected function obfuscateSurrounded($input, array $config): string
    {
        // Pre-validation.
        if (!is_string($input)) {
            throw new InappropriateMethodObfuscatorException("Invalid surrounded string.");
        }

        $matches = [];

        // Check if the string is surrounded.
        if (!preg_match('/^(\s*)(.*?)(\s*)$/s', $input, $matches)) {
            // An error occurred during verification.
            throw new InappropriateMethodObfuscatorException("Invalid surrounded string.");
        }

        // Check if there are spaces before or after the middle part.
        if (empty($matches[1]) && (empty($matches[3]))) {
            // There were no spaces before or after the middle part.
            throw new InappropriateMethodObfuscatorException("Invalid surrounded string.");
        }

        // Obfuscate the middle part.
        return $matches[1] . $this->obfuscate($matches[2], $config) . $matches[3];
    }

    /**
     * Obfuscates the given URL, preserving certain parts.
     *
     * The scheme (https, http, ftp, etc.), the domain's extension, and the keys of query parameters are preserved.
     *
     * @param mixed $url
     *   The input to be obfuscated.
     * @param array $config
     *   An array containing configuration parameters.
     *
     * @return string
     *   The obfuscated URL string.
     *
     * @throws InappropriateMethodObfuscatorException
     *   Thrown if the input is not a valid URL.
     * @throws FatalObfuscatorException
     *    Thrown if a non-string passed to obfuscateDefault.
     * @throws ObfuscatorException
     *     Thrown if there are other exceptions.
     */
    protected function obfuscateUrl($url, array $config): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InappropriateMethodObfuscatorException('Invalid URL');
        }

        try {
            // Initialize the result string.
            $res = '';

            // Parse the URL components.
            $parsed_url = parse_url($url);

            if (!empty($parsed_url['scheme'])) {
                // Preserve the scheme (https, http, ftp, etc.).

                $res .= $parsed_url['scheme'] . '://';
            }

            /**
             * Obfuscate the host part of the URL.
             *
             * @param string $host
             *   The host part of the URL to be obfuscated.
             * @param array $config
             *   An array containing configuration parameters.
             *
             * @return string
             *   The obfuscated host string.
             *
             * @throws FatalObfuscatorException
             *   Thrown if a non-string passed to obfuscateDefault.
             */
            $obfuscateHost = function (string $host, array $config) {
                $res = '';

                $parts = explode('.', $host);

                if (empty($parts)) {
                    return '';
                }

                // Obfuscate parts except the extension and 'www'.
                for ($i = 0; $i < (count($parts) - 1); $i++) {
                    if (!strcasecmp('www', $parts[$i])) {
                        $res .= $parts[$i] . '.';
                        continue;
                    }

                    $res .= $this->obfuscateDefault($parts[$i], $config) . '.';
                }

                // Preserve the last part. It's usually the TLD, or the entire hostname on a private network.
                $res .= $parts[$i];

                return $res;
            };

            $res .= $obfuscateHost($parsed_url['host'], $config);

            // Obfuscate the port if specified.
            if (!empty($parsed_url['port'])) {
                $res .= ':' . $this->obfuscatePort($parsed_url['port'], $config);
            }

            // Obfuscate the path using a lambda function.
            $obfuscatePath = function ($path, $config) {
                $segments = explode('/', $path);

                foreach ($segments as &$segment) {
                    if (!empty($segment)) {
                        $segment = $this->obfuscateDefault($segment, $config);
                    }
                }

                return implode('/', $segments);
            };

            if (!empty($parsed_url['path'])) {
                $res .= $obfuscatePath($parsed_url['path'], $config);
            }

            // Obfuscate the query parameters.
            if (!empty($parsed_url['query'])) {
                $res .= '?' . $this->obfuscateQuery($parsed_url['query'], $config);
            }

            // Preserve the fragment if it exists.
            if (!empty($parsed_url['fragment'])) {
                $res .= '#' . $this->obfuscateDefault($parsed_url['fragment'], $config);
            }

            return $res;
        } catch (FatalObfuscatorException $e) {
            // Do nothing here to treat this specific exception.
            // It will not be handled and will propagate up to a higher level.
            throw $e;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during URL obfuscation.');
        }
    }
}
