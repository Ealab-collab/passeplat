<?php

namespace PassePlat\Core\Event;

use Dakwamine\Component\Event\EventInterface;
use PassePlat\Core\AnalyzableContent\AnalyzableContent;

/**
 * Event on processed request.
 */
class ProcessedRequestEvent implements EventInterface
{
    const EVENT_NAME = 'PASSEPLAT_CORE__PROCESSED_REQUEST';

    /**
     * Content ready for analysis.
     *
     * @var AnalyzableContent
     */
    private $analyzableContent;

    /**
     * ProcessedRequestEvent constructor.
     *
     * @param AnalyzableContent $analyzableContent
     *   Content ready for analysis.
     */
    public function __construct(
        AnalyzableContent $analyzableContent
    ) {
        $this->analyzableContent = $analyzableContent;
    }

    /**
     * Gets the content ready for analysis.
     *
     * @return AnalyzableContent
     *   Gets the content ready for analysis.
     */
    public function getAnalyzableContent(): AnalyzableContent
    {
        return $this->analyzableContent;
    }

    public function getName(): string
    {
        return static::EVENT_NAME;
    }
}
