<?php

/**
 * Queue Extension (on Redis)
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

use core\lib\stc\factory as fty;
use core\lib\std\io;
use core\lib\std\os;
use core\lib\std\pool;
use core\lib\std\reflect;
use core\lib\std\router;

/**
 * Class queue
 *
 * @package ext
 */
class queue extends factory
{
    //Key prefix
    const KEY_PREFIX = 'Q:';

    //Queue type
    const TYPE_DELAY    = 'delay';
    const TYPE_UNIQUE   = 'unique';
    const TYPE_REALTIME = 'realtime';

    //Wait properties
    const WAIT_IDLE = 3;
    const WAIT_SCAN = 60;

    /** @var \Redis $instance */
    public $instance;

    //Process properties
    private $max_fork = 10;
    private $max_exec = 1000;

    //Key prepare status
    private $key_prep = false;

    //Queue name
    private $key_name = 'main:';

    //Queue keys
    private $key_listen = 'listen';
    private $key_failed = 'failed';

    //Queue prefix
    private $key_jobs   = 'jobs:';
    private $key_watch  = 'watch:';
    private $key_worker = 'worker:';
    private $key_unique = 'unique:';

    //Queue delay keys
    private $key_delay_lock = 'delay:lock';
    private $key_delay_time = 'delay:time';
    private $key_delay_jobs = 'delay:jobs:';

    /** @var \core\lib\std\os $unit_os */
    private $unit_os;

    /**
     * queue constructor.
     *
     * @param array $conf
     *
     * @throws \RedisException
     * @throws \ReflectionException
     */
    public function __construct(array $conf = [])
    {
        $this->instance = redis::create($conf)->connect();
        unset($conf);
    }

    /**
     * Name cloned queue instance
     *
     * @param string $name
     *
     * @return $this
     */
    public function set_name(string $name): object
    {
        $object = clone $this;

        $object->key_name = $name . ':';

        unset($name);
        return $object;
    }

    /**
     * Add job
     * Caution: Do NOT expose "add" to TrustZone directly
     *
     * @param string $cmd
     * @param array  $data
     * @param string $group
     * @param string $type default realtime
     * @param int    $time in seconds
     *
     * @return int -1: add unique job failed; 0: add job failed; other: succeeded with job length
     */
    public function add(string $cmd, array $data = [], string $group = 'main', string $type = self::TYPE_REALTIME, int $time = 0): int
    {
        //Add command
        $data['cmd'] = &$cmd;

        //Check group key
        if ('' === $group) {
            $group = 'main';
        }

        //Redirect to REALTIME
        if (0 === $time) {
            $type = self::TYPE_REALTIME;
        }

        //Build process keys
        $this->build_keys();

        //Pack queue job
        $job = json_encode($data, JSON_FORMAT);

        switch ($type) {
            case self::TYPE_UNIQUE:
                $result = $this->add_unique($group, $job, $time, $cmd . (isset($data['unique']) ? ':' . $data['unique'] : ''));
                break;

            case self::TYPE_DELAY:
                $result = $this->add_delay($group, $job, $time);
                break;

            default:
                $result = $this->add_realtime($group, $job);
                break;
        }

        unset($cmd, $data, $group, $type, $time, $job);
        return $result;
    }

    /**
     * Kill worker process
     * Caution: Do NOT expose "kill" to TrustZone directly
     *
     * @param string $proc_hash
     *
     * @return int
     */
    public function kill(string $proc_hash = ''): int
    {
        //Build process keys
        $this->build_keys();

        //Get process list
        $proc_list = '' === $proc_hash ? array_keys($this->get_keys($this->key_watch)) : [$this->key_worker . $proc_hash];

        if (empty($proc_list)) {
            return 0;
        }

        //Remove worker from process list
        $result = call_user_func_array([$this->instance, 'del'], $proc_list);

        //Remove worker keys
        array_unshift($proc_list, $this->key_watch);
        call_user_func_array([$this->instance, 'hDel'], $proc_list);

        unset($proc_hash, $proc_list);
        return $result;
    }

    /**
     * Show fail list
     *
     * @param int $start
     * @param int $end
     *
     * @return array
     */
    public function show_fail(int $start = 0, int $end = -1): array
    {
        //Build process keys
        $this->build_keys();

        $list = [
            'len'  => $this->instance->lLen($this->key_failed),
            'data' => $this->instance->lRange($this->key_failed, $start, $end)
        ];

        unset($start, $end);
        return $list;
    }

    /**
     * Show queue length
     *
     * @param string $queue_key
     *
     * @return int
     */
    public function show_length(string $queue_key): int
    {
        return (int)$this->instance->lLen($queue_key);
    }

    /**
     * Show queue list
     *
     * @return array
     */
    public function show_queue(): array
    {
        //Build process keys
        $this->build_keys();

        return $this->get_keys($this->key_listen);
    }

    /**
     * Show process list
     *
     * @return array
     */
    public function show_process(): array
    {
        //Build process keys
        $this->build_keys();

        return $this->get_keys($this->key_watch);
    }

    /**
     * Master process entry
     *
     * @param int $max_fork
     * @param int $max_exec
     *
     * @throws \Exception
     */
    public function go(int $max_fork = 10, int $max_exec = 1000): void
    {
        //Initialize
        $this->proc_init();

        //Build process keys
        $this->build_keys();

        //Set max forks
        if (0 < $max_fork) {
            $this->max_fork = &$max_fork;
        }

        //Set max executes
        if (0 < $max_exec) {
            $this->max_exec = &$max_exec;
        }

        unset($max_fork, $max_exec);

        //Get idle time
        $idle_time = $this->get_idle_time();

        //Build master hash and key
        $master_hash = hash('crc32b', uniqid(mt_rand(), true));
        $master_key  = $this->key_worker . ($_SERVER['HOSTNAME'] ?? 'master');

        //Exit when master process exists
        if (!$this->instance->setnx($master_key, $master_hash)) {
            exit('Already running!');
        }

        //Set process life and add to watch list
        $this->instance->expire($master_key, self::WAIT_SCAN);
        $this->instance->hSet($this->key_watch, $master_key, time());

        //Create kill_all function
        $kill_all = function (): void
        {
            //Get process list
            if (empty($proc_list = array_keys($this->get_keys($this->key_watch)))) {
                return;
            }

            //Remove worker from process list
            call_user_func_array([$this->instance, 'del'], $proc_list);

            //Remove worker from watch list
            array_unshift($proc_list, $this->key_watch);
            call_user_func_array([$this->instance, 'hDel'], $proc_list);

            unset($proc_list);
        };

        //Close on shutdown
        register_shutdown_function($kill_all);

        /** @var \core\lib\std\io $unit_io */
        $unit_io = fty::build(io::class);

        //Build unit command
        $unit_cmd = '"' . $this->unit_os->php_path() . '" "' . ENTRY_SCRIPT . '" -r"json" ';
        $unit_cmd .= '-c"' . $unit_io->encode('/' . strtr(get_class($this), '\\', '/') . '-unit') . '" ';

        //Build delay command
        $cmd_delay = $this->unit_os->cmd_bg($unit_cmd . '-d"' . $unit_io->encode(json_encode(['type' => 'delay', 'name' => $this->key_name], JSON_FORMAT)) . '"');

        //Build realtime command
        $cmd_realtime = $this->unit_os->cmd_bg($unit_cmd . '-d"' . $unit_io->encode(json_encode(['type' => 'realtime', 'name' => $this->key_name], JSON_FORMAT)) . '"');

        do {
            //Call delay unit
            $this->call_unit_delay($cmd_delay);

            //Get process status
            $valid   = $this->instance->get($master_key) === $master_hash;
            $running = $this->instance->expire($master_key, self::WAIT_SCAN);

            //Idle wait on no job or unit process running
            if (empty($list = $this->get_keys($this->key_listen)) || 1 < count($this->get_keys($this->key_watch))) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Idle wait on no job
            if (empty($job = $this->instance->brPop(array_keys($list), $idle_time))) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Re-add queue job
            $this->instance->rPush($job[0], $job[1]);

            //Call realtime unit
            $this->call_unit_realtime($cmd_realtime);
        } while ($valid && $running);

        //On exit
        $kill_all();

        unset($idle_time, $master_hash, $master_key, $kill_all, $unit_io, $unit_cmd, $cmd_delay, $cmd_realtime, $valid, $running, $list, $job);
    }

    /**
     * Unit process entry
     *
     * @param string $type
     * @param string $name
     *
     * @throws \Exception
     */
    public function unit(string $type, string $name): void
    {
        //Initialize
        $this->proc_init();

        //Set process name
        $this->key_name = &$name;

        //Build process keys
        $this->build_keys();

        //Set init count
        $unit_exec = 0;

        switch ($type) {
            case 'delay':
                //Read time rec
                if (empty($time_list = $this->instance->zRangeByScore($this->key_delay_time, 0, time()))) {
                    break;
                }

                //Seek for jobs
                foreach ($time_list as $time_rec) {
                    $time_key = (string)$time_rec;

                    do {
                        //No delay job found
                        if (false === $delay_job = $this->instance->rPop($this->key_delay_jobs . $time_key)) {
                            $this->instance->zRem($this->key_delay_time, $time_key);
                            $this->instance->hDel($this->key_delay_lock, $time_key);
                            break;
                        }

                        $job_data = json_decode($delay_job, true);

                        //Add realtime job
                        $this->add_realtime($job_data['group'], $job_data['job']);
                    } while (false !== $delay_job && $unit_exec < $this->max_exec);

                    ++$unit_exec;
                }

                unset($time_list, $time_rec, $time_key, $delay_job, $job_data);
                break;

            default:
                /** @var \core\lib\std\router $unit_router */
                $unit_router = fty::build(router::class);

                /** @var \core\lib\std\reflect $unit_reflect */
                $unit_reflect = fty::build(reflect::class);

                //Build unit hash and key
                $unit_hash = hash('crc32b', uniqid(mt_rand(), true));
                $unit_key  = $this->key_worker . $unit_hash;

                //Add to watch list
                $this->instance->set($unit_key, '', self::WAIT_SCAN);
                $this->instance->hSet($this->key_watch, $unit_key, time());

                //Create kill_unit function
                $kill_unit = function (string $unit_hash): void
                {
                    //Remove worker from process list
                    $this->instance->del($worker_key = $this->key_worker . $unit_hash);

                    //Remove worker from watch list
                    $this->instance->hDel($this->key_watch, $worker_key);

                    unset($unit_hash);
                };

                //Close on exit
                register_shutdown_function($kill_unit, $unit_hash);

                //Get idle time
                $idle_time = $this->get_idle_time();

                do {
                    //Exit on no job
                    if (empty($list = $this->get_keys($this->key_listen))) {
                        break;
                    }

                    //Execute job
                    if (!empty($job = $this->instance->brPop(array_keys($list), $idle_time))) {
                        $this->exec_job($job[1], $unit_router, $unit_reflect);
                    }
                } while (0 < $this->instance->exists($unit_key) && $this->instance->expire($unit_key, self::WAIT_SCAN) && ++$unit_exec < $this->max_exec);

                //On exit
                $kill_unit($unit_hash);

                unset($unit_router, $unit_reflect, $unit_hash, $unit_key, $kill_unit, $idle_time, $list, $job);
                break;
        }

        unset($type, $name, $unit_exec);
    }

    /**
     * Process initialize
     *
     * @throws \Exception
     */
    private function proc_init(): void
    {
        //Detect env (only support CLI)
        if (!fty::build(pool::class)->is_CLI) {
            throw new \Exception('Only support CLI!', E_USER_ERROR);
        }

        /** @var \core\lib\std\os unit_os */
        $this->unit_os = fty::build(os::class);
    }

    /**
     * Build runtime keys
     */
    private function build_keys(): void
    {
        if ($this->key_prep) {
            return;
        }

        //Build prefix
        $prefix = self::KEY_PREFIX . $this->key_name;

        //Build queue keys
        foreach ([
            'key_listen',
            'key_failed',
            'key_jobs',
            'key_watch',
            'key_worker',
            'key_unique',
            'key_delay_lock',
            'key_delay_time',
            'key_delay_jobs'
        ] as $key) {
            $this->$key = $prefix . $this->$key;
        }

        //Fill watch key
        $this->key_watch .= $_SERVER['HOSTNAME'] ?? 'worker';

        //Set key prepare status
        $this->key_prep = true;

        unset($prefix, $key);
    }

    /**
     * Add realtime job
     *
     * @param string $group
     * @param string $data
     *
     * @return int
     */
    private function add_realtime(string $group, string $data): int
    {
        //Add watch list
        $this->instance->hSet($this->key_listen, $key = $this->key_jobs . $group, time());

        //Add job
        $result = (int)$this->instance->lPush($key, $data);

        unset($group, $data, $key);
        return $result;
    }

    /**
     * Add unique job
     *
     * @param string $group
     * @param string $data
     * @param int    $time
     * @param string $unique
     *
     * @return int
     */
    private function add_unique(string $group, string $data, int $time, string $unique): int
    {
        //Check job duration
        if (!$this->instance->setnx($key = $this->key_unique . $unique, time())) {
            return -1;
        }

        //Set duration life
        $this->instance->expire($key, $time);

        //Add realtime job
        $result = $this->add_realtime($group, $data);

        unset($group, $data, $time, $unique, $key);
        return $result;
    }

    /**
     * Add delay job
     *
     * @param string $group
     * @param string $data
     * @param int    $time
     *
     * @return int
     */
    private function add_delay(string $group, string $data, int $time): int
    {
        //Calculate delay time
        $delay = time() + $time;

        //Set time lock
        if ($this->instance->hSetNx($this->key_delay_lock, $delay, $delay)) {
            //Add time rec
            $this->instance->zAdd($this->key_delay_time, $delay, $delay);
        }

        //Add delay job
        $result = $this->instance->lPush(
            $this->key_delay_jobs . (string)$delay,
            json_encode([
                'group' => &$group,
                'job'   => &$data
            ], JSON_FORMAT));

        unset($group, $data, $time, $delay);
        return $result;
    }

    /**
     * Get active keys
     *
     * @param string $key
     *
     * @return array
     */
    private function get_keys(string $key): array
    {
        if (0 === $this->instance->exists($key)) {
            return [];
        }

        if (empty($keys = $this->instance->hGetAll($key))) {
            return [];
        }

        foreach ($keys as $k => $v) {
            if (0 === $this->instance->exists($k)) {
                $this->instance->hDel($key, $k);
                unset($keys[$k]);
            }
        }

        unset($key, $k, $v);
        return $keys;
    }

    /**
     * Get idle time
     *
     * @return int
     */
    private function get_idle_time(): int
    {
        return (int)(self::WAIT_SCAN / 2);
    }

    /**
     * Call delay unit process
     *
     * @param string $cmd
     */
    private function call_unit_delay(string $cmd): void
    {
        pclose(popen($cmd, 'r'));
        unset($cmd);
    }

    /**
     * Call realtime unit process
     *
     * @param string $cmd
     */
    private function call_unit_realtime(string $cmd): void
    {
        //Count running processes
        $runs = count($this->get_keys($this->key_watch));

        if (0 >= $left = $this->max_fork - $runs + 1) {
            return;
        }

        //Read queue list
        $list = $this->get_keys($this->key_listen);

        //Count jobs
        $jobs = 0;
        foreach ($list as $key => $item) {
            $jobs += $this->instance->lLen($key);
        }

        //Exit on no job
        if (0 === $jobs) {
            return;
        }

        //Count need processes
        if ($left < $need = (ceil($jobs / $this->max_exec) - $runs + 1)) {
            $need = &$left;
        }

        //Call unit processes
        for ($i = 0; $i < $need; ++$i) {
            pclose(popen($cmd, 'r'));
        }

        unset($cmd, $runs, $left, $list, $jobs, $key, $item, $need, $i);
    }

    /**
     * Execute job
     *
     * @param string                $data
     * @param \core\lib\std\router  $unit_router
     * @param \core\lib\std\reflect $unit_reflect
     */
    private function exec_job(string $data, router $unit_router, reflect $unit_reflect): void
    {
        try {
            //Decode data in JSON
            if (!is_array($input_data = json_decode($data, true))) {
                throw new \Exception('Data ERROR!', E_USER_NOTICE);
            }

            //Parse CMD
            if (empty($cmd_group = $unit_router->parse_cmd($input_data['cmd']))) {
                throw new \Exception('Command NOT found!', E_USER_NOTICE);
            }

            //Process CMD group
            while (is_array($group = array_shift($cmd_group))) {
                //Skip non-exist class
                if (!class_exists($class = $unit_router->get_cls(array_shift($group)))) {
                    throw new \Exception('Class file ' . strtr($class, '\\', '/') . ' NOT found!', E_USER_NOTICE);
                }

                //Call function
                foreach ($group as $method) {
                    /** @var \ReflectionMethod $method_reflect */
                    $method_reflect = $unit_reflect->get_method($class, $method);

                    //Check method visibility
                    if (!$method_reflect->isPublic()) {
                        throw new \Exception($unit_router->get_key_name($class, $method) . ' => NOT for public!', E_USER_NOTICE);
                    }

                    //Create class instance
                    $class_object = !$method_reflect->isStatic() ? fty::create($class, $input_data) : $class;

                    //Filter method params
                    $matched_params = $unit_reflect->build_params($class, $method, $input_data);

                    //Argument params NOT matched
                    if (!empty($matched_params['diff'])) {
                        throw new \Exception($unit_router->get_key_name($class, $method) . ' => Missing params: [' . implode(', ', $matched_params['diff']) . ']', E_USER_NOTICE);
                    }

                    //Check result
                    $this->check_job($data, json_encode(call_user_func([$class_object, $method], ...$matched_params['param'])));
                }
            }
        } catch (\Throwable $throwable) {
            $this->instance->lPush($this->key_failed, json_encode(['data' => &$data, 'time' => date('Y-m-d H:i:s'), 'return' => $throwable->getMessage()], JSON_FORMAT));
            unset($throwable);
        }

        unset($data, $unit_router, $unit_reflect, $input_data, $cmd_group, $group, $class, $method, $method_reflect, $class_object, $matched_params);
    }

    /**
     * Check job
     * Only accept null & true
     *
     * @param string $data
     * @param string $result
     */
    private function check_job(string $data, string $result): void
    {
        //Decode result
        $json = json_decode($result, true);

        //Save to fail list
        if (!is_null($json) && true !== $json) {
            $this->instance->lPush($this->key_failed, json_encode(['data' => &$data, 'time' => date('Y-m-d H:i:s'), 'return' => &$result], JSON_FORMAT));
        }

        unset($data, $result, $json);
    }
}