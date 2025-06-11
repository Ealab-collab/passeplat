<?php

namespace PassePlat\Core\Tool;

/**
 * String values manipulation.
 */
class StringTools
{
    /**
     * Custom implementation of preg_quote which provides the option to exclude certain characters from being escaped.
     *
     * @param string $input
     *   The string to escape.
     *
     * @param array $charactersToExclude
     *   Contains the characters to exclude from escaping.
     *
     * @return string
     *   Escaped string.
     */
    public static function preg_quote(string $input, array $charactersToExclude = []): string
    {
        // List of special characters escaped by preg_quote.
        $escapedCharacters = ['.', '\\', '+', '*', '?', '[', '^', ']', '$',
            '(', ')', '{', '}', '=', '!', '<', '>', '|', ':', '-', '#'];

        $escaped = '';
        $length = strlen($input);

        for ($i = 0; $i < $length; $i++) {
            $char = $input[$i];

            if (in_array($char, $escapedCharacters) &&
                !in_array($char, $charactersToExclude)) {
                $escaped .= '\\' . $char;
                continue;
            }

            $escaped .= $char;
        }

        return $escaped;
    }
}
