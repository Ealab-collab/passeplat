<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use Dakwamine\Component\ComponentBasedObject;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\Tool\DateTime;

/**
 * Base class for timing computers.
 */
abstract class TimingComputerBase extends ComponentBasedObject
{
    /**
     * Datetime tool.
     *
     * @var DateTime
     */
    protected $datetime;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(DateTime::class, $this->datetime);
        return $definitions;
    }

    /**
     * Computes the timings given the microtime values.
     *
     * @param string[] $times
     *   Array of microtime values.
     *
     * @return array
     *   Array containing computed timings. May be empty.
     */
    abstract public function compute(array $times): array;
}
