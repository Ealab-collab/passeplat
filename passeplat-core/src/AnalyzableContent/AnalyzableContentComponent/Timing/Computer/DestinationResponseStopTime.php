<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;

/**
 * Destination response stop time.
 */
class DestinationResponseStopTime extends TimingComputerBase
{
    /**
     * {@inheritdoc}
     */
    public function compute(array $times): array
    {
        if (empty($times[Timing::STEP__STOP]) || empty($times[Timing::STEP__STARTED_RECEIVING])) {
            return [];
        }

        try {
            $data['destination_response_stop_time'] = $this->datetime
                ->getFormattedDateWithMicrosecondsFromMicrotime($times[Timing::STEP__STOP]);

            return $data;
        } catch (\Exception $exception) {
            // No need to abort the whole process. Let the script continue.
        }

        return [];
    }
}
