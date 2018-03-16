<?php

/**
 * Redis Lock Extension
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

class redis_lock extends redis
{
    //Lock life
    public static $life = 3;

    //Lock prefix
    public static $prefix = 'lock:';

    //Lock list
    private static $lock = [];

    //Redis connection
    private static $redis = null;

    //Retry properties
    const wait  = 1000;
    const retry = 10;

    /**
     * Lock on
     *
     * @param string $key
     *
     * @return bool
     * @throws \Exception
     */
    public static function on(string $key): bool
    {
        //Connect Redis
        if (is_null(self::$redis)) self::$redis = parent::connect();

        //Lock key
        $lock_key = self::$prefix . $key;

        //Set lock
        if (self::lock($lock_key)) {
            register_shutdown_function([__CLASS__, 'clear']);
            unset($key, $lock_key);
            return true;
        }

        //Wait lock
        $retry = 0;

        while ($retry <= self::retry) {
            ++$retry;
            usleep(self::wait);

            //Reset lock
            if (self::lock($lock_key)) {
                register_shutdown_function([__CLASS__, 'clear']);
                unset($key, $lock_key, $retry);
                return true;
            }
        }

        unset($key, $lock_key, $retry);
        return false;
    }

    /**
     * Lock off
     *
     * @param string $key
     *
     * @throws \Exception
     */
    public static function off(string $key): void
    {
        //Connect Redis
        if (is_null(self::$redis)) self::$redis = parent::connect();

        //Lock key
        $lock_key = self::$prefix . $key;

        //Delete lock
        self::$redis->del($lock_key);

        //Delete key
        $key = array_search($lock_key, self::$lock, true);
        if (false !== $key) unset(self::$lock[$key]);

        unset($key, $lock_key);
    }

    /**
     * Set lock
     *
     * @param string $key
     *
     * @return bool
     */
    private static function lock(string $key): bool
    {
        if (!self::$redis->setnx($key, time())) return false;

        self::$redis->expire($key, self::$life);
        self::$lock[] = &$key;

        unset($key);
        return true;
    }

    /**
     * Clear all locks
     */
    private static function clear(): void
    {
        if (empty(self::$lock)) return;

        //Delete locks
        call_user_func_array([self::$redis, 'del'], self::$lock);

        //Clear keys
        self::$lock = [];
    }
}