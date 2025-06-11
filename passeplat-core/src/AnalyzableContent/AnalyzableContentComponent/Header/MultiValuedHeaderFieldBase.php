<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Header;

/**
 * Multi-valued header field representation.
 */
abstract class MultiValuedHeaderFieldBase extends HeaderField
{
    /**
     * Adds one or more new values on this header field.
     *
     * @param string|string[] $headerFieldValue
     *   The value(s) of the header to add.
     */
    public function add($headerFieldValue): void
    {
        if (empty($headerFieldValue)) {
            // Do not add an empty value.
            return;
        } 
        if (empty($this->headerFieldValue)) {
            if (is_array($headerFieldValue)) {
                // The current value is empty. Set it.
                $this->headerFieldValue = implode($this->getSeparator(), $headerFieldValue);
            }
            return;
        }

        // $headerValues is already set.
        $currentValuesExploded = explode($this->getSeparator(), $this->headerFieldValue);

        if (!is_array($headerFieldValue)) {
            $headerFieldValue = [$headerFieldValue];
        }

        foreach ($headerFieldValue as $value) {
            if (!is_string($value)) {
                // Ignore non string input.
                continue;
            }

            // Don't check for duplicates here because it's allowed to have
            // duplicates in the header field values.
            $currentValuesExploded[] = $value;
        }

        $this->headerFieldValue = implode($this->getSeparator(), $currentValuesExploded);
    }

    /**
     * The separator used to separate values in this header field.
     *
     * @return string
     *   The separator.
     */
    abstract public function getSeparator(): string;
}
