<?php

namespace PassePlat\Core\Tool;

/**
 * Compares basic objects, arrays, or values.
 */
class PropertiesComparer
{
    /**
     * Compares two values.
     *
     * @param mixed $a
     *   A value of any type.
     * @param mixed $b
     *   A value of any type.
     * @param bool $checkSameOrder
     *   Whether to check the order of the values in arrays and object properties.
     *
     * @return bool
     *   Returns true if the values are equal, false otherwise.
     */
    public static function compareAny($a, $b, bool $checkSameOrder = false): bool
    {
        if ($checkSameOrder) {
            // The standard comparison operator already takes into account the order.
            return $a === $b;
        }

        if (is_object($a) && is_object($b)) {
            return static::compareObjects($a, $b);
        } elseif (is_array($a) && is_array($b)) {
            return static::compareArrays($a, $b);
        } else {
            return $a === $b;
        }
    }

    /**
     * Compares two arrays.
     *
     * @param array $a
     *   An array.
     * @param array $b
     *   Another array.
     *
     * @return bool
     *   Returns true if the arrays are equal, false otherwise.
     */
    public static function compareArrays(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        foreach ($a as $aKey => $aValue) {
            if (!isset($b[$aKey])) {
                return false;
            }

            if (!static::compareAny($aValue, $b[$aKey])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compares two objects.
     *
     * @param object $a
     *   An object.
     * @param object $b
     *   Another object.
     *
     * @return bool
     *   Returns true if the objects are equal, false otherwise.
     */
    public static function compareObjects(object $a, object $b): bool
    {
        if (get_class($a) !== get_class($b)) {
            return false;
        }

        // We compare only the public properties.
        $aProperties = get_object_vars($a);
        $bProperties = get_object_vars($b);

        if (count($aProperties) !== count($bProperties)) {
            return false;
        }

        foreach ($aProperties as $aPropertyKey => $aPropertyValue) {
            if (!isset($bProperties[$aPropertyKey])) {
                return false;
            }

            if (!static::compareAny($aPropertyValue, $bProperties[$aPropertyKey])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a value contains another value.
     *
     * @param mixed $a
     *   A value of any type.
     * @param mixed $b
     *   A value of any type.
     *
     * @return bool
     *   Returns true if the first value contains the second one, false otherwise.
     *   For arrays and objects, the second value must be a subset of the first one.
     *   For scalar values, the first value must be equal to the second one.
     */
    public static function containsAny($a, $b): bool
    {
        if (is_object($a) && is_object($b)) {
            return static::containsObject($a, $b);
        } elseif (is_array($a) && is_array($b)) {
            return static::containsArray($a, $b);
        } else {
            return $a === $b;
        }
    }

    /**
     * Checks if an array contains another array.
     *
     * @param array $a
     *   An array.
     * @param array $b
     *   Another array.
     *
     * @return bool
     *   Returns true if the first array contains the second one, false otherwise.
     */
    public static function containsArray(array $a, array $b): bool
    {
        if (count($a) < count($b)) {
            return false;
        }

        foreach ($b as $bKey => $bValue) {
            if (!isset($a[$bKey])) {
                return false;
            }

            if (!static::containsAny($a[$bKey], $bValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if an object contains another object.
     *
     * The objects must be of the same class.
     * The properties of the second object must be a subset of the properties of the first one.
     *
     * @param object $a
     *   An object.
     * @param object $b
     *   Another object.
     *
     * @return bool
     *   Returns true if the first object contains the second one, false otherwise.
     */
    public static function containsObject(object $a, object $b): bool
    {
        if (get_class($a) !== get_class($b)) {
            return false;
        }

        // We compare only the public properties.
        $aProperties = get_object_vars($a);
        $bProperties = get_object_vars($b);

        if (count($aProperties) < count($bProperties)) {
            return false;
        }

        foreach ($bProperties as $bPropertyKey => $bPropertyValue) {
            if (!isset($aProperties[$bPropertyKey])) {
                return false;
            }

            if (!static::containsAny($aProperties[$bPropertyKey], $bPropertyValue)) {
                return false;
            }
        }

        return true;
    }
}
