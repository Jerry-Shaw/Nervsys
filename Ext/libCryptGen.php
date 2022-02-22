<?php

/**
 * Crypt KeyGen Extension
 *
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

use Nervsys\LC\Factory;

class libCryptGen extends Factory
{
    /**
     * Create Crypt Key
     *
     * @return string (32 bits)
     */
    public function create(): string
    {
        return hash('md5', uniqid(mt_rand(), true));
    }

    /**
     * Extract AES Keys from Crypt Key
     *
     * @param string $key (32 bits)
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

        $key = implode($unit);

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

        $key = implode($unit);

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