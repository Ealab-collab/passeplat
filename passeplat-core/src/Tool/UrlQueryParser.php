<?php

namespace PassePlat\Core\Tool;

use Psr\Http\Message\UriInterface;

/**
 * Helper class for URL query parse.
 */
class UrlQueryParser
{
    /**
     * Parses the query part of a URI.
     *
     * @param UriInterface $uri
     *   The URI to analyze.
     *
     * @return string[]
     *   Query parameters.
     */
    public static function parseFromUrl(UriInterface $uri): array
    {
        $queryParameters = [];

        // WARNING: This will parse using the PHP query string parser. Remember there is no standard about query string
        // parse.
        // See: https://en.wikipedia.org/wiki/Query_string.
        parse_str($uri->getQuery(), $queryParameters);

        return $queryParameters;
    }
}
