<?php

namespace PassePlat\Logger\Elastic\Config\ConfigItem;

use PassePlat\Core\Config\ConfigItem\ConfigItem;

/**
 * Elasticsearch configs.
 */
class Elasticsearch extends ConfigItem
{
    public function getConfigId(): string
    {
        return 'elasticsearch.passeplat-logger-elastic';
    }
}
