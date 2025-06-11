<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

use Dakwamine\Component\ComponentBasedObject;

/**
 * Base class for header analyzers.
 */
abstract class HeaderAnalyzer extends ComponentBasedObject
{
    /**
     * Type of headers.
     *
     * This is used as the key when this analyzer returns
     * its results. Preferably use one of HeaderType consts.
     *
     * Defaults to 'headers'.
     *
     * @var string
     */
    private string $headerType;

    /**
     * Builds the header line in this format: "headerKey: headerValue".
     *
     * @param Header $header
     *   The header object.
     *
     * @return array
     *   The header line.
     */
    abstract public function analyze(Header $header): array;

    /**
     * The headers type.
     *
     * @return string
     *   The headers type. Defaults to 'headers' if empty.
     */
    protected function getHeaderType(): string
    {
        return empty($this->headerType) ? 'headers' : $this->headerType;
    }

    /**
     * Sets the header type.
     *
     * @param string $headerType
     *   Header type as string. Preferably use one of HeaderType consts.
     */
    public function setHeaderType(string $headerType): void
    {
        // This value can be empty.
        $this->headerType = $headerType;
    }
}
