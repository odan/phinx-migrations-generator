<?php

namespace Odan\Migration\Utility;

/**
 * Class ArrayUtil.
 */
final class ArrayUtil
{
    /**
     * Unset array keys.
     *
     * @param array $array The array
     * @param string $unwantedKey The key to remove
     *
     * @return void
     */
    public function unsetArrayKeys(array &$array, string $unwantedKey): void
    {
        unset($array[$unwantedKey]);

        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->unsetArrayKeys($value, $unwantedKey);
            }
        }
    }

    /**
     * Intersect of recursive arrays.
     *
     * @param array $array1 The array 1
     * @param array $array2 The array 2
     *
     * @return array
     */
    public function diff(array $array1, array $array2): array
    {
        $difference = [];

        foreach ($array1 as $key => $value) {
            if (is_array($value)) {
                if (!isset($array2[$key]) || !is_array($array2[$key])) {
                    $difference[$key] = $value;
                } else {
                    $new_diff = $this->diff($value, $array2[$key]);
                    if (!empty($new_diff)) {
                        $difference[$key] = $new_diff;
                    }
                }
            } elseif (!array_key_exists($key, $array2) || $array2[$key] !== $value) {
                $difference[$key] = $value;
            }
        }

        return $difference;
    }
}
