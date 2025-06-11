<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;

/**
 * Duration from the passeplat process start to the destination response emitted step.
 */
class AppExecutionUntilStopDuration extends TimingComputerBase
{
    /**
     * {@inheritdoc}
     */
    public function compute(array $times): array
    {
        if (empty($times[Timing::STEP__STOP]) || empty($_SERVER['REQUEST_TIME_FLOAT'])) {
            return [];
        }

        try {
            $data['app_execution_until_stop_duration'] = $this->datetime
                ->computeDuration($_SERVER['REQUEST_TIME_FLOAT'], $times[Timing::STEP__STOP])
                ->getUnscaledValue()->__toString();

            return $data;
        } catch (\Exception $exception) {
            // No need to abort the whole process. Let the script continue.
        }

        return [];
    }
}
