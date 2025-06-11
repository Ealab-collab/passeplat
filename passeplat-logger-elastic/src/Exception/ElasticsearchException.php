<?php

namespace PassePlat\Logger\Elastic\Exception;

use PassePlat\Core\Exception\Exception;
use Throwable;

/**
 * Elasticsearch related exception.
 */
class ElasticsearchException extends Exception
{
    /**
     * {@inheritdoc}
     * @param string $message
     * @param Throwable|null $previous
     * @param string $ppCode
     * @param int $code
     */
    public function __construct(
        $message = "",
        Throwable $previous = null,
        $ppCode = ErrorCode::ES_UNKNOWN,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
