<?php

/**
 * Redis Queue Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
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

use core\ctr\os;

class redis_queue extends redis
{
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

    //OS Variables
    private static $os_env = [];

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
     * Caution: Do NOT expose "add" to API Safe Key directly
     *
     * @param string $key
     * @param array  $data
     *
     * @return int
     * @throws \Exception
     */
    public static function add(string $key, array $data): int
    {
        if (!isset($data['c']) && !isset($data['cmd'])) return 0;

        self::$redis = parent::connect();
        $add = (int)self::$redis->lPush(self::$prefix_queue . $key, json_encode($data));

        unset($key, $data);
        return $add;
    }

    /**
     * Stop queue
     * Caution: Do NOT expose "add" to API Safe Key directly
     *
     * @param string $key
     *
     * @return int
     * @throws \Exception
     */
    public static function stop(string $key): int
    {
        self::$redis = parent::connect();
        return 'all' === $key ? call_user_func_array([self::$redis, 'del'], array_keys(self::process_list())) : self::$redis->del(self::$prefix_process . $key);
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
        foreach ($keys as $key) $list[$key] = self::$redis->lLen($key);

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

        //Get OS Variables
        self::$os_env = os::get_env();

        //Connect Redis
        self::$redis = parent::connect();

        //Main process key
        $main_key = self::$prefix_process . self::$key_main;

        //Exit when main process is running
        if (self::$redis->exists($main_key)) exit;

        //Set lifetime
        $time_wait = (int)(self::$scan_wait / 2);
        self::$redis->set($main_key, time(), self::$scan_wait);

        //Operations
        $opts = 0;

        //Start
        do {
            //Read list
            $list = self::queue_list();

            //Listen
            $queue = self::$redis->brPop(array_keys($list), $time_wait);

            //Process
            if (is_array($queue)) {
                ++$opts;
                //Execute
                self::exec_queue($queue[1]);
            } else $opts = 0;

            //Too many jobs
            if (0 < $opts && 0 === $opts % self::$max_opts) self::call_process($list);

            //Renew main process
            $renew = self::$redis->expire($main_key, self::$scan_wait);
        } while ($renew);

        //On exit
        $process = self::process_list();
        call_user_func_array([self::$redis, 'del'], array_keys($process));
        unset($main_key, $time_wait, $opts, $list, $queue, $renew, $process);
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

        //Get OS Variables
        self::$os_env = os::get_env();

        //Connect Redis
        self::$redis = parent::connect();

        //Process key
        $process_key = self::$prefix_process . hash('md5', uniqid(mt_rand(), true));

        //Set timeout & lifetime
        $time_wait = (int)(self::$scan_wait / 2);
        self::$redis->set($process_key, 0, self::$scan_wait);

        //Get queue list keys
        $list = array_keys(self::queue_list());

        //Run
        do {
            //Listen
            $queue = self::$redis->brPop($list, $time_wait);

            //Execute in mirror process
            if (is_array($queue)) self::exec_queue($queue[1]);

            //Check status & renew process
            $exist = self::$redis->exists($process_key);
            $renew = self::$redis->expire($process_key, self::$scan_wait);
            $opts = $exist ? self::$redis->incr($process_key) : self::$max_opts;
        } while ($exist && $renew && $opts < self::$max_opts);

        //On exit
        self::$redis->del($process_key);
        unset($process_key, $time_wait, $list, $queue, $exist, $renew, $opts);
    }

    /**
     * Call processes
     *
     * @param array $queue
     */
    private static function call_process(array $queue): void
    {
        //Count jobs
        $jobs = array_sum($queue);

        //Read process list
        $process = self::process_list();

        //Count needed clients
        $needed = (int)(ceil($jobs / self::$max_opts) - count($process) - 1);
        if ($needed > self::$max_runs) $needed = self::$max_runs;

        //Run child processes
        for ($i = 0; $i < $needed; ++$i) pclose(popen(self::$os_env['PHP_EXE'] . ' ' . ROOT . '/api.php --cmd "' . str_replace('\\', '/', __CLASS__) . '-run"', 'r'));

        unset($queue, $jobs, $process, $needed, $i);
    }

    /**
     * Execute queue
     *
     * @param string $data
     */
    private static function exec_queue(string $data): void
    {
        //Execute
        exec(self::$os_env['PHP_EXE'] . ' ' . ROOT . '/api.php --record "result" --data "' . addcslashes($data, '"') . '"', $output);

        //Check
        self::chk_queue($data, implode(PHP_EOL, $output));

        //Reset
        $output = null;
        unset($data, $output);
    }

    /**
     * Check returned data
     * Only empty & true are considerable
     *
     * @param string $data
     * @param string $result
     */
    private static function chk_queue(string $data, string $result): void
    {
        //Decode in JSON
        $json = json_decode($result, true);

        //Considerations
        if (!is_null($json) && (empty($json) || true === current($json))) return;

        //Save fail list
        self::$redis->lPush(self::$fail_list, json_encode(['data' => &$data, 'return' => &$result]));
        unset($data, $result, $json);
    }
}