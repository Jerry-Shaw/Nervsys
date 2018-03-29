<?php

/**
 * Redis Session Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017 秋水之冰 <27206617@qq.com>
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

    //Redis connection
    private static $redis = null;

    /**
     * Initialize SESSION
     */
    public static function start(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) return;
        //Connect Redis
        if (is_null(self::$redis)) self::$redis = self::connect();

        //Setup Session GC config
        ini_set('session.gc_divisor', 100);
        ini_set('session.gc_probability', 100);

        //Set Session handler & start Session
        $handler = __CLASS__;
        session_set_save_handler(
            [$handler, 'open'],
            [$handler, 'close'],
            [$handler, 'read'],
            [$handler, 'write'],
            [$handler, 'destroy'],
            [$handler, 'gc']
        );

        register_shutdown_function('session_write_close');
        session_start();
        unset($handler);
    }

    /**
     * @param string $save_path
     * @param string $session_name
     *
     * @return bool
     */
    public static function open(string $save_path, string $session_name): bool
    {
        unset($save_path, $session_name);
        return true;
    }

    /**
     * @return bool
     */
    public static function close(): bool
    {
        return true;
    }

    /**
     * @param string $session_id
     *
     * @return string
     */
    public static function read(string $session_id): string
    {
        return (string)self::$redis->get(self::$prefix_key . $session_id);
    }

    /**
     * @param string $session_id
     * @param string $session_data
     *
     * @return bool
     */
    public static function write(string $session_id, string $session_data): bool
    {
        $write = self::$redis->set(self::$prefix_key . $session_id, $session_data, self::$lifetime);
        unset($session_id, $session_data);
        return (bool)$write;
    }

    /**
     * @param string $session_id
     *
     * @return bool
     */
    public static function destroy(string $session_id): bool
    {
        self::$redis->del(self::$prefix_key . $session_id);
        unset($session_id);
        return true;
    }

    /**
     * @param int $lifetime
     *
     * @return bool
     */
    public static function gc(int $lifetime): bool
    {
        unset($lifetime);
        return true;
    }
}