<?php

/**
 * Redis Queue Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
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

use core\handler\platform;

use core\pool\config;

class redis_queue extends redis
{
    /**
     * API Command
     *
     * Examples:
     * "example/queue-process", etc... (Controlled by API & TrustZone)
     * "program" or "exe", etc... (Controlled by API & conf.ini)
     *
     * Notice:
     * When set cmd value, make sure it can be fully controlled by API.
     * Otherwise, child processes won't start. Only main runs.
     * It is very useful facing huge queue list.
     *
     * Recommended:
     * Method "run" is fully ready for use, but is kept away from directly calling.
     * TrustZone is closed in extensions, you can always call it from your own codes.
     */
    public static $cmd = '';

    //Queue failed key (list)
    public static $key_fail = 'queue:fail';

    //Queue list prefix (list)
    public static $prefix_list = 'queue:list:';

    //Queue process prefix (kv)
    public static $prefix_process = 'queue:process:';

    //Queue watch keys (hash)
    public static $key_watch_list    = 'queue:watch:list';
    public static $key_watch_process = 'queue:watch:process';

    //Queue scan wait (in seconds)
    public static $scan_wait = 60;

    //Process idle wait (in seconds)
    public static $idle_wait = 3;

    //Max child processes
    public static $max_runs = 5;

    //Max executed counts
    public static $max_execute = 200;

    /**
     * Get active keys
     *
     * @param string $key
     *
     * @return array
     * @throws \Exception
     */
    private static function get_keys(string $key): array
    {
        if (!self::connect()->exists($key)) {
            return [];
        }

        $keys = self::connect()->hGetAll($key);

        if (empty($keys)) {
            return [];
        }

        foreach ($keys as $k => $v) {
            if (!self::connect()->exists($k)) {
                self::connect()->hDel($key, $k);
                unset($keys[$k]);
            }
        }

        unset($key, $k, $v);
        return $keys;
    }

    /**
     * Add queue
     * Caution: Do NOT expose "add" to API TrustZone directly
     *
     * @param string $key
     * @param array  $data
     *
     * @return int
     * @throws \Exception
     */
    public static function add(string $key, array $data): int
    {
        //Command should always exist
        if (!isset($data['c']) && !isset($data['cmd'])) {
            return 0;
        }

        //Queue list key
        $list_key = self::$prefix_list . $key;

        //Add to watch list & queue list
        self::connect()->hSet(self::$key_watch_list, $list_key, time());
        $add = (int)self::connect()->lPush($list_key, json_encode($data));

        unset($key, $data, $list_key);
        return $add;
    }

    /**
     * Stop queue
     * Caution: Do NOT expose "stop" to API TrustZone directly
     *
     * @param string $key
     *
     * @return int
     * @throws \Exception
     */
    public static function stop(string $key = ''): int
    {
        $process = '' === $key ? array_keys(self::show_process()) : [self::$prefix_process . $key];

        if (empty($process)) {
            return 0;
        }

        $result = call_user_func_array([self::connect(), 'del'], $process);

        array_unshift($process, self::$key_watch_process);
        call_user_func_array([self::connect(), 'hDel'], $process);

        unset($key, $process);
        return $result;
    }

    /**
     * Show process list
     *
     * @return array
     * @throws \Exception
     */
    public static function show_process(): array
    {
        return self::get_keys(self::$key_watch_process);
    }

    /**
     * Show queue list
     *
     * @return array
     * @throws \Exception
     */
    public static function show_queue(): array
    {
        return self::get_keys(self::$key_watch_list);
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
    public static function show_fail(int $start = 0, int $end = -1): array
    {
        $list = [];

        $list['len'] = self::connect()->lLen(self::$key_fail);
        $list['data'] = self::connect()->lRange(self::$key_fail, $start, $end);

        unset($start, $end);
        return $list;
    }

    /**
     * Start root process
     *
     * @throws \Exception
     */
    public static function start(): void
    {
        //Only support CLI
        if (config::$IS_CGI) {
            exit;
        }

        //Root process key
        $root_key = self::$prefix_process . 'root';

        //Exit when root process is running
        if (self::connect()->exists($root_key)) {
            exit;
        }

        //Set lifetime
        $time_wait = (int)(self::$scan_wait / 2);
        self::connect()->set($root_key, time(), self::$scan_wait);

        //Add to watch list
        self::connect()->hSet(self::$key_watch_process, $root_key, time());

        //Operation counter
        $counter = 0;

        //Start
        do {
            //Read list
            $list = self::show_queue();

            //Idle wait on no job
            if (empty($list)) {
                //Renew root process
                $renew = self::connect()->expire($root_key, self::$scan_wait);

                //Sleep for Idle
                sleep(self::$idle_wait);

                //Reset operation counter
                $counter = 0;
                continue;
            }

            //Listen
            $queue = self::connect()->brPop(array_keys($list), $time_wait);

            //Process
            if (is_array($queue)) {
                ++$counter;
                self::exec_queue($queue[1]);
                self::connect()->lRem($queue[0], $queue[1]);

                //Call child processes
                if (0 === $counter % self::$max_execute && '' !== self::$cmd) {
                    self::call_process();
                }
            } else {
                $counter = 0;
            }

            //Renew root process
            $renew = self::connect()->expire($root_key, self::$scan_wait);
        } while ($renew);

        //On exit
        self::stop();

        unset($root_key, $time_wait, $counter, $list, $queue, $renew);
    }

    /**
     * Run queue process
     *
     * @throws \Exception
     */
    public static function run(): void
    {
        //Only support CLI
        if (config::$IS_CGI) {
            exit;
        }

        //Process Hash & Key
        $process_hash = hash('md5', uniqid(mt_rand(), true));
        $process_key = self::$prefix_process . $process_hash;

        //Set timeout & lifetime
        $time_wait = (int)(self::$scan_wait / 2);
        self::connect()->set($process_key, 0, self::$scan_wait);

        //Add to watch list
        self::connect()->hSet(self::$key_watch_process, $process_key, time());

        //Run
        do {
            //Get queue list
            $list = self::show_queue();

            //Exit on no job
            if (empty($list)) {
                break;
            }

            //Listen
            $queue = self::connect()->brPop(array_keys($list), $time_wait);

            //Execute in mirror process
            if (is_array($queue)) {
                self::exec_queue($queue[1]);
                self::connect()->lRem($queue[0], $queue[1]);
            }

            //Check status & renew process
            $exist = self::connect()->exists($process_key);
            $renew = self::connect()->expire($process_key, self::$scan_wait);
            $executed = $exist ? self::connect()->incr($process_key) : self::$max_execute;
        } while ($exist && $renew && $executed < self::$max_execute);

        //On exit
        self::stop($process_hash);

        unset($process_hash, $process_key, $time_wait, $list, $queue, $exist, $renew, $executed);
    }

    /**
     * Call processes
     *
     * @throws \Exception
     */
    private static function call_process(): void
    {
        //Count running processes
        $running = count(self::show_process());

        $left = self::$max_runs - $running;

        if (0 >= $left) {
            return;
        }

        //Read queue list
        $queue = self::show_queue();

        //Count jobs
        $jobs = 0;
        foreach ($queue as $key => $value) {
            $jobs += self::connect()->lLen($key);
        }

        if (0 === $jobs) {
            return;
        }

        //Count need processes
        $need = (int)(ceil($jobs / self::$max_execute) - $running);

        if ($need > $left) {
            $need = &$left;
        }

        //Build command
        $cmd = platform::cmd_bg(platform::sys_path() . ' ' . ROOT . DIRECTORY_SEPARATOR . 'api.php --cmd "' . self::$cmd . '"');

        //Run child processes
        for ($i = 0; $i < $need; ++$i) {
            pclose(popen($cmd, 'r'));
        }

        unset($running, $left, $queue, $jobs, $key, $value, $need, $cmd, $i);
    }

    /**
     * Execute queue
     *
     * @param string $data
     *
     * @throws \Exception
     */
    private static function exec_queue(string $data): void
    {
        //Execute
        exec(platform::sys_path() . ' ' . ROOT . DIRECTORY_SEPARATOR . 'api.php --ret --data "' . addcslashes($data, '"') . '"', $output);

        //Collect
        foreach ($output as $key => $value) {
            $output[$key] = trim($value);
        }

        //Check
        self::chk_queue($data, implode($output));

        unset($data, $output, $key, $value);
    }

    /**
     * Check returned data
     * Only empty & true are considerable
     *
     * @param string $data
     * @param string $result
     *
     * @throws \Exception
     */
    private static function chk_queue(string $data, string $result): void
    {
        //Accept empty & true
        if ('' === $result || true === json_decode($result, true)) {
            return;
        }

        //Save fail list
        self::connect()->lPush(self::$key_fail, json_encode(['data' => &$data, 'return' => &$result]));

        unset($data, $result);
    }
}