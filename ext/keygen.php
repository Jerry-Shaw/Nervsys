<?php

/**
 * Crypt Key Generator Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
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

use ext\lib\keys;

class keygen implements keys
{
    /**
     * Create Crypt Key
     *
     * @return string (64 bits)
     */
    public static function create(): string
    {
        return hash('sha256', uniqid(mt_rand(), true));
    }

    /**
     * Parse Keys from Crypt Key
     *
     * @param string $key (64 bits)
     *
     * @return array
     */
    public static function parse(string $key): array
    {
        $keys = [];
        switch (ord(substr($key, 0, 1)) & 1) {
            case 0:
                $keys['key'] = substr($key, 0, 32);
                $keys['iv'] = substr($key, -32, 16);
                break;
            case 1:
                $keys['key'] = substr($key, -32, 32);
                $keys['iv'] = substr($key, 0, 16);
                break;
        }
        unset($key);
        return $keys;
    }

    /**
     * Mix Crypt Key
     *
     * @param string $key (64 bits)
     *
     * @return string (80 bits)
     */
    public static function mix(string $key): string
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
     * Build Crypt Key
     *
     * @param string $key (80 bits)
     *
     * @return string (64 bits)
     */
    public static function build(string $key): string
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