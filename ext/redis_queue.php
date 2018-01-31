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
    /**
     * API Command
     *
     * Examples:
     * "example/queue-process", etc... (Controlled by API & Safe Key)
     * "program" or "exe", etc... (Controlled by API & cfg.ini)
     *
     * Notice:
     * When set cmd value, make sure it can be fully controlled by API.
     * Otherwise, child processes won't start. Only main runs.
     * It is very useful facing huge queue list.
     *
     * Recommended:
     * Method "run" is ready for use, but is kept away from directly calling.
     * No Safe Key in extensions, you can always call it from your own codes.
     *
     * @var string
     */
    public static $cmd = '';

    //Main process key
    public static $key_main = 'main';

    //Queue failed key
    public static $fail_list = 'queue:fail';

    //Queue list prefix
    public static $prefix_queue = 'queue:list:';

    //Queue process prefix
    public static $prefix_process = 'queue:process:';

    //Queue scan wait (in seconds)
    public static $scan_wait = 60;

    //Process idle wait (in seconds)
    public static $idle_wait = 3;

    //Max running clients
    public static $max_runs = 5;

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
        $start = $offset = null;

        do {
            $offset = $start;
            $keys = self::$redis->scan($start, $pattern, self::$max_runs);
            if (false !== $keys) foreach ($keys as $key) $list[] = $key;
        } while (0 < $start && $offset !== $start);

        unset($pattern, $start, $offset, $keys, $key);
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
    public static function stop(string $key = ''): int
    {
        self::$redis = parent::connect();

        $process = '' === $key ? array_keys(self::process_list()) : [self::$prefix_process . $key];
        $result = 0 < count($process) ? call_user_func_array([self::$redis, 'del'], $process) : 0;

        unset($key, $process);
        return $result;
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
     * Show fail list
     *
     * @param int $start
     * @param int $end
     *
     * @return array
     * @throws \Exception
     */
    public static function fail_list(int $start = 0, int $end = -1): array
    {
        $list = [];

        self::$redis = parent::connect();

        $list['len'] = self::$redis->lLen(self::$fail_list);
        $list['data'] = self::$redis->lRange(self::$fail_list, $start, $end);

        unset($start, $end);
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
        $operations = 0;

        //Start
        do {
            //Read list
            $list = self::queue_list();

            //Idle wait on no job
            if (empty($list)) {
                //Renew main process
                $renew = self::$redis->expire($main_key, self::$scan_wait);

                //Sleep for Idle
                sleep(self::$idle_wait);

                //Reset operations
                $operations = 0;
                continue;
            }

            //Listen
            $queue = self::$redis->brPop(array_keys($list), $time_wait);

            //Process
            if (is_array($queue)) {
                ++$operations;
                self::exec_queue($queue[1]);
            } else $operations = 0;

            //Too many jobs
            if (0 < $operations && 0 === $operations % self::$max_opts && '' !== self::$cmd) self::call_process($list);

            //Renew main process
            $renew = self::$redis->expire($main_key, self::$scan_wait);
        } while ($renew);

        //On exit
        self::stop();
        unset($main_key, $time_wait, $operations, $list, $queue, $renew);
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

        //Process Hash & Key
        $process_hash = hash('md5', uniqid(mt_rand(), true));
        $process_key = self::$prefix_process . $process_hash;

        //Set timeout & lifetime
        $time_wait = (int)(self::$scan_wait / 2);
        self::$redis->set($process_key, 0, self::$scan_wait);

        //Run
        do {
            //Get queue list keys
            $list = array_keys(self::queue_list());

            //Exit on no job
            if (empty($list)) break;

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
        self::stop($process_hash);
        unset($process_hash, $process_key, $time_wait, $list, $queue, $exist, $renew, $opts);
    }

    /**
     * Call processes
     *
     * @param array $queue
     *
     * @throws \Exception
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

        //Build command
        $cmd = os::in_bg(self::$os_env['PHP_EXE'] . ' ' . ROOT . '/api.php --cmd "' . self::$cmd . '"');

        //Run child processes
        for ($i = 0; $i < $needed; ++$i) pclose(popen($cmd, 'r'));

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
        foreach ($output as $key => $value) $output[$key] = trim($value);
        self::chk_queue($data, implode(PHP_EOL, $output));

        unset($data, $output, $key, $value);
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