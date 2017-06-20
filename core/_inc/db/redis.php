<?php

/**
 * Redis Module
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

namespace core\db;

class redis
{
    /**
     * Declare all the parameters for Redis instance
     * All default to NULL, but can be changed by passing variables
     * Passing different parameters can produce different Redis instances
     */
    public static $redis_host;
    public static $redis_port;
    public static $redis_db;
    public static $redis_auth;
    public static $redis_persistent;

    /**
     * @return \Redis
     */
    public static function connect(): \Redis
    {
        //Parameters for Redis instance
        $redis_host = self::$redis_host ?? Redis_HOST;
        $redis_port = self::$redis_port ?? Redis_PORT;
        $redis_db = self::$redis_db ?? Redis_DB;
        $redis_auth = self::$redis_auth ?? Redis_AUTH;
        //Try to connect Redis Server
        try {
            $db_redis = new \Redis();
            $connect = (self::$redis_persistent ?? Redis_PERSISTENT) ? 'pconnect' : 'connect';
            $db_redis->$connect($redis_host, $redis_port);
            if (!is_null($redis_auth) && '' !== $redis_auth) $db_redis->auth($redis_auth);
            $db_redis->select($redis_db);
            unset($connect);
        } catch (\Exception $error) {
            exit('Failed to connect Redis Server! ' . $error->getMessage());
        }
        unset($redis_host, $redis_port, $redis_db, $redis_auth);
        //Return the Redis instance
        return $db_redis;
    }
}