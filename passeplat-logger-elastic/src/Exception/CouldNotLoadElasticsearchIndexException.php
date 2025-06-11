<?php

namespace PassePlat\Logger\Elastic\Exception;

use Throwable;

/**
 * Elasticsearch index init exception.
 */
class CouldNotLoadElasticsearchIndexException extends ElasticsearchException
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
        $ppCode = ErrorCode::ES_COULD_NOT_LOAD_INDEX,
        $code = 0
    ) {
        parent::__construct($message, $previous, $ppCode, $code);
    }
}
