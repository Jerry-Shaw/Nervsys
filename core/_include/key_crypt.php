<?php

/**
 * Crypt Key Generator Module
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
class key_crypt
{
    /**
     * Generate Crypt Key
     *
     * @return string (64 bits)
     */
    public static function get_key(): string
    {
        return hash('sha256', get_uuid());
    }

    /**
     * Get Keys from Crypt Key
     *
     * @param string $key (64 bits)
     *
     * @return array
     */
    public static function get_keys(string $key): array
    {
        $keys = ['alg' => ord(substr($key, 0, 1)) & 1];
        switch ($keys['alg']) {
            case 0:
                $keys['key'] = substr($key, 0, 32);
                $keys['iv'] = substr($key, -16, 16);
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
     * Get Mixed Crypt Key
     *
     * @param string $key (64 bits)
     *
     * @return string (80 bits)
     */
    public static function get_mixed(string $key): string
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
     * Get Rebuilt Crypt Key
     *
     * @param string $key (80 bits)
     *
     * @return string (64 bits)
     */
    public static function get_rebuilt(string $key): string
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