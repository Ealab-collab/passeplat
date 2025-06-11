<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;

/**
 * Class DestinationResponseStartedReceivingTime.
 */
class DestinationResponseStartedReceivingTime extends TimingComputerBase
{
    /**
     * {@inheritdoc}
     */
    public function compute(array $times): array
    {
        if (empty($times[Timing::STEP__STARTED_RECEIVING])) {
            return [];
        }

        try {
            $data['destination_response_step_started_receiving_time'] = $this->datetime
                ->getFormattedDateWithMicrosecondsFromMicrotime($times[Timing::STEP__STARTED_RECEIVING]);

            return $data;
        } catch (\Exception $exception) {
            // No need to abort the whole process. Let the script continue.
        }

        return [];
    }
}
