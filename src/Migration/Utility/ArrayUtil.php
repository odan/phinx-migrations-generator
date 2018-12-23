<?php

namespace Odan\Migration\Utility;

/**
 * Class ArrayUtil.
 */
class ArrayUtil
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
}
