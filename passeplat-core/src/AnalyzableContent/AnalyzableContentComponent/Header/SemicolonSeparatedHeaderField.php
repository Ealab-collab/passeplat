<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

/**
 * Semicolon-separated header field representation.
 */
class SemicolonSeparatedHeaderField extends MultiValuedHeaderFieldBase
{
    public function getSeparator(): string
    {
        return ';';
    }
}
