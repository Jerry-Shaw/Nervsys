<?php

/**
 * Redis Queue Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 *
 * Copyright 2018 Jerry Shaw
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

class redis_queue extends redis
{
    public static $key = [
        'start' => ['cmd']
    ];

    //Main process key
    public static $key_main = 'main';

    //Queue List prefix
    public static $prefix_queue = 'queue:list:';

    //Queue Process prefix
    public static $prefix_process = 'queue:process:';

    //Queue scan wait (in seconds)
    public static $scan_wait = 60;

    //Max running clients
    public static $max_runs = 10;

    //Redis connection
    private static $redis = null;

    /**
     * Initialize
     *
     * @throws \Exception
     */
    public static function init(): void
    {
        self::$redis = parent::connect();
    }

    /**
     * Scan for keys
     *
     * @param string $pattern
     *
     * @return array
     */
    private static function get_keys(string $pattern): array
    {
        $start = 0;
        $list = [];

        do {
            $keys = self::$redis->scan($start, $pattern, self::$max_runs);
            foreach ($keys as $key) $list[] = $key;
        } while (0 < $start);

        unset($pattern, $start, $keys, $key);
        return $list;
    }

    /**
     * Show process list
     *
     * @return array
     */
    public static function process_list(): array
    {
        $list = [];

        $keys = self::get_keys(self::$prefix_process . '*');
        foreach ($keys as $key) $list[$key] = self::$redis->get($key);

        unset($keys, $key);
        return $list;
    }

    /**
     * Show queue list
     *
     * @return array
     */
    public static function queue_list(): array
    {
        $list = [];

        $keys = self::get_keys(self::$prefix_queue . '*');
        foreach ($keys as $key) $list[$key] = self::$redis->llen($key);

        unset($keys, $key);
        return $list;
    }


    public static function start(): void
    {
        //Main process key
        $main_key = self::$prefix_process . self::$key_main;

        //Exit when main process is running
        if (self::$redis->exists($main_key)) exit;

        //Run queue process
        while (true){



        }


    }


}