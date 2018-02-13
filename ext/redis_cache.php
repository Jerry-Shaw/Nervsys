<?php

/**
 * Redis Cache Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2018 Jerry Shaw
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

use core\ctr\router;

class redis_cache extends redis
{
    //Cache life (in seconds)
    public static $life = 600;

    //Cache prefix
    public static $prefix = 'cache:';

    //Redis connection
    private static $redis = null;

    /**
     * Set cache
     *
     * @param array  $data
     * @param string $key
     *
     * @return bool
     * @throws \Exception
     */
    public static function set(array $data, string $key = ''): bool
    {
        if (empty($data)) return false;

        if (is_null(self::$redis)) self::$redis = parent::connect();

        $key = '' === $key ? self::build_key() : self::$prefix . $key;

        if (!self::$redis->setnx($key, json_encode($data))) return false;
        self::$redis->expire($key, 0 < self::$life ? self::$life : 600);

        unset($data, $key);
        return true;
    }

    /**
     * Get cache
     *
     * @param string $key
     *
     * @return array
     * @throws \Exception
     */
    public static function get(string $key = ''): array
    {
        if (is_null(self::$redis)) self::$redis = parent::connect();

        $cache = self::$redis->get('' === $key ? self::build_key() : self::$prefix . $key);
        if (false === $cache) return [];

        $data = json_decode($cache, true);
        if (!is_array($data)) return [];

        unset($key, $cache);
        return $data;
    }

    /**
     * Delete cache
     *
     * @param string $key
     *
     * @throws \Exception
     */
    public static function del(string $key = ''): void
    {
        if (is_null(self::$redis)) self::$redis = parent::connect();

        self::$redis->del('' === $key ? self::build_key() : self::$prefix . $key);

        unset($key);
    }

    /**
     * Build cache key
     *
     * @param array $keys
     *
     * @return string
     */
    public static function build_key(array $keys = []): string
    {
        if (empty($keys)) $keys = ['cmd' => router::$cmd, 'data' => router::$data, 'session' => &$_SESSION];
        $key = self::$prefix . hash('md5', json_encode($keys));

        unset($keys);
        return $key;
    }
}