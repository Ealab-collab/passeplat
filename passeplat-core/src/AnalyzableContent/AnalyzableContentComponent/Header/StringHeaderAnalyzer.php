<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

/**
 * Base class for header analyzers outputting strings.
 */
class StringHeaderAnalyzer extends HeaderAnalyzer
{
    /**
     * {@inheritdoc}
     */
    public function analyze(Header $header): array
    {
        $headerLines = $this->getHeaderLines($header);

        return [
            $this->getHeaderType() => $headerLines,
        ];
    }

    /**
     * Builds the header line in this format: "headerKey: headerValue".
     *
     * @param HeaderField $headerField
     *   The header to build its line.
     *
     * @return string
     *   The string.
     */
    public function buildHeaderLine(HeaderField $headerField): string
    {
        if (empty($headerField->getName())) {
            return '';
        }

        return $headerField->getName() . ': ' . $headerField->getValue();
    }

    /**
     * Gets the formatted header lines.
     *
     * One header per line.
     *
     * @param Header $header
     *   The header list object.
     *
     * @return string
     *   Headers string.
     */
    protected function getHeaderLines(Header $header): string
    {
        $header_strings = [];

        // Recompose the headers into a string, one header per line.
        /** @var HeaderField $headerField */
        foreach ($header->getComponentsByClassName(HeaderField::class) as $headerField) {
            $header_strings[] = $this->buildHeaderLine($headerField);
        }

        return implode("\n", $header_strings);
    }
}
