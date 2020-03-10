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
    const KEY_PREFIX = '{Q}:';

    //Queue types (in BitMask)
    const TYPE_REALTIME = 1;
    const TYPE_UNIQUE   = 2;
    const TYPE_DELAY    = 4;

    //Wait properties
    const WAIT_IDLE = 3;
    const WAIT_SCAN = 60;

    /** @var \Redis $redis */
    public $redis;

    /** @var \core\lib\std\os $unit_os */
    private $unit_os;

    //Process properties
    private $max_fork = 10;
    private $max_exec = 1000;
    private $max_hist = 2000;

    //Queue name
    private $key_name = 'main:';

    //Runtime key slot
    private $key_slot = [];

    //Default key list
    private $key_list = [
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

        $object->key_slot = [];
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
     * @param string $type_mask BitMask value, default realtime
     * @param int    $time_wait in seconds
     *
     * @return int -1: unique job blocked; 0: add job failed; other: add job done with total job length in groups
     * @throws \Exception
     */
    public function add(string $cmd, array $data = [], string $group = 'main', string $type_mask = self::TYPE_REALTIME, int $time_wait = 0): int
    {
        //Add command
        $data['cmd'] = &$cmd;

        //Check group key
        if ('' === $group) {
            $group = 'main';
        }

        //Redirect to REALTIME
        if (0 === $time_wait) {
            $type_mask = self::TYPE_REALTIME;
        }

        //Build process keys
        $this->build_keys();

        //Check unique job identifier
        if (($type_mask & self::TYPE_UNIQUE) === self::TYPE_UNIQUE && !$this->check_unique($cmd, $data['unique_id'] ?? '', $time_wait)) {
            //Unique job blocked
            unset($cmd, $data, $group, $type_mask, $time_wait);
            return -1;
        }

        //Set job length
        $job_len = 0;
        //Pack job data in JSON
        $job_data = json_encode($data, JSON_FORMAT);

        //Add realtime job
        if (($type_mask & self::TYPE_REALTIME) === self::TYPE_REALTIME) {
            if (0 === $add_res = $this->add_realtime($group, $job_data)) {
                throw new \Exception('Realtime job add failed!', E_USER_WARNING);
            }

            //Count group jobs
            $job_len += $add_res;
        }

        //Add delay job
        if (($type_mask & self::TYPE_DELAY) === self::TYPE_DELAY) {
            if (0 === $add_res = $this->add_delay($group, $job_data, $time_wait)) {
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
     */
    public function kill(string $proc_hash = ''): int
    {
        //Build process keys
        $this->build_keys();

        //Get process list
        $proc_list = '' === $proc_hash ? array_keys($this->get_keys($this->key_slot['watch'])) : [$this->key_slot['worker'] . $proc_hash];

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
        $failed_key = $this->get_log_key('failed');

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
        $result = $this->add_realtime('rollback', $job_data['data']);

        unset($job_json, $failed_key, $job_data);
        return $result;
    }

    /**
     * Delete logs
     *
     * @param string $type
     *
     * @return int
     * @throws \Exception
     */
    public function del_logs(string $type = 'success'): int
    {
        //Remove key
        $del = $this->redis->del($this->get_log_key($type));

        unset($type);
        return $del;
    }

    /**
     * Show success/failed logs
     *
     * @param string $type
     * @param int    $start
     * @param int    $end
     *
     * @return array
     * @throws \Exception
     */
    public function show_logs(string $type = 'success', int $start = 0, int $end = -1): array
    {
        //Get log key
        $key = $this->get_log_key($type);

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
     * Show queue length
     *
     * @param string $queue_key
     *
     * @return int
     */
    public function show_length(string $queue_key): int
    {
        return (int)$this->redis->lLen($queue_key);
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

        return $this->redis->sMembers($this->key_slot['listen']);
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

        return $this->get_keys($this->key_slot['watch']);
    }

    /**
     * Master process entry
     *
     * @param int $max_fork
     * @param int $max_exec
     * @param int $max_hist
     *
     * @throws \Exception
     */
    public function go(int $max_fork = 10, int $max_exec = 1000, int $max_hist = 2000): void
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

        //Set max history records
        if (0 < $max_hist) {
            $this->max_hist = &$max_hist;
        }

        unset($max_fork, $max_exec, $max_hist);

        //Get idle time
        $idle_time = $this->get_idle_time();

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
            if (empty($proc_list = array_keys($this->get_keys($this->key_slot['watch'])))) {
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
            $valid   = $this->redis->get($master_key) === $master_hash;
            $running = $this->redis->expire($master_key, self::WAIT_SCAN);

            //Idle wait on no job or unit process running
            if (false === ($job_key = $this->redis->sRandMember($this->key_slot['listen'])) || 1 < count($this->get_keys($this->key_slot['watch']))) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Idle wait on no job
            if (empty($job = $this->get_job($job_key, $idle_time))) {
                sleep(self::WAIT_IDLE);
                continue;
            }

            //Re-add queue job
            $this->redis->rPush($job[0], $job[1]);

            //Call realtime unit
            $this->call_unit_realtime($cmd_realtime);
        } while ($valid && $running);

        //On exit
        $kill_all();

        unset($idle_time, $master_hash, $master_key, $kill_all, $unit_io, $unit_cmd, $cmd_delay, $cmd_realtime, $valid, $running, $job_key, $job);
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
                if (empty($time_list = $this->redis->zRangeByScore($this->key_slot['delay_time'], 0, time()))) {
                    break;
                }

                //Seek for jobs
                foreach ($time_list as $time_rec) {
                    $time_key = (string)$time_rec;

                    do {
                        //No delay job found
                        if (false === $delay_job = $this->redis->rPop($this->key_slot['delay_jobs'] . $time_key)) {
                            $this->redis->zRem($this->key_slot['delay_time'], $time_key);
                            $this->redis->hDel($this->key_slot['delay_lock'], $time_key);
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
                $idle_time = $this->get_idle_time() / 2;

                do {
                    //Exit on no job
                    if (false === $job_key = $this->redis->sRandMember($this->key_slot['listen'])) {
                        break;
                    }

                    //Execute job
                    if (!empty($job = $this->get_job($job_key, $idle_time))) {
                        $this->exec_job($job[1], $unit_router, $unit_reflect);
                    }
                } while (0 < $this->redis->exists($unit_key) && $this->redis->expire($unit_key, self::WAIT_SCAN) && ++$unit_exec < $this->max_exec);

                //On exit
                $kill_unit($unit_hash);

                unset($unit_router, $unit_reflect, $unit_hash, $unit_key, $kill_unit, $idle_time, $job_key, $job);
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
     * Get job from queue stack
     *
     * @param string $job_key
     * @param int    $idle_time
     *
     * @return array
     */
    private function get_job(string $job_key, int $idle_time): array
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
     * Build runtime keys
     */
    private function build_keys(): void
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
    private function add_realtime(string $group, string $data): int
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
    private function add_delay(string $group, string $data, int $time): int
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
     * Get active keys
     *
     * @param string $key
     *
     * @return array
     */
    private function get_keys(string $key): array
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
    private function get_log_key(string $type): string
    {
        //Check log type
        if (!in_array($type, ['success', 'failed'], true)) {
            throw new \Exception('Log type ERROR!');
        }

        //Build process keys
        $this->build_keys();

        //Get log key
        return $this->key_slot[$type];
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
        $runs = count($this->get_keys($this->key_slot['watch']));

        if (0 >= $left = $this->max_fork - $runs + 1) {
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
        if ($left < $need = (ceil($jobs / $this->max_exec) - $runs + 1)) {
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
            $this->redis->lPush($this->key_slot['failed'], json_encode(['data' => &$data, 'time' => date('Y-m-d H:i:s'), 'return' => $throwable->getMessage()], JSON_FORMAT));
            unset($throwable);
        }

        unset($data, $unit_router, $unit_reflect, $input_data, $cmd_group, $group, $class, $method, $method_reflect, $class_object, $matched_params);
    }

    /**
     * Check a unique job
     *
     * @param string $job_cmd
     * @param string $unique_id
     * @param int    $time_wait
     *
     * @return bool
     */
    private function check_unique(string $job_cmd, string $unique_id, int $time_wait): bool
    {
        //Default result
        $result = false;

        //Build job unique key
        $unique_key = $this->key_slot['unique'] . $job_cmd;

        //Append defined unique id
        if ('' !== $unique_id) {
            $unique_key .= ':' . $unique_id;
        }

        //Check job with unique id
        if ($this->redis->setnx($unique_key, time() + $time_wait)) {
            //Set unique job duration life
            $this->redis->expire($unique_key, $time_wait);
            //Check passed
            $result = true;
        }

        unset($job_cmd, $unique_id, $time_wait, $unique_key);
        return $result;
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

        //Build queue log
        $log = json_encode(['data' => &$data, 'time' => date('Y-m-d H:i:s'), 'return' => &$result], JSON_FORMAT);

        //Save to queue history
        !is_null($json) && true !== $json
            //Save to failed history
            ? $this->redis->lPush($this->key_slot['failed'], $log)
            //Save to success history
            : 0 < (int)$this->redis->lPush($this->key_slot['success'], $log) && $this->redis->lTrim($this->key_slot['success'], 0, $this->max_hist - 1);

        unset($data, $result, $json);
    }
}