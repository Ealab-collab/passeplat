<?php

namespace PassePlat\Core\Tool;

use PassePlat\Core\Exception\ObfuscatorException;
use PassePlat\Core\Exception\FatalObfuscatorException;
use PassePlat\Core\Exception\InappropriateMethodObfuscatorException;

/**
 * This class inherits from ObfuscatorBase, designed to obfuscate an input while preserving its size.
 */
class LengthPreservingObfuscator extends ObfuscatorBase
{
    protected function obfuscateBinary($bin, array $config): string
    {
        if (!$this->isBinary($bin)) {
            throw new InappropriateMethodObfuscatorException('Invalid binary.');
        }

        try {
            // Split binary string into parts based on 'b' or 'B' delimiter.
            $parts = preg_split('/[bB]/', $bin, 2);

            // Set default character for obfuscation.
            $config['char'] = '0';

            if (count($parts) === 1) {
                return $this->obfuscateDefault($parts[0], $config);
            }

            return $parts[0] . 'b' . $this->obfuscateDefault($parts[1], $config);
        } catch (FatalObfuscatorException $e) {
            // Do nothing here to treat this specific exception.
            // It will not be handled and will propagate up to a higher level.
            throw $e;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during binary obfuscation.');
        }
    }

    protected function obfuscateDefault($input, array $config): string
    {
        if (!is_string($input)) {
            throw new FatalObfuscatorException(
                'It should never occur to obfuscate a non-string by the default method.'
            );
        }

        $char = $config['char'] ?? '*';
        return str_repeat($char, strlen($input));
    }

    protected function obfuscateEmail($email, array $config): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InappropriateMethodObfuscatorException('Invalid email');
        }

        try {
            $res = '';

            [$user, $domain] = explode("@", $email, 2);

            $config['char'] = 'u';
            $res .= $this->obfuscateDefault($user, $config) . '@';

            $domainParts = explode('.', $domain);
            $nbCount = count($domainParts);
            $config['char'] = 'd';

            if ($nbCount <= 1) {
                // Normally, we never have this case since it is not valid by FILTER_VALIDATE_EMAIL
                $res .= $this->obfuscateDefault($domain, $config);
                return $res;
            }

            // Obfuscate all domain parts except the last one
            for ($i = 0; $i < ($nbCount-1); $i++) {
                $res .= $this->obfuscateDefault($domainParts[$i], $config) . '.';
            }

            // Append the last non obfuscated part of the domain
            $res .= $domainParts[$nbCount-1];
            return $res;
        } catch (FatalObfuscatorException $e) {
            // Do nothing here to treat this specific exception.
            // It will not be handled and will propagate up to a higher level.
            throw $e;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during email obfuscation.');
        }
    }

    protected function obfuscateHexadecimal($hex, array $config): string
    {
        if (!$this->isHexadecimal($hex)) {
            throw new InappropriateMethodObfuscatorException('Invalid hexadecimal');
        }

        try {
            $parts = preg_split('/[xX]/', $hex, 2);

            // Set default character for obfuscation.
            $config['char'] = 'F';

            if (count($parts) === 1) {
                return $this->obfuscateDefault($parts[0], $config);
            }

            return $parts[0] . 'x' . $this->obfuscateDefault($parts[1], $config);
        } catch (FatalObfuscatorException $e) {
            // Do nothing here to treat this specific exception.
            // It will not be handled and will propagate up to a higher level.
            throw $e;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during hexadecimal obfuscation.');
        }
    }

    protected function obfuscateIp($ip, array $config): string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InappropriateMethodObfuscatorException('Invalid IP');
        }

        try {
            // Determine the delimiter used in the IP address (either '.' (for IPv4) or ':' (for IPv6)).
            $delimiter = strpos($ip, '.') ? '.' : ':';

            // Split the IP address into parts.
            $parts = explode($delimiter, $ip);

            // Preserve the first part of the IP address.
            $firstPart = $parts[0];

            // Set default character for obfuscation.
            $config['char'] = 'X';

            // Obfuscate the rest of the IP address parts.
            $obfuscated = '';

            for ($i = 1; $i < count($parts); $i++) {
                $obfuscated .= $delimiter;
                $obfuscated .= $this->obfuscateDefault($parts[$i], $config);
            }

            // Return the obfuscated IP address string with the preserved first part.
            return $firstPart . $obfuscated;
        } catch (FatalObfuscatorException $e) {
            // Do nothing here to treat this specific exception.
            // It will not be handled and will propagate up to a higher level.
            throw $e;
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during IP address obfuscation.');
        }
    }

    protected function obfuscateNumeric($number, array $config): string
    {
        // Validate if the input is a numeric value.
        if (!is_numeric($number)) {
            throw new InappropriateMethodObfuscatorException('Invalid numeric.');
        }

        // Obfuscate the numeric data by replacing digits with '#'.
        // We do not call ObfuscateDefault to preserve special characters like 'e', '-', '.'.
        return preg_replace('/\d/', '#', $number);
    }

    protected function obfuscatePort($port, array $config): string
    {
        try {
            return $this->obfuscateNumeric($port, $config);
        } catch (\Exception $e) {
            throw new ObfuscatorException('Invalid port number');
        }
    }
}
