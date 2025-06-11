<?php

namespace PassePlat\Logger\Elastic\Exception;

/**
 * Error codes collection for passeplat.
 */
class ErrorCode
{
    const ES_UNKNOWN = 'ES_0';

    const ES_COULD_NOT_LOAD_INDEX = 'ES_100';
    const ES_COULD_NOT_CREATE_INDEX = 'ES_101';
}
