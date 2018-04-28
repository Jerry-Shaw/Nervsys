<?php

/**
 * Redis Lock Extension
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

class redis_lock extends redis
{
    //Lock prefix
    public static $prefix = 'lock:';

    //Lock list
    private static $lock = [];

    //Retry properties
    const WAIT  = 1000;
    const RETRY = 10;

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
        //Lock key
        $lock_key = self::$prefix . $key;

        //Set lock
        if (self::lock($lock_key)) {
            register_shutdown_function([__CLASS__, 'clear']);
            unset($key, $lock_key);
            return true;
        }

        $retry = 0;

        while ($retry <= self::RETRY) {
            ++$retry;
            usleep(self::WAIT);

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
        //Lock key
        $lock_key = self::$prefix . $key;

        //Delete lock
        self::connect()->del($lock_key);

        //Delete key
        $key = array_search($lock_key, self::$lock, true);
        if (false !== $key) unset(self::$lock[$key]);

        unset($key, $lock_key);
    }

    /**
     * Set lock
     *
     * @param string $key
     * @param int    $life (in seconds)
     *
     * @return bool
     * @throws \Exception
     */
    private static function lock(string $key, int $life = 3): bool
    {
        if (!self::connect()->setnx($key, time())) return false;

        self::connect()->expire($key, $life);
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
        call_user_func_array([self::connect(), 'del'], self::$lock);

        //Clear keys
        self::$lock = [];
    }
}