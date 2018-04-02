<?php

/**
 * Redis Session Extension
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

class redis_session extends redis
{
    //SESSION Prefix (After "parent::$prefix")
    public static $prefix_key = 'sess:';

    //SESSION Lifetime (in seconds)
    public static $lifetime = 600;

    /**
     * Initialize SESSION
     */
    public static function start(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) return;

        //Setup Session GC config
        ini_set('session.gc_divisor', 100);
        ini_set('session.gc_probability', 100);

        //Set Session handler & start Session
        session_set_save_handler(
            [__CLASS__, 'session_open'],
            [__CLASS__, 'session_close'],
            [__CLASS__, 'session_read'],
            [__CLASS__, 'session_write'],
            [__CLASS__, 'session_destroy'],
            [__CLASS__, 'session_gc']
        );

        //Start SESSION
        register_shutdown_function('session_write_close');
        session_start();
    }

    /**
     * @param string $save_path
     * @param string $session_name
     *
     * @return bool
     */
    public static function session_open(string $save_path, string $session_name): bool
    {
        unset($save_path, $session_name);
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
     * @throws \Exception
     */
    public static function session_read(string $session_id): string
    {
        return (string)self::connect()->get(self::$prefix_key . $session_id);
    }

    /**
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool
     * @throws \Exception
     */
    public static function session_write(string $session_id, string $session_data): bool
    {
        $write = self::connect()->set(self::$prefix_key . $session_id, $session_data, self::$lifetime);

        unset($session_id, $session_data);
        return (bool)$write;
    }

    /**
     * @param string $session_id
     *
     * @return bool
     * @throws \Exception
     */
    public static function session_destroy(string $session_id): bool
    {
        self::connect()->del(self::$prefix_key . $session_id);

        unset($session_id);
        return true;
    }

    /**
     * @param int $lifetime
     *
     * @return bool
     */
    public static function session_gc(int $lifetime): bool
    {
        unset($lifetime);
        return true;
    }
}