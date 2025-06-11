<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Task\Condition;

use PassePlat\Core\AnalyzableContent\AnalyzableContent;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body\Body;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\RequestInfo;
use PassePlat\Core\AnalyzableContent\ExecutionTrace\ExecutionTraceProviderTrait;
use PassePlat\Core\Exception\ConditionException;
use PassePlat\Core\Tool\PropertiesComparer;
use PassePlat\Core\WebService\WebServiceInterface;

/**
 * Condition which checks the x-www-form-urlencoded body of the request.
 */
class RequestXWwwFormUrlencodedBodyConditionBase_0 extends RequestXWwwFormUrlencodedBodyCondition_0
{
    public static function hasEnableForm(): bool
    {
        return false;
    }
}
