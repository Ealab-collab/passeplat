<?php

namespace PassePlat\Core\Security;

use Psr\Http\Message\UriInterface;

/**
 * Interface for host checkers.
 */
interface HostCheckerInterface
{
    /**
     * Something like "https://wsolution.com/"
     */
    const HOST_TYPE__BASE = 'HOST_TYPE__BASE';

    /**
     * Could not determine the host type.
     */
    const HOST_TYPE__UNKNOWN = 'HOST_TYPE__UNKNOWN';

    /**
     * Something like "https://userid.wsolution.com/".
     */
    const HOST_TYPE__WITH_USER_ID = 'HOST_TYPE__WITH_USER_ID';

    /**
     * Something like "https://scheme---destination--web--service-com---userid.wsolution.com/".
     */
    const HOST_TYPE__WITH_DESTINATION = 'HOST_TYPE__WITH_DESTINATION';

    /**
     * Gets the host type from URL.
     *
     * @param UriInterface $uri
     *   The URL to inspect.
     *
     * @return string
     *   One of the HOST_TYPE_* consts.
     */
    public function getHostTypeFromUrl(UriInterface $uri): string;
}
