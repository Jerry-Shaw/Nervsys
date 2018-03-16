<?php

/**
 * Crypt Key Generator Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2018 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ext;

use ext\lib\key;

class keygen implements key
{
    /**
     * Create Crypt Key
     *
     * @return string (32 bits)
     */
    public static function create(): string
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
    public static function extract(string $key): array
    {
        $keys = [];
        $keys['key'] = &$key;
        $keys['iv'] = 0 === ord(substr($key, 0, 1)) & 1 ? substr($key, 0, 16) : substr($key, -16, 16);

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
    public static function obscure(string $key): string
    {
        $unit = str_split($key, 4);

        foreach ($unit as $k => $v) {
            $unit_key = substr($v, 0, 1);
            if ($k & 1 !== ord($unit_key) & 1) $v = strrev($v);
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
    public static function rebuild(string $key): string
    {
        $unit = str_split($key, 5);

        foreach ($unit as $k => $v) {
            $unit_key = substr($v, -1, 1);
            $unit_item = substr($v, 0, 4);
            $unit[$k] = ($k & 1 !== ord($unit_key) & 1) ? strrev($unit_item) : $unit_item;
        }

        $key = implode($unit);

        unset($unit, $k, $v, $unit_key, $unit_item);
        return $key;
    }
}