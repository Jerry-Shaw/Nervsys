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
    //Cache key
    public static $key = null;

    //Cache life (in seconds)
    public static $life = 600;

    //Cache prefix
    public static $prefix = 'cache:';

    //Bind session
    public static $bind_session = false;

    //Redis connection
    private static $redis = null;

    /**
     * Set cache
     *
     * @param array $data
     *
     * @return bool
     * @throws \Exception
     */
    public static function set(array $data): bool
    {
        if (empty($data)) return false;

        if (is_null(self::$redis)) self::$redis = parent::connect();
        if (!self::$redis->set(self::prep_key(), json_encode($data), self::$life)) return false;

        unset($data);
        return true;
    }

    /**
     * Get cache
     *
     * @return array
     * @throws \Exception
     */
    public static function get(): array
    {
        if (is_null(self::$redis)) self::$redis = parent::connect();
        $cache = self::$redis->get(self::prep_key());
        if (false === $cache) return [];

        $data = json_decode($cache, true);
        if (!is_array($data)) return [];

        unset($cache);
        return $data;
    }

    /**
     * Delete cache
     *
     * @throws \Exception
     */
    public static function del(): void
    {
        if (is_null(self::$redis)) self::$redis = parent::connect();
        self::$redis->del(self::prep_key());
    }

    /**
     * Prepare cache key
     *
     * @return string
     */
    private static function prep_key(): string
    {
        //Build keys
        $keys = is_null(self::$key) ? [router::$cmd, router::$data, self::$bind_session ? $_SESSION : []] : self::$key;
        $key = self::$prefix . hash('md5', json_encode($keys));

        unset($keys);
        return $key;
    }
}