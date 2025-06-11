<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

use Dakwamine\Component\Exception\UnmetDependencyException;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\AnalyzableContentComponentBase;

/**
 * Contains HTTP header information.
 */
class Header extends AnalyzableContentComponentBase
{
    /**
     * Adds a header field.
     *
     * @param string $headerFieldName
     *   Header field name, e.g. 'Host', 'Accept', 'Vary', etc.
     * @param string|string[] $headerFieldValue
     *   The value(s) of the header field.
     *   Supports arrays for the case when a same header field will have multiple values.
     *
     * @throws UnmetDependencyException
     *   Could not instantiate a header field.
     */
    public function addHeaderFieldEntry(
        string $headerFieldName,
        $headerFieldValue
    ): void {
        $headerFieldType = $this->getHeaderFieldTypeByHeaderFieldName($headerFieldName);

        switch ($headerFieldType) {
            case HeaderFieldType::COMMA_SEPARATED_HEADER_FIELD:
                $class = CommaSeparatedHeaderField::class;
                break;

            case HeaderFieldType::SEMICOLON_SEPARATED_HEADER_FIELD:
                $class = SemicolonSeparatedHeaderField::class;
                break;

            default:
                $class = HeaderField::class;
                break;
        }

        if (method_exists($class, 'add')) {
            // The chosen class supports adding values.
            // Look and check if the header already exists to add value.
            /** @var MultiValuedHeaderFieldBase $component */
            foreach ($this->getComponentsByClassName($class) as $component) {
                if ($component->getName() === $headerFieldName) {
                    // Let the component handle the value addition.
                    $component->add($headerFieldValue);
                    return;
                }
            }
        }

        // The chosen class does not support adding values, or the header does not exist yet.
        $addHeaderField = function () use ($headerFieldName, $class) {
            /** @var MultiValuedHeaderFieldBase $headerField */
            $headerField = $this->addComponentByClassName($class);
            $headerField->init($headerFieldName);
            return $headerField;
        };

        if (method_exists($class, 'add')) {
            // The chosen class supports adding values.
            // It should handle array values.
            $addHeaderField()->add($headerFieldValue);
            return;
        }

        // The chosen class does not support adding values.
        if (is_array($headerFieldValue)) {
            // The value is an array, and the chosen class does not support adding values.
            // We'll create a new header field for each value.
            foreach ($headerFieldValue as $value) {
                $addHeaderField()->setValue($value);
            }

            return;
        }

        // The value is not an array, and the chosen class does not support adding values.
        // We'll create a new header field for the value.
        $addHeaderField()->setValue($headerFieldValue);
    }

    /**
     * {@inheritdoc}
     */
    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        // Work with analyzers.
        /** @var HeaderAnalyzer $headerAnalyzer */
        foreach ($this->getComponentsByClassName(HeaderAnalyzer::class) as $headerAnalyzer) {
            $data = array_merge($data, $headerAnalyzer->analyze($this));
        }

        return $data;
    }

    /**
     * Gets a specific header field value given its key.
     * @todo : la valeur peut être multiple avec charset ou boundary ou plusieurs lignes.
     *
     * @return string|null
     *   The header field value, of null if not found.
     */
    public function getHeaderFieldValue(string $key): ?string
    {
        /** @var HeaderField $header */
        foreach ($this->getComponentsByClassName(HeaderField::class) as $header) {
            if (strtolower($header->getName()) === strtolower($key)) {
                return $header->getValue();
            }
        }
        return null;
    }

    /**
     * Gets the header field type by header field name.
     * @param string $headerFieldName
     * @return string
     */
    public function getHeaderFieldTypeByHeaderFieldName(string $headerFieldName): string
    {
        // Values provided by https://stackoverflow.com/a/74957958.
        $commaSeparatedStandardHeaderFieldNames = [
            'a-im',
            'accept',
            'accept-charset',
            'accept-encoding',
            'accept-language',
            'access-control-request-headers',
            'cache-control',
            'connection',
            'content-encoding',
            'expect',
            'forwarded',
            'if-match',
            'if-none-match',
            'range',
            'te',
            'trailer',
            'transfer-encoding',
            'upgrade',
            'via',
            'warning',
        ];

        $semiColonSeparatedStandardHeaderFieldNames = [
            'cookie',
            'prefer',
        ];

        if (in_array(strtolower($headerFieldName), $commaSeparatedStandardHeaderFieldNames)) {
            return HeaderFieldType::COMMA_SEPARATED_HEADER_FIELD;
        }

        if (in_array(strtolower($headerFieldName), $semiColonSeparatedStandardHeaderFieldNames)) {
            return HeaderFieldType::SEMICOLON_SEPARATED_HEADER_FIELD;
        }

        // Generic header field.
        return HeaderFieldType::SINGLE_VALUED_HEADER_FIELD;
    }

    /**
     * Gets the headers structured for use in a real request.
     *
     * @return array
     *   Headers array to use for requests.
     */
    public function getHeadersForRequest(): array
    {
        $headers = [];

        /** @var HeaderField $header */
        foreach ($this->getComponentsByClassName(HeaderField::class) as $header) {
            $headers[$header->getName()][] = $header->getValue();
        }

        return $headers;
    }

    /**
     * Removes a single header.
     *
     * @param string $headerFieldName
     *   The key of the header to remove.
     *
     * @return bool
     *   true if the header was removed, false otherwise.
     */
    public function removeHeader(string $headerFieldName): bool
    {
        /** @var HeaderField $component */
        foreach ($this->getComponentsByClassName(HeaderField::class) as $component) {
            if ($component->getName() === $headerFieldName) {
                $this->removeComponent($component);
                return true;
            }
        }

        return false;
    }

    /**
     * Removes all headers.
     */
    public function removeHeaders(): void
    {
        foreach ($this->getComponentsByClassName(HeaderField::class) as $component) {
            $this->removeComponent($component);
        }
    }

    /**
     * Replaces a single header.
     *
     * @param string $headerFieldName
     *   Header key, e.g. 'Host', 'Accept', 'Vary', etc.
     * @param string|array $headerFieldValue
     *   The value(s) of the header. Supports arrays for the case when a same header name is used for multiple header
     *   lines.
     *
     * @return bool
     *   True if the header existed and was replaced, false otherwise.
     *
     * @throws UnmetDependencyException
     */
    public function replaceHeader(string $headerFieldName, $headerFieldValue): bool
    {
        if (!$this->removeHeader($headerFieldName)) {
            return false;
        }

        $this->addHeaderFieldEntry($headerFieldName, $headerFieldValue);
        return true;
    }
}
