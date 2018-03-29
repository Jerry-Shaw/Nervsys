<?php

/**
 * Redis Cache Extension
 *
 * Copyright 2018 秋水之冰 <27206617@qq.com>
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

namespace ext;

use core\ctr\router;

class redis_cache extends redis
{
    //Cache life (in seconds)
    public static $life = 600;

    //Cache name
    public static $name = null;

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
        $name = self::get_name();
        $cache = json_encode($data);

        if (is_null(self::$redis)) self::$redis = parent::connect();
        $result = 0 < self::$life ? self::$redis->set($name, $cache, self::$life) : self::$redis->set($name, $cache);

        unset($data, $name, $cache);
        return $result;
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
        $cache = self::$redis->get(self::get_name());
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
        self::$redis->del(self::get_name());
    }

    /**
     * Get cache name
     *
     * @return string
     */
    private static function get_name(): string
    {
        $keys = is_null(self::$name) ? [router::$cmd, router::$data, self::$bind_session ? $_SESSION : []] : self::$name;
        $name = self::$prefix . hash('md5', json_encode($keys));

        unset($keys);
        return $name;
    }
}