<?php

/**
 * Keygen Extension
 *
 * Copyright 2016-2024 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Nervsys\Ext;

use Nervsys\Core\Factory;

class libKeygen extends Factory
{
    const KEY_ONLY_NUMBERS      = 1;
    const KEY_LOWERCASE_LETTERS = 2;
    const KEY_UPPERCASE_LETTERS = 4;
    const KEY_ALL_ALPHANUMERIC  = 7;

    /**
     * Create key in specific types of specific length
     *
     * @return string (Default 32 bits)
     */
    public function create(int $length = 32, int $key_type = self::KEY_ALL_ALPHANUMERIC): string
    {
        $char_list = [];

        if (!in_array($key_type, range(1, 7), true)) {
            $key_type = self::KEY_ALL_ALPHANUMERIC;
        }

        if (self::KEY_ONLY_NUMBERS === ($key_type & self::KEY_ONLY_NUMBERS)) {
            array_push($char_list, ...range(0, 9));
        }

        if (self::KEY_LOWERCASE_LETTERS === ($key_type & self::KEY_LOWERCASE_LETTERS)) {
            array_push($char_list, ...range('a', 'z'));
        }

        if (self::KEY_UPPERCASE_LETTERS === ($key_type & self::KEY_UPPERCASE_LETTERS)) {
            array_push($char_list, ...range('A', 'Z'));
        }

        $char_keys  = $char_list;
        $char_count = count($char_list);

        if ($length > $char_count) {
            $times = ceil($length / $char_count) - 1;

            for ($i = 0; $i < $times; ++$i) {
                array_push($char_keys, ...$char_list);
            }

            unset($times, $i);
        }

        shuffle($char_keys);

        $key = implode('', array_slice($char_keys, 0, $length));

        unset($length, $key_type, $char_list, $char_keys, $char_count);
        return $key;
    }

    /**
     * Extract AES Keys from Crypt Key
     *
     * @param string $key (suggested 32 bits)
     *
     * @return array
     */
    public function extract(string $key): array
    {
        $keys = [];

        $keys['key'] = &$key;
        $keys['iv']  = 0 === (ord($key[0]) & 1)
            ? substr($key, 0, 16)
            : substr($key, -16, 16);

        unset($key);
        return $keys;
    }

    /**
     * Obscure Crypt Key
     *
     * @param string $key (32 bits)
     *
     * @return string (40 bits)
     */
    public function obscure(string $key): string
    {
        $unit = str_split($key, 4);

        foreach ($unit as $k => $v) {
            $unit_key = $v[0];

            if ($this->getKvMode($k, $unit_key)) {
                $v = strrev($v);
            }

            $unit[$k] = $v . $unit_key;
        }

        $key = implode('', $unit);

        unset($unit, $k, $v, $unit_key);
        return $key;
    }

    /**
     * Rebuild Crypt Key
     *
     * @param string $key (40 bits)
     *
     * @return string (32 bits)
     */
    public function rebuild(string $key): string
    {
        $unit = str_split($key, 5);

        foreach ($unit as $k => $v) {
            $unit_key  = substr($v, -1, 1);
            $unit_item = substr($v, 0, 4);

            $unit[$k] = $this->getKvMode($k, $unit_key)
                ? strrev($unit_item)
                : $unit_item;
        }

        $key = implode('', $unit);

        unset($unit, $k, $v, $unit_key, $unit_item);
        return $key;
    }

    /**
     * Get KV logic mode (k & v)
     *
     * @param int    $k
     * @param string $v
     *
     * @return bool
     */
    private function getKvMode(int $k, string $v): bool
    {
        return 0 === ($k & ord($v));
    }
}