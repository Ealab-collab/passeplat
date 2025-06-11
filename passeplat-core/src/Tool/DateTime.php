<?php

namespace PassePlat\Core\Tool;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use PassePlat\Core\Exception\Exception;

/**
 * Date and time tools.
 */
class DateTime
{
    const SCALE = 6;

    /**
     * Indicate whether the date_default_timezone is well configured or not.
     *
     * @var bool
     */
    private static bool $timezoneConfigured = false;

    /**
     * Computes the duration between two microtimes.
     *
     * @param float|string $startMicrotime
     *   Microtime start value as microtime(false|true).
     * @param float|string $stopMicrotime
     *   Microtime stop value as microtime(false|true).
     *
     * @return BigDecimal
     *   Duration as BigDecimal in seconds with microsecond precision (6 decimal places on fractional part).
     *
     * @throws Exception
     * @throws MathException
     */
    public function computeDuration($startMicrotime, $stopMicrotime): BigDecimal
    {
        $startMicrotime_dec = $this->microtimeToDecimal($startMicrotime);
        $stopMicrotime_dec = $this->microtimeToDecimal($stopMicrotime);
        return $stopMicrotime_dec
            ->minus($startMicrotime_dec)
            ->toScale(static::SCALE, RoundingMode::UP);
    }

    /**
     * Converts a date string into a Unix timestamp.
     *
     * @param string $dateString
     *   Date string in 'Y-m-d\TH:i' format.
     *
     * @return int
     *   Corresponding Unix timestamp.
     *
     * @throws \Exception
     *   If the date format is invalid.
     */
    public static function convertDateToTimestamp(string $dateString): int
    {
        static::setTimezone();

        $date = \DateTime::createFromFormat('Y-m-d\TH:i', $dateString);

        if ($date === false) {
            throw new \Exception('Date format is invalid: ' . $dateString);
        }

        return $date->getTimestamp() ;
    }

    /**
     * Converts a Unix timestamp into a formatted date string.
     *
     * @param int $timestamp
     *   Unix timestamp in milliseconds.
     *
     * @return string
     *   Formatted date in 'd/m/Y H:i:s' format.
     */
    public static function convertTimestampToDate(int $timestamp): string
    {
        $time = $timestamp / 1000;
        $date = new \DateTime("@$time");
        return ($date->format('d/m/Y H:i:s'));
    }

    /**
     * Converts a timestamp to a human-readable date string.
     *
     * @param float $timestamp
     *   The timestamp in milliseconds.
     *
     * @return string
     *   The formatted date string or 'N/A' if timestamp is empty.
     */
    public static function convertTimestampToString(float $timestamp): string
    {
        if (empty($timestamp)) {
            return 'N/A';
        }

        $timestampInSeconds = (int)($timestamp / 1000);

        //TODO The month should be multiple languages.
        return date('d F Y H:i:s', $timestampInSeconds);
    }

    /**
     * Formats a date from Elasticsearch into a human-readable format.
     *
     * @param $dateES
     *   The date in Elasticsearch format.
     *
     * @return string
     *   The formatted date as 'd/m/Y H:i:s'.
     *
     * @throws \DateMalformedStringException
     */
    public static function getFormatedDate($dateES): string
    {
        static::setTimezone();
        $date = new \DateTime($dateES);
        return $date->format("d/m/Y H:i:s");
    }

    /**
     * Gets the ISO 8601 formatted time for the given microtime.
     *
     * @param float|string|null $microtime
     *   Microtime value. Accepts the microtime(false|true) notation.
     *
     * @return string
     *   The formatted date.
     *
     * @throws Exception
     *   An error on input value was found.
     * @throws MathException
     */
    public function getFormattedDateWithMicrosecondsFromMicrotime(string $microtime = null): string
    {
        if (empty($microtime)) {
            // Microtime value in "msec sec" notation.
            // microtime() is implicitly UTC.
            /** @var string $microtime */
            $microtime = microtime();
        }
        $decimalTime = $this->microtimeToDecimal($microtime);

        return $this->getFormattedDateWithMicroseconds($decimalTime);
    }

    /**
     * Gets the ISO 8601 formatted date with microseconds appended.
     *
     * @param BigDecimal $time
     *   Microsecond precision time (timestamp + microseconds).
     *
     * @return string
     *   The formatted date.
     *
     * @throws MathException
     */
    public function getFormattedDateWithMicroseconds(BigDecimal $time): string
    {
        // Ensure scale.
        $time = $time->toScale(static::SCALE, RoundingMode::UP);

        $timestamp = $time->getIntegralPart();
        $microseconds = $time->getFractionalPart();

        // As we're dealing with timestamps, the timezone is UTC.
        $datetime = \DateTime::createFromFormat('U u', (string) $timestamp . ' ' . $microseconds);
        $formatted = $datetime->format('Y-m-d\TH:i:s.u\Z');
        return $formatted;
    }

    /**
     * Gets the decimal value from microtime.
     *
     * @param string|float $microtime
     *   Microtime value as given by microtime(false|true).
     *
     * @return BigDecimal
     * The decimal value from microtime.
     *
     * @throws Exception
     *   An error on input value was found.
     * @throws MathException
     */
    public function microtimeToDecimal($microtime): BigDecimal
    {
        if (is_numeric($microtime)) {
            // Convert to a string representation with enough decimal places.
            $numericString = $this->numericToString($microtime);

            if ($numericString === null) {
                throw new Exception(sprintf(
                    'Not a valid microtime input value. Given microtime value was %s.',
                    $microtime
                ));
            }

            $microtimeAsBigDecimal = BigDecimal::of($numericString);

            if ($microtimeAsBigDecimal->isLessThan(0)) {
                throw new Exception(sprintf(
                    'Not a valid microtime input value. Given microtime value was %s.',
                    $microtime
                ));
            }

            return $microtimeAsBigDecimal;
        }

        if (!is_string($microtime)) {
            throw new Exception(sprintf(
                'Not a valid microtime input value. Given microtime value was %s.',
                $microtime
            ));
        }

        $microtime_values = explode(' ', $microtime);

        if (count($microtime_values) !== 2) {
            throw new Exception(sprintf(
                'Not a valid microtime input value. Given microtime value was %s.',
                $microtime
            ));
        }

        // Seconds are represented as an integer timestamp string.
        $secondsTimestamp = BigInteger::of($microtime_values[1]);

        // Subsecond part containing fractional part value string.
        $subSecondPart = BigDecimal::of($microtime_values[0]);

        // Subsecond part is comprised between 0 included and 1 excluded.
        if ($subSecondPart->isLessThan(0) || $subSecondPart->isGreaterThanOrEqualTo(1)) {
            throw new Exception(sprintf(
                'Not a valid microtime subsecond part input value. Given microtime value was %s.',
                $microtime
            ));
        }

        return $subSecondPart->plus($secondsTimestamp);
    }

    /**
     * Converts a numeric value to a string.
     *
     * @param $numericValue
     *   Value of numeric type or a string representing a numeric value.
     *
     * @return string|null
     *   The string representation of the value. Returns null if the input value is not numeric.
     */
    private function numericToString($numericValue): ?string
    {
        if (!is_numeric($numericValue)) {
            return null;
        }

        if (!is_float($numericValue)) {
            return (string) $numericValue;
        }

        // Due to the precision of float values configured in the php.ini,
        // the fractional part may be limited to 4 or less decimal places
        // when the value is casted to a string,
        // so we need to replace the value with a string representation
        // of the float value with enough decimal places.
        // The +2 scale is there for rounding on computations.
        return number_format($numericValue, static::SCALE + 2, '.', '');
    }

    /**
     * Sets the default timezone to UTC if it hasn't been set already.
     */
    private static function setTimezone(): void
    {
        if (!static::$timezoneConfigured) {
            date_default_timezone_set('UTC');
            static::$timezoneConfigured = true;
        }
    }
}
