<?php

namespace PassePlat\Core\Tool;

use PassePlat\Core\Exception\ObfuscatorException;
use PassePlat\Core\Exception\FatalObfuscatorException;
use PassePlat\Core\Exception\InappropriateMethodObfuscatorException;

/**
 * This class inherits from ObfuscatorBase, designed to obfuscate an input without preserving its size.
 */
class UltimateObfuscator extends ObfuscatorBase
{
    protected function obfuscateBinary($bin, array $config): string
    {
        if (!$this->isBinary($bin)) {
            throw new InappropriateMethodObfuscatorException('Invalid binary.');
        }

        return 'Binary';
    }

    protected function obfuscateDefault($input, array $config): string
    {
        if (!is_string($input)) {
            throw new FatalObfuscatorException(
                'It should never occur to obfuscate a non-string by the default method.'
            );
        }

        $char = $config['char'] ?? '*';
        return str_repeat($char, 4);
    }

    protected function obfuscateEmail($email, array $config): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InappropriateMethodObfuscatorException('Invalid mail');
        }

        try {
            $res = 'username'. '@';

            [, $domain] = explode("@", $email, 2);
            $domainParts = explode('.', $domain);
            $nbCount = count($domainParts);

            if ($nbCount === 1) {
                return $res . 'domain';
            }

            return $res . 'domain' . '.' . $domainParts[$nbCount - 1];
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during mail obfuscation.');
        }
    }

    protected function obfuscateHexadecimal($hex, array $config): string
    {
        if (!$this->isHexadecimal($hex)) {
            throw new InappropriateMethodObfuscatorException('Invalid hexadecimal');
        }

        return 'Hexadecimal';
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

            // Returning a descriptive string indicating the type of IP address and its starting part.
            if ($delimiter === '.') {
                return "IPv4 address beginning with $firstPart";
            }

            return "IPv6 address beginning with $firstPart";
        } catch (\Exception $e) {
            throw new ObfuscatorException('An unknown error occurred during IP address obfuscation.');
        }
    }

    protected function obfuscateNumeric($number, array $config): string
    {
        if (!is_numeric($number)) {
            throw new InappropriateMethodObfuscatorException('Invalid numeric.');
        }

        return 'Number';
    }

    protected function obfuscatePort($port, array $config): string
    {
        if (!is_numeric($port)) {
            throw new ObfuscatorException("Invalid port number");
        }
        return 'port';
    }
}
