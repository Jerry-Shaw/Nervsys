<?php

/**
 * Crypt Key Generator Interface
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

namespace ext\lib;

interface crypt_key
{
    /**
     * Create Crypt Key
     *
     * @return string (64 bits)
     */
    public static function create(): string;

    /**
     * Get Keys from Crypt Key
     *
     * @param string $key (64 bits)
     *
     * @return array
     */
    public static function get_keys(string $key): array;

    /**
     * Fix key length
     *
     * @param array $keys
     * @param int   $iv_len
     *
     * @return array
     */
    public static function fix_keys(array $keys, int $iv_len): array;

    /**
     * Build Mixed Key
     *
     * @param string $key (64 bits)
     *
     * @return string (80 bits)
     */
    public static function build(string $key): string;

    /**
     * Rebuild Crypt Key
     *
     * @param string $key (80 bits)
     *
     * @return string (64 bits)
     */
    public static function rebuild(string $key): string;
}