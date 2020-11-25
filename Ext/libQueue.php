<?php

/**
 * Queue Extension (on Redis)
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Execute;
use Core\Factory;
use Core\Lib\App;
use Core\Lib\IOUnit;
use Core\Lib\Router;
use Core\OSUnit;
use Core\Reflect;

/**
 * Class libQueue
 *
 * @package Ext
 */
class libQueue extends Factory
{
    //Key prefix
    const KEY_PREFIX = '{Q}:';

    //Queue types (in BitMask)
    const TYPE_REALTIME = 1;
    const TYPE_UNIQUE   = 2;
    const TYPE_DELAY    = 4;

    //Wait properties
    const WAIT_IDLE = 3;
    const WAIT_SCAN = 60;

    /** @var \Redis $redis */
    public \Redis $redis;

    /** @var \Core\Lib\App $app */
    private App $app;

    /** @var \Core\Lib\IOUnit $io_unit */
    private IOUnit $io_unit;

    /** @var \Core\OSUnit $os_unit */
    private OSUnit $os_unit;

    //Process properties
    private int $max_fork = 10;
    private int $max_exec = 1000;
    private int $max_hist = 2000;

    //Queue name
    private string $key_name = 'main:';

    //Runtime key slot
    private array $key_slot = [];

    //Default key list
    private array $key_list = [
        //Process keys
        'listen'     => 'listen',
        'failed'     => 'failed',
        'success'    => 'success',
        //Queue prefix
        'jobs'       => 'jobs:',
        'watch'      => 'watch:',
        'worker'     => 'worker:',
        'unique'     => 'unique:',
        //Queue delay keys
        'delay_lock' => 'delay:lock',
        'delay_time' => 'delay:time',
        'delay_jobs' => 'delay:jobs:'
    ];

    //Queue unit handler
    private array $unit_handler = [];

    /**
     * Bind to Redis connection
     *
     * @param \Redis $redis
     *
     * @return $this
     */
    public function bindRedis(\Redis $redis): self
    {
        $this->redis = &$redis;

        unset($redis);
        return $this;
    }

    /**
     * Clone queue instance as alias
     *
     * @param string $name
     *
     * @return $this
     */
    public function cloneAs(string $name): object
    {
        $object = clone $this;

        $object->key_slot = [];
        $object->key_name = $name . ':';

        unset($name);
        return $object;
    }

    /**
     * Set custom UnitHandler
     *
     * @param string $handler_class
     * @param string $handler_method
     *
     * @return $this
     */
    public function setUnitHandler(string $handler_class, string $handler_method): self
    {
        $this->unit_handler = [$handler_class, $handler_method];

        unset($handler_class, $handler_method);
        return $this;
    }

    /**
     * Add job
     *
     * @param string $cmd
     * @param array  $data      Use string "job_hash" to mark as unique key, use string "argv" to pass as command argv param
     * @param string $group
     * @param string $type_mask BitMask value, default realtime
     * @param int    $time_wait in seconds
     *
     * @return int -1: unique job blocked; 0: add job failed; other: add job done with total job length in groups
     * @throws \Exception
     */
    public function add(string $cmd, array $data = [], string $group = 'main', string $type_mask = self::TYPE_REALTIME, int $time_wait = 0): int
    {
        //Add command
        $data['c'] = &$cmd;

        //Check group key
        if ('' === $group) {
            $group = 'main';
        }

        //Redirect to REALTIME
        if (0 === $time_wait) {
            $type_mask = self::TYPE_REALTIME;
        }

        //Build process keys
        $this->buildKeys();

        //Check unique job identifier
        if (($type_mask & self::TYPE_UNIQUE) === self::TYPE_UNIQUE && $this->isUnique($cmd, $data['job_hash'] ?? '', $time_wait)) {
            //Unique job blocked
            unset($cmd, $data, $group, $type_mask, $time_wait);
            return -1;
        }

        //Set job length
        $job_len = 0;

        //Pack job data in JSON
        unset($data['job_hash']);
        $job_data = json_encode($data, JSON_FORMAT);

        //Add realtime job
        if (($type_mask & self::TYPE_REALTIME) === self::TYPE_REALTIME) {
            if (0 === $add_res = $this->addRealtime($group, $job_data)) {
                throw new \Exception('Realtime job add failed!', E_USER_WARNING);
            }

            //Count group jobs
            $job_len += $add_res;
        }

        //Add delay job
        if (($type_mask & self::TYPE_DELAY) === self::TYPE_DELAY) {
            if (0 === $add_res = $this->addDelay($group, $job_data, $time_wait)) {
                throw new \Exception('Delay job add failed!', E_USER_WARNING);
            }

            //Count group jobs
            $job_len += $add_res;
        }

        unset($cmd, $data, $group, $type_mask, $time_wait, $job_data, $add_res);
        return $job_len;
    }

    /**
     * Kill worker process
     * Caution: Do NOT expose "kill" to TrustZone directly
     *
     * @param string $proc_hash
     *
     * @return int
     * @throws \Exception
     */
    public function kill(string $proc_hash = ''): int
    {
        //Build process keys
        $this->buildKeys();

        //Get process list
        $proc_list = '' === $proc_hash ? array_keys($this->getKeys($this->key_slot['watch'])) : [$this->key_slot['worker'] . $proc_hash];

        if (empty($proc_list)) {
            return 0;
        }

        //Remove worker from process list
        $result = call_user_func_array([$this->redis, 'del'], $proc_list);

        //Remove worker keys
        array_unshift($proc_list, $this->key_slot['watch']);
        call_user_func_array([$this->redis, 'hDel'], $proc_list);

        unset($proc_hash, $proc_list);
        return $result;
    }

    /**
     * Rollback a failed job to realtime list
     *
     * @param string $job_json
     *
     * @return int
     * @throws \Exception
     */
    public function rollback(string $job_json): int
    {
        //Get failed list key
        $failed_key = $this->getLogKey('failed');

        //Remove from failed list
        if (0 === (int)($this->redis->lRem($failed_key, $job_json, 1))) {
            unset($job_json, $failed_key);
            return 0;
        }

        //Decode job data
        if (is_null($job_data = json_decode($job_json, true))) {
            unset($job_json, $failed_key, $job_data);
            return 0;
        }

        //Add job as realtime job in rollback group
        $result = $this->addRealtime('rollback', json_encode($job_data['data'], JSON_FORMAT));

        unset($job_json, $failed_key, $job_data);
        return $result;
    }

    /**
     * Clear logs
     *
     * @param string $type
     *
     * @return int
     * @throws \Exception
     */
    public function clearLogs(string $type = 'success'): int
    {
        //Remove key
        $del = $this->redis->del($this->getLogKey($type));

        unset($type);
        return $del;
    }

    /**
     * Get success/failed logs
     *
     * @param string $type
     * @param int    $start
     * @param int    $end
     *
     * @return array
     * @throws \Exception
     */
    public function getLogs(string $type = 'success', int $start = 0, int $end = -1): array
    {
        //Get log key
        $key = $this->getLogKey($type);

        //Read logs
        $list = [
            'key'  => &$key,
            'len'  => $this->redis->lLen($key),
            'data' => $this->redis->lRange($key, $start, $end)
        ];

        unset($type, $start, $end, $key);
        return $list;
    }

    /**
     * Get queue job length
     *
     * @param string $queue_key
     *
     * @return int
     */
    public function getJobLength(string $queue_key): int
    {
        return (int)$this->redis->lLen($queue_key);
    }

    /**
     * Get queue list
     *
     * @return array
     * @throws \Exception
     */
    public function getQueueList(): array
    {
        //Build process keys
        $this->buildKeys();

        return $this->redis->sMembers($this->key_slot['listen']);
    }

    /**
     * Get process list
     *
     * @return array
     * @throws \Exception
     */
    public function getProcList(): array
    {
        //Build process keys
        $this->buildKeys();

        return $this->getKeys($this->key_slot['watch']);
    }

    /**
     * Queue master handler
     *
     * @param int $max_fork
     * @param int $max_exec
     * @param int $max_hist
     *
     * @throws \Exception
     */
    public function callMasterHandler(int $max_fork = 10, int $max_exec = 1000, int $max_hist = 2000): void
    {
        //Initialize
        $this->initProc();

        //Build process keys
        $this->buildKeys();

        //Set default unitHandler
        if (empty($this->unit_handler)) {
            throw new \Exception('Queue unit handler NOT set!', E_USER_ERROR);
        }

        //Set max forks
        if (0 < $max_fork) {
            $this->max_fork = &$max_fork;
        }

        //Set max executes
        if (0 < $max_exec) {
            $this->max_exec = &$max_exec;
        }

        //Set max history records
        if (0 < $max_hist) {
            $this->max_hist = &$max_hist;
        }

        unset($max_fork, $max_exec, $max_hist);

        //Get idle time
        $idle_time = $this->getIdleTime();

        //Build master hash and key
        $master_hash = hash('crc32b', uniqid(mt_rand(), true));
        $master_key  = $this->key_slot['worker'] . ($_SERVER['HOSTNAME'] ?? 'master');

        //Exit when master process exists
        if (!$this->redis->setnx($master_key, $master_hash)) {
            exit('Already running!');
        }

        //Set process life and add to watch list
        $this->redis->expire($master_key, self::WAIT_SCAN);
        $this->redis->hSet($this->key_slot['watch'], $master_key, time());

        //Create kill_all function
        $kill_all = function (): void
        {
            //Get process list
            if (empty($proc_list = array_keys($this->getKeys($this->key_slot['watch'])))) {
                return;
            }

            //Remove worker from process list
            call_user_func_array([$this->redis, 'del'], $proc_list);

            //Remove worker from watch list
            array_unshift($proc_list, $this->key_slot['watch']);
            call_user_func_array([$this->redis, 'hDel'], $proc_list);

            unset($proc_list);
        };

        //Close on shutdown
        register_shutdown_function($kill_all);

        //Build unit command
        $unit_cmd = '"' . $this->os_unit->getPhpPath() . '" "' . $this->app->script_path . '" -t"json" ';
        $unit_cmd .= '-c"' . $this->io_unit->encodeData('/' . strtr($this->unit_handler[0], '\\', '/') . '/' . $this->unit_handler[1]) . '" ';

        //Build delay command
        $cmd_delay = $this->os_unit->setCmd($unit_cmd . '-d"' . $this->io_unit->encodeData(json_encode(['type' => 'delay', 'name' => $this->key_name], JSON_FORMAT)) . '"')->setAsBg()->setEnvPath()->fetchCmd();

        //Build realtime command
        $cmd_realtime = $this->os_unit->setCmd($unit_cmd . '-d"' . $this->io_unit->encodeData(json_encode(['type' => 'realtime', 'name' => $this->key_name], JSON_FORMAT)) . '"')->setAsBg()->setEnvPath()->fetchCmd();

        do {
            //Call delay unit
            $this->callUnitDelay($cmd_delay);

            //Get process status
            $is_valid   = ($this->redis->get($master_key) === $master_hash);
            $is_running = $this->redis->expire($master_key, self::WAIT_SCAN);

            //Idle wait on no job or unit process running
            if (false === ($job_key = $this->redis->sRandMember($this->key_slot['listen'])) || 1 < count($this->getKeys($this->key_slot['watch']))) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Idle wait on no job
            if (empty($job = $this->getJob($job_key, $idle_time))) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Re-add queue job
            $this->redis->rPush($job[0], $job[1]);

            //Call realtime unit
            $this->callUnitRealtime($cmd_realtime);
        } while ($is_valid && $is_running);

        //On exit
        $kill_all();

        unset($idle_time, $master_hash, $master_key, $kill_all, $unit_cmd, $cmd_delay, $cmd_realtime, $is_valid, $is_running, $job_key, $job);
    }

    /**
     * Queue unit handler
     *
     * @param string $type
     * @param string $name
     *
     * @throws \Exception
     */
    public function callUnitHandler(string $type = 'realtime', string $name = 'main:'): void
    {
        //Initialize
        $this->initProc();

        //Set process name
        $this->key_name = &$name;

        //Build process keys
        $this->buildKeys();

        //Set init count
        $exec_count = 0;

        switch ($type) {
            case 'delay':
                //Read time rec
                if (empty($time_list = $this->redis->zRangeByScore($this->key_slot['delay_time'], 0, time()))) {
                    break;
                }

                //Seek for jobs
                foreach ($time_list as $time_rec) {
                    $time_key = (string)$time_rec;

                    do {
                        //No delay job found
                        if (false === ($delay_job = $this->redis->rPop($this->key_slot['delay_jobs'] . $time_key))) {
                            $this->redis->zRem($this->key_slot['delay_time'], $time_key);
                            $this->redis->hDel($this->key_slot['delay_lock'], $time_key);
                            break;
                        }

                        $job_data = json_decode($delay_job, true);

                        //Add realtime job
                        $this->addRealtime($job_data['group'], $job_data['job']);
                    } while (false !== $delay_job && $exec_count < $this->max_exec);

                    ++$exec_count;
                }

                unset($time_list, $time_rec, $time_key, $delay_job, $job_data);
                break;

            default:
                //Init Router, Reflect, Execute
                $router  = Router::new();
                $reflect = Reflect::new();
                $execute = Execute::new();

                //Build unit hash and key
                $unit_hash = hash('crc32b', uniqid(mt_rand(), true));
                $unit_key  = $this->key_slot['worker'] . $unit_hash;

                //Add to watch list
                $this->redis->set($unit_key, '', self::WAIT_SCAN);
                $this->redis->hSet($this->key_slot['watch'], $unit_key, time());

                //Create kill_unit function
                $kill_unit = function (string $unit_hash): void
                {
                    //Remove worker from process list
                    $this->redis->del($worker_key = $this->key_slot['worker'] . $unit_hash);

                    //Remove worker from watch list
                    $this->redis->hDel($this->key_slot['watch'], $worker_key);

                    unset($unit_hash);
                };

                //Close on exit
                register_shutdown_function($kill_unit, $unit_hash);

                //Get idle time
                $idle_time = $this->getIdleTime() / 2;

                do {
                    //Exit on no job
                    if (false === $job_key = $this->redis->sRandMember($this->key_slot['listen'])) {
                        break;
                    }

                    //Execute job
                    if (!empty($job = $this->getJob($job_key, $idle_time))) {
                        $this->execJob($job[1], $router, $reflect, $execute);
                    }
                } while (0 < $this->redis->exists($unit_key) && $this->redis->expire($unit_key, self::WAIT_SCAN) && ++$exec_count < $this->max_exec);

                //On exit
                $kill_unit($unit_hash);

                unset($router, $reflect, $execute, $unit_hash, $unit_key, $kill_unit, $idle_time, $job_key, $job);
                break;
        }

        unset($type, $name, $exec_count);
    }

    /**
     * Process initialize
     *
     * @throws \Exception
     */
    private function initProc(): void
    {
        //Detect ENV (only support CLI)
        $this->app = App::new();

        if (!$this->app->is_cli) {
            throw new \Exception('Only in CLI!', E_USER_ERROR);
        }

        /** @var \Core\Lib\IOUnit io_unit */
        $this->io_unit = IOUnit::new();

        /** @var \Core\OSUnit os_unit */
        $this->os_unit = OSUnit::new();
    }

    /**
     * Build runtime keys
     *
     * @throws \Exception
     */
    private function buildKeys(): void
    {
        if (!empty($this->key_slot)) {
            return;
        }

        //Build prefix
        $prefix = self::KEY_PREFIX . $this->key_name;

        //Build queue key slot
        foreach ($this->key_list as $key => $value) {
            $this->key_slot[$key] = $prefix . $value;
        }

        //Fill watch key slot
        $this->key_slot['watch'] .= $_SERVER['HOSTNAME'] ?? 'worker';
        unset($prefix, $key, $value);
    }

    /**
     * Add realtime job
     *
     * @param string $group
     * @param string $data
     *
     * @return int
     */
    private function addRealtime(string $group, string $data): int
    {
        //Add listen list
        $this->redis->sAdd($this->key_slot['listen'], $key = $this->key_slot['jobs'] . $group);

        //Add job
        $result = (int)$this->redis->lPush($key, $data);

        unset($group, $data, $key);
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
    private function addDelay(string $group, string $data, int $time): int
    {
        //Calculate delay time
        $delay = time() + $time;

        //Set time lock
        if ($this->redis->hSetNx($this->key_slot['delay_lock'], $delay, $delay)) {
            //Add time rec
            $this->redis->zAdd($this->key_slot['delay_time'], $delay, $delay);
        }

        //Add delay job
        $result = (int)$this->redis->lPush(
            $this->key_slot['delay_jobs'] . (string)$delay,
            json_encode([
                'group' => &$group,
                'job'   => &$data
            ], JSON_FORMAT));

        unset($group, $data, $time, $delay);
        return $result;
    }

    /**
     * Get job from queue stack
     *
     * @param string $job_key
     * @param int    $idle_time
     *
     * @return array
     */
    private function getJob(string $job_key, int $idle_time): array
    {
        //Check job length & get job content
        if (0 < $this->redis->lLen($job_key) && !empty($job = $this->redis->brPop($job_key, $idle_time))) {
            unset($job_key, $idle_time);
            return $job;
        }

        //Remove empty job list
        $this->redis->sRem($this->key_slot['listen'], $job_key);

        unset($job_key, $idle_time);
        return [];
    }

    /**
     * Get active keys
     *
     * @param string $key
     *
     * @return array
     */
    private function getKeys(string $key): array
    {
        if (0 === $this->redis->exists($key)) {
            return [];
        }

        if (empty($keys = $this->redis->hGetAll($key))) {
            return [];
        }

        foreach ($keys as $k => $v) {
            if (0 === $this->redis->exists($k)) {
                $this->redis->hDel($key, $k);
                unset($keys[$k]);
            }
        }

        unset($key, $k, $v);
        return $keys;
    }

    /**
     * Get log key
     *
     * @param string $type
     *
     * @return string
     * @throws \Exception
     */
    private function getLogKey(string $type): string
    {
        //Check log type
        if (!in_array($type, ['success', 'failed'], true)) {
            $type = 'success';
        }

        //Build process keys
        $this->buildKeys();

        //Get log key
        return $this->key_slot[$type];
    }

    /**
     * Get idle time
     *
     * @return int
     */
    private function getIdleTime(): int
    {
        return (int)(self::WAIT_SCAN / 2);
    }

    /**
     * Call delay unit process
     *
     * @param string $cmd
     */
    private function callUnitDelay(string $cmd): void
    {
        pclose(popen($cmd, 'r'));
        unset($cmd);
    }

    /**
     * Call realtime unit process
     *
     * @param string $cmd
     */
    private function callUnitRealtime(string $cmd): void
    {
        //Count running processes
        $runs = count($this->getKeys($this->key_slot['watch']));

        if (0 >= ($left = $this->max_fork - $runs + 1)) {
            return;
        }

        //Read queue list
        $list = $this->redis->sMembers($this->key_slot['listen']);

        //Count jobs
        $jobs = 0;
        foreach ($list as $item) {
            $jobs += $this->redis->lLen($item);
        }

        //Exit on no job
        if (0 === $jobs) {
            return;
        }

        //Count need processes
        if ($left < ($need = (ceil($jobs / $this->max_exec) - $runs + 1))) {
            $need = &$left;
        }

        //Call unit processes
        for ($i = 0; $i < $need; ++$i) {
            pclose(popen($cmd, 'r'));
        }

        unset($cmd, $runs, $left, $list, $jobs, $item, $need, $i);
    }

    /**
     * Execute job
     *
     * @param string           $data
     * @param \Core\Lib\Router $router
     * @param \Core\Reflect    $reflect
     * @param \Core\Execute    $execute
     */
    private function execJob(string $data, Router $router, Reflect $reflect, Execute $execute): void
    {
        //Decode data in JSON
        $input_data = json_decode($data, true);

        //Source data parse failed
        if (!is_array($input_data)) {
            $this->redis->lPush($this->key_slot['failed'], json_encode(['time' => date('Y-m-d H:i:s'), 'data' => &$data, 'return' => 'Data ERROR!'], JSON_FORMAT));

            unset($data, $router, $reflect, $execute, $input_data);
            return;
        }

        try {
            //Parse CMD
            $cmd_group = $router->parse($input_data['c']);

            //Call CGI
            if (!empty($cmd_group['cgi'])) {
                //Remap input data
                $this->io_unit->src_input = $input_data;
                //Execute CGI command
                $this->callCgi($cmd_group['cgi'], $reflect, $execute);
            }

            //Call CLI
            if (!empty($cmd_group['cli'])) {
                //Remap argv data
                $this->io_unit->src_argv = $input_data['argv'] ?? '';
                //Execute CLI command
                $this->callCli($cmd_group['cli'], $execute);
            }
        } catch (\Throwable $throwable) {
            $this->redis->lPush($this->key_slot['failed'], json_encode(['time' => date('Y-m-d H:i:s'), 'data' => &$input_data, 'return' => $throwable->getMessage()], JSON_FORMAT));
            unset($throwable);
        }

        unset($data, $router, $reflect, $execute, $input_data, $cmd_group);
    }

    /**
     * Call CGI command
     *
     * @param array         $cmd_group
     * @param \Core\Reflect $reflect
     * @param \Core\Execute $execute
     *
     * @throws \ReflectionException
     */
    private function callCgi(array $cmd_group, Reflect $reflect, Execute $execute): void
    {
        //Process CGI command
        while (is_array($cmd_pair = array_shift($cmd_group))) {
            //Extract CMD contents
            [$cmd_class, $cmd_method] = $cmd_pair;
            //Run script method
            $result = $execute->runScript($reflect, $cmd_class, $cmd_method, $cmd_pair[2] ?? implode('/', $cmd_pair));
            //Check result
            !empty($result) && $this->checkJob($result);
        }

        unset($cmd_group, $reflect, $execute, $cmd_pair, $cmd_class, $cmd_method, $result);
    }

    /**
     * Call CLI command
     *
     * @param array         $cmd_group
     * @param \Core\Execute $execute
     *
     * @throws \Exception
     */
    private function callCli(array $cmd_group, Execute $execute): void
    {
        //Process CLI command
        while (is_array($cmd_pair = array_shift($cmd_group))) {
            //Extract CMD contents
            [$cmd_name, $exe_path] = $cmd_pair;

            if ('' !== ($exe_path = trim($exe_path))) {
                //Run external program
                $execute->runProgram($this->os_unit, $cmd_name, $exe_path);
            }
        }

        unset($cmd_group, $execute, $cmd_pair, $cmd_name, $exe_path);
    }

    /**
     * Check a unique job
     *
     * @param string $job_cmd
     * @param string $job_hash
     * @param int    $time_wait
     *
     * @return bool
     */
    private function isUnique(string $job_cmd, string $job_hash, int $time_wait): bool
    {
        //Default result
        $result = true;

        //Build job unique key
        $unique_key = $this->key_slot['unique'] . $job_cmd;

        //Append defined unique id
        if ('' !== $job_hash) {
            $unique_key .= ':' . $job_hash;
        }

        //Check job with unique id
        if ($this->redis->setnx($unique_key, time() + $time_wait)) {
            //Set unique job duration life
            $this->redis->expire($unique_key, $time_wait);
            //Check passed
            $result = false;
        }

        unset($job_cmd, $job_hash, $time_wait, $unique_key);
        return $result;
    }

    /**
     * Check job
     * Only accept true
     *
     * @param array $result
     */
    private function checkJob(array $result): void
    {
        //Get return data
        $return = current($result);

        //Build queue log
        $log = json_encode(['time' => date('Y-m-d H:i:s'), 'data' => $this->io_unit->src_input, 'return' => &$return], JSON_FORMAT);

        //Save to queue history
        true !== $return
            //Save to failed history
            ? $this->redis->lPush($this->key_slot['failed'], $log)
            //Save to success history
            : 0 < (int)$this->redis->lPush($this->key_slot['success'], $log) && $this->redis->lTrim($this->key_slot['success'], 0, $this->max_hist - 1);

        unset($result, $return, $log);
    }
}