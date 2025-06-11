<?php

namespace PassePlat\Core\Config;

use Dakwamine\Component\ComponentBasedObject;
use PHLAK\Config\Config;
use PHLAK\Config\Exceptions\ConfigException;
use PHLAK\Config\Exceptions\InvalidContextException;
use PHLAK\Config\Exceptions\InvalidFileException;

/**
 * Reusable config loader.
 */
class ConfigLoader extends ComponentBasedObject
{
    /**
     * Loads from a directory.
     *
     * @param string $directory
     *   Path to the directory containing the configuration files.
     * @param string $fileNamePattern
     *   Regex pattern of the file to load. Leave empty to retrieve all files of the directory.
     * @param string|string[] $extension
     *   Expected file extension(s). Defaults to ['json', 'yaml'].
     *   Set an array to support multiple extensions.
     *
     * @return array
     *   The loaded configuration, keyed by file name.
     *
     * @throws \PassePlat\Core\Exception\ConfigException
     *   Throws when parameters are empty or configuration files are invalid.
     */
    public function loadConfigFromDirectory(string $directory, string $fileNamePattern = '', $extension = ['json', 'yaml']): array
    {
        if (empty($directory)) {
            throw new \PassePlat\Core\Exception\ConfigException(
                'Asked to load config but no path was given.'
            );
        }

        if (empty($extension)) {
            throw new \PassePlat\Core\Exception\ConfigException(
                'Asked to load config but no extension was given.'
            );
        }

        $loaded = [];

        // Either use the provided file name pattern, or retrieve all files.
        $finalFileNamePattern = strlen($fileNamePattern) > 0 ? $fileNamePattern : '.+';

        // Build the extension pattern.
        $extensionPattern = is_array($extension) ? implode('|', $extension) : $extension;

        // Load files from the directory.
        // Based on https://stackoverflow.com/a/29109311.
        $rdi = new \RecursiveDirectoryIterator($directory);
        $rii = new \RecursiveIteratorIterator($rdi);

        // The initial slash in the pattern is vital; it prevents matching the pattern with files
        // that end with the same domain but have different prefixes.
        $files = new \RegexIterator($rii, "/\/$finalFileNamePattern\.($extensionPattern)$/", \RegexIterator::GET_MATCH);

        foreach ($files as $file => $pattern) {
            try {
                // Load configuration file.
                // Files nested in subdirectories will erase upperlevel configs if they share the same name.
                $loaded[basename($file, '.' . $pattern[1])] = (new Config($file))->toArray();
            } catch (InvalidContextException $exception) {
                throw new \PassePlat\Core\Exception\ConfigException('Impossible to load the file.');
            } catch (InvalidFileException $exception) {
                throw new \PassePlat\Core\Exception\ConfigException('Error parsing the configuration file.');
            } catch (ConfigException $exception) {
                throw new \PassePlat\Core\Exception\ConfigException('Unknown config error');
            }
        }

        return $loaded;
    }
}
