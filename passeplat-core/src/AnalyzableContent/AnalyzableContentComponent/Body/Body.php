<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Body;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\AnalyzableContentComponentBase;
use Psr\Http\Message\StreamInterface;

/**
 * Body content to analyse.
 */
class Body extends AnalyzableContentComponentBase
{
    /**
     * Body string.
     *
     * @var string
     */
    private $body = '';

    /**
     * Body stream.
     *
     * @var StreamInterface
     */
    private $bodyStream;

    /**
     * Body length.
     *
     * @var int
     */
    private $realBodyLength = 0;

    /**
     * Tells if the limit has been reached.
     *
     * @var bool
     */
    private $hasExceededLimit = false;

    /**
     * Length in bytes, not in character count.
     */
    const DEFAULT_MAX_BODY_LENGTH = 8388608;

    /**
     * Gets the body string.
     *
     * @return string
     *   Body string.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * {@inheritdoc}
     */
    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        foreach ($this->getComponentsByClassName(BodyAnalyzer::class) as $analyzer) {
            $data = array_merge($data, $analyzer->analyze($this));
        }

        return $data;
    }

    /**
     * Gets the actual max body length.
     *
     * @return int
     *   Max body length in bytes.
     */
    private function getMaxBodyLength(): int
    {
        // TODO: make this configurable.
        return static::DEFAULT_MAX_BODY_LENGTH;
    }

    /**
     * Gets the real, non-truncated, body length.
     *
     * @return int
     *   Body length in bytes.
     */
    public function getRealBodyLength(): int
    {
        return $this->realBodyLength;
    }

    /**
     * Tells if this content body may be analyzed.
     *
     * If false, it could mean that the body was truncated (too large) or impossible to analyze due to an unmanaged
     * content type.
     *
     * @return bool
     *   True if analyzable, false if not.
     */
    public function isBodyAnalyzable(): bool
    {
        return !$this->hasExceededLimit;
    }

    /**
     * Replaces the body string. This does not check the length.
     *
     * This is useful for obfuscation, which replaces the content.
     *
     * @param string $body The new body string.
     */
    public function replaceBody(string $body): void
    {
        $this->body = $body;
    }

    /**
     * Call this to reset the body. Empties it and optionally unsets the hasExceededLimit flag.
     *
     * @param bool $resetLimit Set to true to reset the hasExceededLimit flag. Defaults to true.
     */
    public function resetBody(bool $resetLimit = true): void
    {
        $this->body = '';

        if ($resetLimit) {
            $this->hasExceededLimit = false;
        }
    }

    /**
     * Writes the body coming from the stream.
     *
     * If body exceeds limit, it will be truncated.
     *
     * @param string $string
     *   The string to write.
     */
    public function write($string): void
    {
        $this->realBodyLength += strlen($string);

        // Write only the allowed length to the body.
        // This syntax is for when getMaxBodyLength() may dynamically update its value during the execution.
        $allowedWritableStringPart = substr($string, 0, max($this->getMaxBodyLength() - strlen($this->body), 0));
        $this->body .= $allowedWritableStringPart;

        if (strlen($allowedWritableStringPart) !== strlen($string)) {
            // Not the entire string was written. It means it was truncated.
            $this->hasExceededLimit = true;
        }
    }
}
