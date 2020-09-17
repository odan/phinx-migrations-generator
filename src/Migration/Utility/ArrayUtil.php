<?php

namespace Odan\Migration\Utility;

use Riimu\Kit\PHPEncoder\PHPEncoder;

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

    /**
     * Compare array (not).
     *
     * @param array $arr
     * @param array $arr2
     * @param array $keys
     *
     * @return bool
     */
    public function neq($arr, $arr2, $keys): bool
    {
        return !$this->eq($arr, $arr2, $keys);
    }

    /**
     * Compare array.
     *
     * @param array $arr
     * @param array $arr2
     * @param array $keys
     *
     * @return bool
     */
    private function eq($arr, $arr2, $keys): bool
    {
        $val1 = $this->find($arr, $keys);
        $val2 = $this->find($arr2, $keys);

        return $val1 === $val2;
    }

    /**
     * Get array value by keys.
     *
     * @param array $array
     * @param array $parts
     *
     * @return mixed
     */
    private function find($array, $parts)
    {
        foreach ($parts as $part) {
            if (!array_key_exists($part, $array)) {
                return null;
            }
            $array = $array[$part];
        }

        return $array;
    }

    /**
     * Prettify array.
     *
     * @param array $variable Array to prettify
     * @param int $tabCount Initial tab count
     *
     * @return string
     */
    public function prettifyArray(array $variable, int $tabCount): string
    {
        $encoder = new PHPEncoder();

        return $encoder->encode($variable, [
            'array.base' => $tabCount * 4,
            'array.inline' => true,
            'array.indent' => 4,
            'array.eol' => "\n",
            'string.escape' => false,
            'string.utf8' => true,
        ]);
    }
}
