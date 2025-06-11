<?php

namespace PassePlat\Core\AnalyzableContent\ExecutionTrace;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Exception\Exception;
use PassePlat\Core\Tool\DateTime;

/**
 * Provides execution trace details.
 */
class ExecutionTraceProvider extends ComponentBasedObject
{
    /**
     * Date time tool.
     *
     * @var DateTime|null
     */
    private ?DateTime $dateTime;

    /**
     * Trace details.
     *
     * @var mixed
     */
    private $details;

    /**
     * Microtime when the details were set.
     *
     * @var float
     */
    private float $microtime;

    /**
     * Trace source.
     *
     * @var object
     */
    private object $source;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(DateTime::class, $this->dateTime);
        return $definitions;
    }

    /**
     * Gets the microtime.
     *
     * @return float
     *   The microtime.
     */
    public function getMicrotime(): float
    {
        return $this->microtime;
    }

    /**
     * Returns info for the ExecutionTraceCollector.
     *
     * @return array
     *   The info for the ExecutionTraceCollector.
     */
    public function provideTraceInfo(): array
    {
        try {
            $encodedDetails = json_encode($this->details, JSON_THROW_ON_ERROR);
        } catch (\Exception $exception) {
            $encodedDetails = 'Could not parse trace details.';
        }

        try {
            $formattedMicrotime = $this->dateTime->getFormattedDateWithMicrosecondsFromMicrotime($this->microtime);
            $microsecondsFromStart = $this->dateTime->computeDuration($_SERVER['REQUEST_TIME_FLOAT'], $this->microtime);
        } catch (Exception $e) {
            // This is very unlikely to happen.
        }

        return [
            'datetime' => $formattedMicrotime ?? null,
            'microsecondsFromAppStart' => isset($microsecondsFromStart)
                ? $microsecondsFromStart->getUnscaledValue()->__toString()
                : $this->microtime - $_SERVER['REQUEST_TIME_FLOAT'],
            'source' => get_class($this->source),
            'details' => $encodedDetails,
        ];
    }

    /**
     * Sets the details.
     *
     * @param mixed $details
     *   Any serializable value. Please insert light values only.
     */
    public function setDetails($details): void
    {
        $this->details = $details;
        $this->microtime = microtime(true);
    }

    /**
     * Sets the trace source.
     *
     * @param object $source
     *   The trace source.
     */
    public function setTraceSource(object $source): void
    {
        $this->source = $source;
    }
}
