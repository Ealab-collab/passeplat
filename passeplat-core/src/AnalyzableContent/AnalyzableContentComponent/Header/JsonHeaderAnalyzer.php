<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

/**
 * Base class for header analyzers outputting JSON.
 */
class JsonHeaderAnalyzer extends HeaderAnalyzer
{
    /**
     * {@inheritdoc}
     */
    public function analyze(Header $header): array
    {
        $headersAsJsonString = $this->getJson($header);

        return [
            $this->getHeaderType() => $headersAsJsonString,
        ];
    }

    /**
     * Gets the formatted header lines as JSON.
     *
     * One header per line.
     *
     * @param Header $headerList
     *   The header list object.
     *
     * @return string
     *   Headers string.
     */
    protected function getJson(Header $headerList): string
    {
        $header_strings = [];

        // Recompose the headers into a string, one header per line.
        /** @var HeaderField $headerField */
        foreach ($headerList->getComponentsByClassName(HeaderField::class) as $headerField) {
            $header_strings[] = [
                'key' => $headerField->getName(),
                'value' => $headerField->getValue(),
            ];
        }

        return json_encode($header_strings);
    }
}
