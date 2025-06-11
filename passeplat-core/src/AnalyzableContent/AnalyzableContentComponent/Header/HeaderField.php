<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

use Dakwamine\Component\ComponentBasedObject;

/**
 * A single header field representation.
 */
class HeaderField extends ComponentBasedObject
{
    /**
     * This header field name.
     *
     * @var string
     */
    protected string $headerFieldName;

    /**
     * This header field values.
     *
     * @var string
     */
    protected string $headerFieldValue = '';

    /**
     * This header field name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->headerFieldName;
    }

    /**
     * This header field value as string.
     *
     * @return string
     */
    public function getValue(): string
    {
        return $this->headerFieldValue;
    }

    /**
     * Inits this header field instance.
     *
     * @param string $headerFieldName
     *   Header key, e.g. 'Host', 'Accept', 'Vary', etc.
     * @param string $headerFieldValue
     *   The value of the header.
     */
    public function init(string $headerFieldName, string $headerFieldValue = ''): void
    {
        $this->headerFieldName = $headerFieldName;
        $this->headerFieldValue = $headerFieldValue;
    }

    /**
     * Sets this header field value.
     *
     * @param string $headerFieldValue
     *   The value of the header.
     */
    public function setValue(string $headerFieldValue): void
    {
        $this->headerFieldValue = $headerFieldValue;
    }
}
