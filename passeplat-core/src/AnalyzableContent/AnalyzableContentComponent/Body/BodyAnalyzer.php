<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body;

use Dakwamine\Component\ComponentBasedObject;

/**
 * Base class for body analyzers.
 */
abstract class BodyAnalyzer extends ComponentBasedObject
{
    /**
     * Analyzes the body.
     *
     * @param Body $body
     *   The body object.
     *
     * @return array
     */
    abstract public function analyze(Body $body): array;
}
