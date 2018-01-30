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

use core\ctr\os;

class redis_queue extends redis
{
    public static $key = [
        'start' => [],
        'run'   => []
    ];

    //Main process key
    public static $key_main = 'main';

    //Queue failed key
    public static $fail_list = 'queue:fail';

    //Queue list prefix
    public static $prefix_queue = 'queue:list:';

    //Queue process prefix
    public static $prefix_process = 'queue:process:';

    //Queue scan wait
    public static $scan_wait = 60;

    //Max running clients
    public static $max_runs = 10;

    //Max operations
    public static $max_opts = 200;

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
        $list = [];
        $start = null;

        do {
            $keys = self::$redis->scan($start, $pattern, self::$max_runs);
            if (false !== $keys) foreach ($keys as $key) $list[] = $key;
        } while (0 < $start);

        unset($pattern, $start, $keys, $key);
        return $list;
    }

    /**
     * Add queue
     * Caution: Do NOT expose "add" to API Safe Key
     *
     * @param string $key
     * @param array  $data
     *
     * @return bool
     * @throws \Exception
     */
    public static function add(string $key, array $data): bool
    {
        if (!isset($data['c']) && !isset($data['cmd'])) return false;

        self::init();
        $add = self::$redis->lpush(self::$prefix_queue . $key, json_encode($data));

        unset($key, $data);
        return $add;
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

    /**
     * Start main process
     *
     * @throws \Exception
     */
    public static function start(): void
    {
        //Only support CLI
        if ('cli' !== PHP_SAPI) exit;

        //Main process key
        $main_key = self::$prefix_process . self::$key_main;

        //Exit when main process is running
        if (self::$redis->exists($main_key)) exit;

        //Set lifetime
        $time_wait = (int)(self::$scan_wait / 2);
        self::$redis->set($main_key, time(), self::$scan_wait);

        //Get OS Variables
        $os_env = os::get_env();

        //Start main process
        do {
            //Count queue jobs
            $queue_jobs = array_sum(self::queue_list());

            //Read process list
            $process = self::process_list();
            if (isset($process[$main_key])) unset($process[$main_key]);

            //Count needed clients
            $need_more = (int)(ceil($queue_jobs / self::$max_opts) - count($process));

            //Run child processes
            for ($i = 0; $i < $need_more; ++$i) pclose(popen($os_env['PHP_EXE'] . ' ' . ROOT . '/api.php --cmd "' . str_replace('\\', '/', __CLASS__) . '-run"', 'r'));

            //Renew main process
            $renew = self::$redis->expire($main_key, self::$scan_wait);

            //Sleep
            sleep($time_wait);
        } while ($renew);

        //On exit
        $process = self::process_list();
        call_user_func_array([self::$redis, 'del'], array_keys($process));
        unset($main_key, $time_wait, $os_env, $queue_jobs, $process, $need_more, $i, $renew);
    }

    /**
     * Run queue process
     *
     * @throws \Exception
     */
    public static function run(): void
    {
        //Only support CLI
        if ('cli' !== PHP_SAPI) exit;

        //Process key
        $process_key = self::$prefix_process . hash('md5', uniqid(mt_rand(), true));

        //Set timeout & lifetime
        $time_wait = (int)(self::$scan_wait / 2);
        self::$redis->set($process_key, 0, self::$scan_wait);

        //Get queue list keys
        $list = array_keys(self::queue_list());

        //Get OS Variables
        $os_env = os::get_env();

        //Run
        do {
            //Listen
            $queue = self::$redis->brPop($list, $time_wait);

            //Run mirror process
            if (is_array($queue)) {
                exec($os_env['PHP_EXE'] . ' ' . ROOT . '/api.php --record result --data "' . addcslashes($queue[1], '"') . '"', $output);
                //Check returned data
                self::chk_return($queue[1], implode(PHP_EOL, $output));

                //Reset output
                $output = null;
                unset($output);
            }

            //Check status & renew process
            $exist = self::$redis->exists($process_key);
            $renew = self::$redis->expire($process_key, self::$scan_wait);
            $opts = $exist ? self::$redis->incr($process_key) : self::$max_opts;
        } while ($exist && $renew && $opts < self::$max_opts);

        //On exit
        self::$redis->del($process_key);
        unset($process_key, $time_wait, $list, $os_env, $queue, $exist, $renew, $opts);
    }

    /**
     * Check returned data
     * Only empty & true are considerable
     *
     * @param string $data
     * @param string $result
     */
    private static function chk_return(string $data, string $result): void
    {
        //Decode in JSON
        $json = json_decode($result, true);
        if (!is_null($json) && (empty($json) || true === current($json))) return;

        //Save fail list
        self::$redis->lpush(self::$fail_list, json_encode(['data' => &$data, 'return' => &$result]));
        unset($data, $result, $json);
    }
}