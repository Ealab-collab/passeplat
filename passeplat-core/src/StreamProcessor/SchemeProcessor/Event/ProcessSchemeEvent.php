<?php

namespace PassePlat\Core\StreamProcessor\SchemeProcessor\Event;

use Dakwamine\Component\Event\EventInterface;
use PassePlat\Core\StreamProcessor\SchemeProcessor\SchemeProcessor;

/**
 * Called when scheme processors are called.
 */
class ProcessSchemeEvent implements EventInterface
{
    const EVENT_NAME = 'PASSEPLAT_CORE__SCHEME_PROCESSOR__PROCESS_SCHEME';

    /**
     * Processors for the given scheme.
     *
     * @var SchemeProcessor[]
     */
    private $processors = [];

    /**
     * URI Scheme.
     *
     * @var string
     */
    private $scheme = '';

    /**
     * ProcessSchemeEvent constructor.
     *
     * @param string $scheme
     *   The called scheme, e.g. http, https, ftp.
     */
    public function __construct($scheme)
    {
        $this->scheme = $scheme;
    }

    /**
     * Adds the given processor to scheme processors.
     *
     * @param SchemeProcessor $processor
     *   The processor.
     */
    public function addProcessor(SchemeProcessor $processor)
    {
        $this->processors[] = $processor;
    }

    public function getName(): string
    {
        return static::EVENT_NAME;
    }

    /**
     * Available processors for this scheme.
     *
     * @return SchemeProcessor[]
     */
    public function getProcessors(): array
    {
        return $this->processors;
    }

    /**
     * The scheme to process.
     *
     * @return string
     *   URI scheme, e.g. http, https, ftp.
     */
    public function getScheme(): string
    {
        return $this->scheme;
    }
}
