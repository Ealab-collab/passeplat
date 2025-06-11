<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing;

use Dakwamine\Component\Exception\UnmetDependencyException;
use Dakwamine\Component\RootDependencyDefinition;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\AnalyzableContentComponentBase;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\AppExecutionUntilStopDuration;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\DestinationResponseStartedReceivingTime;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\DestinationResponseStartTime;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\DestinationResponseStartToStopDuration;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\DestinationResponseStopTime;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\DestinationResponseWaitDuration;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\DestinationRoundTripDuration;
use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer\TimingComputerBase;
use PassePlat\Core\Tool\DateTime;

/**
 * Contains timings.
 */
class Timing extends AnalyzableContentComponentBase
{
    const DURATION__DESTINATION_ROUND_TRIP = 'DURATION__ALL_DESTINATION_TRANSFERS';
    const STEP__START = 'STEP__START';
    const STEP__STARTED_RECEIVING = 'STEP__STARTED_RECEIVING';
    const STEP__STOP = 'STEP__STOP';

    /**
     * Date time tool.
     *
     * @var DateTime|null
     */
    private ?DateTime $dateTime;

    /**
     * Microtimes array indexed by arbitrary keys.
     *
     * @var string[]
     */
    private array $times = [];

    /**
     * Tells if timing computers have been retrieved at least once.
     *
     * @var bool
     */
    private bool $timingComputersInitDone = false;

    public function getDependencyDefinitions(): array
    {
        $definitions = parent::getDependencyDefinitions();
        $definitions[] = new RootDependencyDefinition(DateTime::class, $this->dateTime);
        return $definitions;
    }

    /**
     * {@inheritdoc}
     */
    public function getComponentDataToLog(): array
    {
        $data = $this->getSubComponentsDataToLog();

        foreach ($this->getTimingComputers() as $timingComputer) {
            $data = array_merge($data, $timingComputer->compute($this->times));
        }

        return $data;
    }

    /**
     * Default timing computers class names.
     *
     * @return string[]
     */
    private function getDefaultTimingComputersNames(): array
    {
        return [
            AppExecutionUntilStopDuration::class,
            DestinationResponseStartedReceivingTime::class,
            DestinationResponseStartTime::class,
            DestinationResponseStartToStopDuration::class,
            DestinationResponseStopTime::class,
            DestinationResponseWaitDuration::class,
            DestinationRoundTripDuration::class,
        ];
    }

    /**
     * Gets the enabled timing computers.
     *
     * @return TimingComputerBase[]
     *   Array of enabled timing computers.
     */
    private function getTimingComputers(): array
    {
        if (!$this->timingComputersInitDone) {
            // TODO: configurable.
            $timingComputersNames = $this->getDefaultTimingComputersNames();

            foreach ($timingComputersNames as $timingComputersName) {
                try {
                    // Getting the component instead of adding will prevent dupes.
                    $this->getComponentByClassName($timingComputersName, true);
                } catch (UnmetDependencyException $e) {
                    // Don't fail here.
                    continue;
                }
            }

            $this->timingComputersInitDone = true;
        }

        return $this->getComponentsByClassName(TimingComputerBase::class);
    }

    /**
     * Sets a micro time.
     *
     * @param string $type
     *   Time type. See Timing consts.
     * @param float|string $microtime
     *   microtime(false|true) value. Leave empty for current microtime.
     */
    public function setMicrotime(string $type, $microtime = null): void
    {
        if (empty($type)) {
            return;
        }

        $this->times[$type] = empty($microtime) ? microtime() : $microtime;
    }

    /**
     * Unsets a microtime.
     *
     * @param string $type
     *   Time type. See Timing consts.
     */
    public function unsetMicrotime(string $type): void
    {
        unset($this->times[$type]);
    }
}
