<?php

namespace PassePlat\Core\Exception;

/**
 * Error codes collection for passeplat.
 */
class ErrorCode
{
    const PP = 'PP_0';

    const PP_BAD_REQUEST = 'PP_50';

    const PP_HTTP = 'PP_100';
    const PP_HTTP_HEADER = 'PP_101';

    const PP_USER = 'PP_150';
    const PP_USER_NOT_FOUND = 'PP_151';
    const PP_INVALID_USER_REPOSITORY = 'PP_152';
    const PP_USER_AUTHENTICATION = 'PP_153';

    const PP_WEB_SERVICE = 'PP_200';
    const PP_WEB_SERVICE_INVALID_URI = 'PP_201';

    const PP_CONFIG = 'PP_250';

    const PP_CONDITION = 'PP_300';

    const PP_OBFUSCATOR = 'PP_700';
    const PP_OBFUSCATOR_INAPPROPRIATE = 'PP_701';
    const PP_OBFUSCATOR_UNEXPECTED_ERROR = 'PP_702';
    const PP_OBFUSCATOR_FATAL_ERROR = 'PP_703';
}
