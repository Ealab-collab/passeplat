<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;

/**
 * Class DestinationResponseStartToStopDuration.
 */
class DestinationResponseStartToStopDuration extends TimingComputerBase
{
    /**
     * {@inheritdoc}
     */
    public function compute(array $times): array
    {
        if (empty($times[Timing::STEP__STOP]) || empty($times[Timing::STEP__START])) {
            return [];
        }

        try {
            $data['destination_response_start_to_stop_duration'] = $this->datetime
                ->computeDuration($times[Timing::STEP__START], $times[Timing::STEP__STOP])
                ->getUnscaledValue()->__toString();

            return $data;
        } catch (\Exception $exception) {
            // No need to abort the whole process. Let the script continue.
        }

        return [];
    }
}
