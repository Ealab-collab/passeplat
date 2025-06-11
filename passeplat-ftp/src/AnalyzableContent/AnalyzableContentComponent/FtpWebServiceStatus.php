<?php

namespace PassePlat\Ftp\AnalyzableContent\AnalyzableContentComponent;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\WebServiceStatusBase;

/**
 * Status about a FTP web service call.
 */
class FtpWebServiceStatus extends WebServiceStatusBase
{
    const OK = 'OK';

    protected function getValidStatuses(): array
    {
        $statuses = parent::getValidStatuses();
        $statuses[] = static::OK;
        return $statuses;
    }
}
