<?php

/**
 * Redis Queue Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

use core\parser\data;

use core\handler\operator;
use core\handler\platform;

class redis_queue extends redis
{
    //Expose "root" & "child"
    public static $tz = [
        'root'  => [],
        'child' => []
    ];

    //Process properties
    private $runs  = 10;
    private $exec  = 200;
    private $child = '';

    //Wait properties
    const WAIT_IDLE = 3;
    const WAIT_SCAN = 60;

    //Queue keys
    const KEY_FAILED       = 'RQ:fail';
    const KEY_WATCH_LIST   = 'RQ:watch:list';
    const KEY_WATCH_WORKER = 'RQ:watch:worker';

    //Queue key prefix
    const PREFIX_CMD    = 'RQ:cmd:';
    const PREFIX_LIST   = 'RQ:list:';
    const PREFIX_WORKER = 'RQ:worker:';

    /**
     * Add job
     * Caution: Do NOT expose "add" to TrustZone directly
     *
     * @param string $cmd
     * @param array  $data
     * @param string $group
     * @param int    $duration
     *
     * @return int
     * @throws \RedisException
     */
    public function add(string $cmd, array $data = [], string $group = '', int $duration = 0): int
    {
        //Add command
        $data['cmd'] = &$cmd;

        //Build connection
        $redis = parent::connect();

        //Check duration
        if (0 < $duration) {
            //Check job duration
            if (!$redis->setnx($cmd_key = self::PREFIX_CMD . hash('crc32b', $cmd), '')) {
                return 0;
            }

            //Set duration life
            $redis->expire($cmd_key, $duration);
            unset($cmd_key);
        }

        //Build group key & queue data
        $list  = self::PREFIX_LIST . ('' === $group ? 'main' : $group);
        $queue = json_encode($data);

        //Add watch list & queue list
        $redis->hSet(self::KEY_WATCH_LIST, $list, time());
        $result = 0 < $redis->lRem($list, $queue) ? $redis->rPush($list, $queue) : $redis->lPush($list, $queue);

        unset($cmd, $data, $group, $duration, $redis, $list, $queue);
        return (int)$result;
    }

    /**
     * Close process
     * Caution: Do NOT expose "close" to TrustZone directly
     *
     * @param string $key
     *
     * @return int
     * @throws \RedisException
     */
    public function close(string $key = ''): int
    {
        //Build connection
        $redis = parent::connect();

        //Get process list
        $process = '' === $key ? array_keys($this->show_process()) : [self::PREFIX_WORKER . $key];

        if (empty($process)) {
            return 0;
        }

        $result = call_user_func_array([$redis, 'del'], $process);

        array_unshift($process, self::KEY_WATCH_WORKER);
        call_user_func_array([$redis, 'hDel'], $process);

        unset($key, $redis, $process);
        return $result;
    }

    /**
     * Show fail list
     *
     * @param int $start
     * @param int $end
     *
     * @return array
     * @throws \RedisException
     */
    public function show_fail(int $start = 0, int $end = -1): array
    {
        $list = [];

        //Build connection
        $redis = parent::connect();

        //Read failed list
        $list['len']  = $redis->lLen(self::KEY_FAILED);
        $list['data'] = $redis->lRange(self::KEY_FAILED, $start, $end);

        unset($start, $end, $redis);
        return $list;
    }

    /**
     * Show queue list
     *
     * @return array
     * @throws \RedisException
     */
    public function show_queue(): array
    {
        return $this->get_keys(self::KEY_WATCH_LIST);
    }

    /**
     * Show process list
     *
     * @return array
     * @throws \RedisException
     */
    public function show_process(): array
    {
        return $this->get_keys(self::KEY_WATCH_WORKER);
    }

    /**
     * Start root process
     *
     * @param int $runs
     * @param int $exec
     *
     * @throws \RedisException
     */
    public function root(int $runs = 10, int $exec = 200): void
    {
        //Detect running mode
        if (!parent::$is_cli) {
            throw new \Exception('Redis queue only supports CLI!', E_USER_ERROR);
        }

        //Build connection
        $redis = parent::connect();

        //Build root process key
        $root_key = self::PREFIX_WORKER . 'root';

        //Exit when root process is running
        if (0 < $redis->exists($root_key)) {
            exit;
        }

        if (0 < $runs) {
            $this->runs = &$runs;
        }

        if (0 < $exec) {
            $this->exec = &$exec;
        }

        unset($runs, $exec);

        //Set process life
        $wait_time = (int)(self::WAIT_SCAN / 2);
        $root_hash = hash('md5', uniqid(mt_rand(), true));

        $redis->set($root_key, $root_hash, self::WAIT_SCAN);

        //Add to watch list
        $redis->hSet(self::KEY_WATCH_WORKER, $root_key, time());

        //Close on shutdown
        register_shutdown_function([$this, 'close']);

        //Build child command
        $this->child = platform::cmd_bg(
            platform::sys_path() . ' '
            . ROOT . 'api.php --ret --cmd "'
            . strtr(__CLASS__, '\\', '/') . '-child"'
        );

        do {
            //Get process status
            $valid   = $redis->get($root_key) === $root_hash;
            $running = $redis->expire($root_key, self::WAIT_SCAN);

            //Idle wait on no job or child process running
            if (empty($list = $this->show_queue()) || 1 < count($this->show_process())) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Idle wait on no job
            if (empty($queue = $redis->brPop(array_keys($list), $wait_time))) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Re-add queue job
            $redis->rPush($queue[0], $queue[1]);
            //Call child process
            $this->call_child();
        } while ($valid && $running);

        //On exit
        self::close();

        unset($redis, $root_key, $wait_time, $root_hash, $valid, $running, $list, $queue);
    }

    /**
     * Start child process
     *
     * @throws \RedisException
     */
    public function child(): void
    {
        //Detect running mode
        if (!parent::$is_cli) {
            throw new \Exception('Redis queue only supports CLI!', E_USER_ERROR);
        }

        //Build connection
        $redis = parent::connect();

        //Build Hash & Key
        $child_hash = hash('md5', uniqid(mt_rand(), true));
        $child_key  = self::PREFIX_WORKER . $child_hash;

        //Set process life
        $wait_time = (int)(self::WAIT_SCAN / 2);
        $redis->set($child_key, '', self::WAIT_SCAN);

        //Add to watch list
        $redis->hSet(self::KEY_WATCH_WORKER, $child_key, time());

        //Close on exit
        register_shutdown_function([$this, 'close'], $child_hash);

        $execute = 0;

        do {
            //Exit on no job
            if (empty($list = $this->show_queue())) {
                break;
            }

            //Execute job
            if (!empty($queue = $redis->brPop(array_keys($list), $wait_time))) {
                self::exec_job($queue[1]);
            }
        } while (0 < $redis->exists($child_key) && $redis->expire($child_key, self::WAIT_SCAN) && ++$execute < $this->exec);

        //On exit
        self::stop($child_hash);

        unset($redis, $child_hash, $child_key, $wait_time, $execute, $list, $queue);
    }

    /**
     * Get active keys
     *
     * @param string $key
     *
     * @return array
     * @throws \RedisException
     */
    private function get_keys(string $key): array
    {
        $redis = parent::connect();

        if (0 === $redis->exists($key)) {
            return [];
        }

        if (empty($keys = $redis->hGetAll($key))) {
            return [];
        }

        foreach ($keys as $k => $v) {
            if (0 === $redis->exists($k)) {
                $redis->hDel($key, $k);
                unset($keys[$k]);
            }
        }

        unset($key, $k, $v);
        return $keys;
    }

    /**
     * Execute job
     *
     * @param string $data
     *
     * @throws \RedisException
     */
    private function exec_job(string $data): void
    {
        //Build connection
        $redis = parent::connect();

        //Decode data in JSON
        if (!is_array($input = json_decode($data, true))) {
            $redis->lPush(
                self::KEY_FAILED,
                json_encode(['data' => &$data, 'return' => 'Data ERROR!'])
            );

            return;
        }

        //Check command
        if (false === strpos($input['cmd'], '-')) {
            $redis->lPush(
                self::KEY_FAILED,
                json_encode(['data' => &$data, 'return' => 'Command [' . $input['cmd'] . '] ERROR!'])
            );

            return;
        }

        //Get order & class & method
        list($order, $method) = explode('-', $input['cmd'], 2);

        //Call LOAD commands
        if (isset(parent::$load[$module = strstr($order, '/', true)])) {
            operator::init_load(is_string(parent::$load[$module]) ? [parent::$load[$module]] : parent::$load[$module]);
        }

        //Merge methods
        $class   = parent::build_name($order);
        $methods = method_exists($class, 'init') ? ['init', $method] : [$method];

        foreach ($methods as $method) {
            try {
                //Reflect method
                $reflect = new \ReflectionMethod($class, $method);

                //Not public
                if (!$reflect->isPublic()) {
                    throw new \Exception(ltrim($class, '\\') . '=>' . $method . ': NOT for public!', E_USER_WARNING);
                }

                //Get factory object
                if (!$reflect->isStatic()) {
                    $class = method_exists($class, '__construct')
                        ? parent::obtain($class, data::build_argv(new \ReflectionMethod($class, '__construct'), $input))
                        : parent::obtain($class);
                }

                //Call method (with params)
                $result = !empty($params = data::build_argv($reflect, $input))
                    ? forward_static_call_array([$class, $method], $params)
                    : forward_static_call([$class, $method]);

                //Check result
                self::check_job($data, json_encode($result));
            } catch (\Throwable $throwable) {
                $redis->lPush(self::KEY_FAILED, json_encode(['data' => &$data, 'return' => $throwable->getMessage()]));
                unset($throwable);
                return;
            }
        }

        unset($data, $redis, $input, $order, $method, $module, $class, $methods, $reflect, $params, $result);
    }

    /**
     * Check job
     * Only accept null & true
     *
     * @param string $data
     * @param string $result
     *
     * @throws \RedisException
     */
    private function check_job(string $data, string $result): void
    {
        //Decode result
        $json = json_decode($result, true);

        //Save to fail list
        if (!is_null($json) && true !== $json) {
            parent::connect()->lPush(self::KEY_FAILED, json_encode(['data' => &$data, 'return' => &$result]));
        }

        unset($data, $result, $json);
    }

    /**
     * Call child process
     *
     * @throws \RedisException
     */
    private function call_child(): void
    {
        //Count running processes
        $runs = count($this->show_process());

        if (0 >= $left = $this->runs - $runs + 1) {
            return;
        }

        //Read queue list
        $queue = $this->show_queue();

        //Count jobs
        $jobs  = 0;
        $redis = parent::connect();
        foreach ($queue as $key => $item) {
            $jobs += $redis->lLen($key);
        }

        //Exit on no job
        if (0 === $jobs) {
            return;
        }

        //Count need processes
        if ($left < $need = (ceil($jobs / $this->exec) - $runs + 1)) {
            $need = &$left;
        }

        //Call child processes
        for ($i = 0; $i < $need; ++$i) {
            pclose(popen($this->child, 'r'));
        }

        unset($runs, $left, $queue, $jobs, $redis, $key, $item, $need, $i);
    }
}