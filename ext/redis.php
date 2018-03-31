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

    //Connection instance
    private static $connect = null;

    /**
     * Create new connection
     *
     * @return \Redis
     * @throws \Exception
     */
    private static function create(): \Redis
    {
        self::$connect = new \Redis();
        self::$persist ? self::$connect->pconnect(self::$host, self::$port, self::$timeout, self::$persist_id) : self::$connect->connect(self::$host, self::$port, self::$timeout);

        if ('' !== self::$auth && !self::$connect->auth(self::$auth)) throw new \Exception('Redis: Authentication Failed!');
        if (!self::$connect->select(self::$db)) throw new \Exception('Redis: DB [' . self::$db . '] NOT exist!');

        self::$connect->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_NONE);
        if ('' !== self::$prefix) self::$connect->setOption(\Redis::OPT_PREFIX, self::$prefix . ':');

        return self::$connect;
    }

    /**
     * Create Redis instance
     *
     * @return \Redis
     * @throws \Exception
     */
    public static function connect(): \Redis
    {
        return self::$connect ?? self::create();
    }

    /**
     * Close Redis instance
     */
    public static function close(): void
    {
        self::$connect->close();
        self::$connect = null;
    }
}