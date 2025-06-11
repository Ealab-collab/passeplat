<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;

/**
 * Wait duration value.
 */
class DestinationResponseWaitDuration extends TimingComputerBase
{
    /**
     * {@inheritdoc}
     */
    public function compute(array $times): array
    {
        if (empty($times[Timing::STEP__START]) || empty($times[Timing::STEP__STARTED_RECEIVING])) {
            return [];
        }

        try {
            $data['destination_response_wait_duration'] = $this->datetime
                ->computeDuration($times[Timing::STEP__START], $times[Timing::STEP__STARTED_RECEIVING])
                ->getUnscaledValue()->__toString();

            return $data;
        } catch (\Exception $exception) {
            // No need to abort the whole process. Let the script continue.
        }

        return [];
    }
}
