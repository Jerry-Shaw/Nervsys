<?php

/**
 * Session Module (in Redis)
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

namespace core\ctrl;

use \core\db\redis;

class session
{
    //Redis connection instance
    private static $db_redis;

    //SESSION prefix in Redis
    const prefix = 'sess:';

    //SESSION Lifetime (in seconds)
    const lifetime = 600;

    /**
     * Initialize SESSION
     */
    public static function start()
    {
        if (PHP_SESSION_ACTIVE !== session_status()) {
            redis::$redis_db = 0;
            self::$db_redis = redis::connect();
            ini_set('session.gc_divisor', 100);
            ini_set('session.gc_probability', 100);
            ini_set('session.save_handler', 'user');
            session_set_save_handler(
                [__CLASS__, 'session_open'],
                [__CLASS__, 'session_close'],
                [__CLASS__, 'session_read'],
                [__CLASS__, 'session_write'],
                [__CLASS__, 'session_destroy'],
                [__CLASS__, 'session_gc']
            );
            session_start();
        }
    }

    /**
     * @param string $session_path
     * @param string $session_name
     *
     * @return bool
     */
    public static function session_open(string $session_path, string $session_name): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function session_close(): bool
    {
        return true;
    }

    /**
     * @param string $session_id
     *
     * @return string
     */
    public static function session_read(string $session_id): string
    {
        return (string)self::$db_redis->get(self::prefix . $session_id);
    }

    /**
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool
     */
    public static function session_write(string $session_id, string $session_data): bool
    {
        return self::$db_redis->set(self::prefix . $session_id, $session_data, self::lifetime);
    }

    /**
     * @param string $session_id
     *
     * @return bool
     */
    public static function session_destroy(string $session_id): bool
    {
        self::$db_redis->del(self::prefix . $session_id);
        return true;
    }

    /**
     * @param int $gc_lifetime
     *
     * @return bool
     */
    public static function session_gc(int $gc_lifetime): bool
    {
        return true;
    }
}