<?php

/**
 * Redis Connector Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
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

class redis
{
    /**
     * Redis settings
     */
    public static $host       = '127.0.0.1';
    public static $port       = 6379;
    public static $auth       = '';
    public static $db         = 0;
    public static $prefix     = '';
    public static $timeout    = 10;
    public static $persist    = true;
    public static $persist_id = null;

    //Current connection instance
    private static $connect = null;

    //Connection pool
    private static $pool = [];

    /**
     * Create new connection
     *
     * @return \Redis
     * @throws \Exception
     */
    private static function create(): \Redis
    {
        $redis = new \Redis();
        self::$persist ? $redis->pconnect(self::$host, self::$port, self::$timeout, self::$persist_id) : $redis->connect(self::$host, self::$port, self::$timeout);

        if ('' !== self::$auth && !$redis->auth(self::$auth)) throw new \Exception('Redis: Authentication Failed!');
        if (!$redis->select(self::$db)) throw new \Exception('Redis: DB [' . self::$db . '] NOT exist!');

        $redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
        if ('' !== self::$prefix) $redis->setOption(\Redis::OPT_PREFIX, self::$prefix . ':');

        return $redis;
    }

    /**
     * Create Redis instance
     *
     * @param string $name
     *
     * @return \Redis
     * @throws \Exception
     */
    public static function connect(string $name = ''): \Redis
    {
        self::$connect = '' === $name
            ? (self::$connect ?? self::create())
            : (self::$pool[$name] ?? self::$pool[$name] = self::create());

        unset($name);
        return self::$connect;
    }

    /**
     * Close Redis instance
     *
     * @param string $name
     */
    public static function close(string $name = ''): void
    {
        if ('' === $name) {
            $key = array_search(self::$connect, self::$pool, true);

            if (false !== $key) {
                self::$pool[$key] = null;
                unset(self::$pool[$key]);
            }

            self::$connect->close();
            self::$connect = null;
        } else {
            if (!isset(self::$pool[$name])) return;
            if (self::$connect === self::$pool[$name]) self::$connect = null;

            self::$pool[$name]->close();
            self::$pool[$name] = null;
            unset(self::$pool[$name]);
        }
    }
}