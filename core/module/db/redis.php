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
     * Default settings for Redis
     */
    public static $host       = '127.0.0.1';
    public static $port       = 6379;
    public static $auth       = '';
    public static $db         = 0;
    public static $prefix     = '';
    public static $timeout    = 10;
    public static $persistent = true;
    public static $session    = true;

    /**
     * @return \Redis
     */
    public static function connect(): \Redis
    {
        try {
            $redis = new \Redis();
            $type = self::$persistent ? 'pconnect' : 'connect';
            if (!$redis->$type(self::$host, self::$port)) throw new \Exception('Redis: Host or Port ERROR!');
            if ('' !== self::$auth) $redis->auth((string)self::$auth);
            if ('' !== self::$prefix) $redis->setOption($redis::OPT_PREFIX, (string)self::$prefix . ':');
            if (0 < self::$timeout) $redis->setOption($redis::OPT_READ_TIMEOUT, (int)self::$timeout);
            $redis->setOption($redis::OPT_SERIALIZER, $redis::SERIALIZER_NONE);
            $redis->select((int)self::$db);
            unset($type);
        } catch (\Exception $error) {
            exit('Redis: Failed to connect! ' . $error->getMessage());
        }
        return $redis;
    }
}