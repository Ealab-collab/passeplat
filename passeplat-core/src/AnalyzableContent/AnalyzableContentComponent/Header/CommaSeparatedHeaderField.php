<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

/**
 * Comma-separated header field representation.
 */
class CommaSeparatedHeaderField extends MultiValuedHeaderFieldBase
{
    public function getSeparator(): string
    {
        return ',';
    }
}
