<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;

/**
 * Class DestinationResponseStartTime.
 */
class DestinationResponseStartTime extends TimingComputerBase
{
    /**
     * {@inheritdoc}
     */
    public function compute(array $times): array
    {
        // If empty, this is WSolution that is responding. Set the current time.
        if (empty($times[Timing::STEP__START])) {
            return [];
        }

        try {
            $data['destination_response_start_time'] = $this->datetime
                ->getFormattedDateWithMicrosecondsFromMicrotime($times[Timing::STEP__START]);

            return $data;
        } catch (\Exception $exception) {
            // No need to abort the whole process. Let the script continue.
        }

        return [];
    }
}
