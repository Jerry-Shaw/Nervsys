<?php

/**
 * Redis Connector Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
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
     * Default settings for Redis
     */
    public static $host    = '127.0.0.1';
    public static $port    = 6379;
    public static $auth    = '';
    public static $db      = 0;
    public static $prefix  = '';
    public static $timeout = 10;
    public static $persist = true;

    /**
     * Connect Redis
     *
     * @return \Redis
     * @throws \Exception
     */
    public static function connect(): \Redis
    {
        $redis = new \Redis();

        if (self::$persist ? !$redis->pconnect(self::$host, self::$port) : !$redis->connect(self::$host, self::$port)) throw new \Exception('Redis: Host or Port ERROR!');
        if ('' !== self::$auth && !$redis->auth((string)self::$auth)) throw new \Exception('Redis: Authentication Failed!');

        if ('' !== self::$prefix) $redis->setOption($redis::OPT_PREFIX, (string)self::$prefix . ':');

        $redis->setOption($redis::OPT_READ_TIMEOUT, self::$timeout);
        $redis->setOption($redis::OPT_SERIALIZER, $redis::SERIALIZER_NONE);

        if (!$redis->select((int)self::$db)) throw new \Exception('Redis: DB ' . self::$db . ' NOT exist!');

        return $redis;
    }
}