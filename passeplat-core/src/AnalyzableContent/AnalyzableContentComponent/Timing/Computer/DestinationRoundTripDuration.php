<?php

namespace PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Computer;

use PassePlat\Core\AnalyzableContent\AnalyzableContentComponent\Timing\Timing;
use PassePlat\Core\Exception\Exception;

/**
 * Round trip duration value between passeplat and destination.
 */
class DestinationRoundTripDuration extends TimingComputerBase
{
    /**
     * {@inheritdoc}
     */
    public function compute(array $times): array
    {
        if (empty($times[Timing::DURATION__DESTINATION_ROUND_TRIP])) {
            return [];
        }

        try {
            $data['destination_round_trip_duration'] =
                $this->datetime->computeDuration(0, $times[Timing::DURATION__DESTINATION_ROUND_TRIP])
                    ->getUnscaledValue()->__toString();
        } catch (Exception $e) {
            // No need to abort the whole process. Let the script continue.
            return [];
        }
        return $data;
    }
}
