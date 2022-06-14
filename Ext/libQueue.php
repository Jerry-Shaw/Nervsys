<?php

/**
 * Queue Extension (on Redis)
 *
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\LC\Factory;
use Nervsys\LC\Lib\App;
use Nervsys\LC\Lib\Caller;
use Nervsys\LC\Lib\IOData;
use Nervsys\LC\Lib\OSUnit;
use Nervsys\LC\Lib\Router;
use Nervsys\LC\Reflect;

class libQueue extends Factory
{
    //Key prefix
    const KEY_PREFIX = '{Q}:';

    //Queue types (in BitMask)
    const TYPE_REALTIME = 1;
    const TYPE_UNIQUE   = 2;
    const TYPE_DELAY    = 4;

    //Key lifetime (in seconds)
    const LIFETIME = 30;

    //Wait properties (in microseconds)
    const WAIT_TIME = 10000;

    /** @var \Redis $redis */
    public \Redis $redis;

    public App    $app;
    public Caller $caller;
    public Router $router;
    public IOData $IOData;
    public OSUnit $OSUnit;

    //Process properties
    private int $max_fork = 10;
    private int $max_exec = 1000;
    private int $max_hist = 2000;

    //Runtime key slot
    private array $key_slot = [];

    //Default key list
    private array $key_list = [
        //Process keys
        'idx'        => 'idx',
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

    //Queue job handler
    private array $job_handler = [];

    /**
     * libQueue constructor
     *
     * @throws \ReflectionException
     */
    public function __construct(string $name)
    {
        //Init Libraries
        $this->app    = App::new();
        $this->caller = Caller::new();
        $this->router = Router::new();
        $this->IOData = IOData::new();
        $this->OSUnit = OSUnit::new();

        //Build prefix
        $prefix = self::KEY_PREFIX . $name . ':';

        //Build queue key slot
        foreach ($this->key_list as $key => $value) {
            $this->key_slot[$key] = $prefix . $value;
        }

        //Fill watch key slot
        $this->key_slot['watch'] .= HOSTNAME;
        unset($name, $prefix, $key, $value);
    }

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
     * Set the maximum number of success logs
     *
     * @param int $number
     *
     * @return $this
     */
    public function setMaxHistory(int $number): self
    {
        $this->max_hist = &$number;

        unset($number);
        return $this;
    }

    /**
     * Set job handler
     *
     * @param string $class_name
     * @param string $method_name
     *
     * @return $this
     */
    public function setJobHandler(string $class_name, string $method_name): self
    {
        $this->job_handler = [$class_name, $method_name];

        unset($class_name, $method_name);
        return $this;
    }

    /**
     * Add job
     *
     * @param string $cmd
     * @param array  $data      Use string "job_hash" to mark as unique key, use array "argv" and string "cwd" to pass as command argv params and cwd path
     * @param string $group     Queue group key
     * @param int    $type_mask BitMask value, default realtime
     * @param int    $time_wait in seconds
     *
     * @return string           Job queue idx
     */
    public function add(string $cmd, array $data = [], string $group = 'main', int $type_mask = self::TYPE_REALTIME, int $time_wait = 0): string
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

        //Check unique job identifier
        if (($type_mask & self::TYPE_UNIQUE) === self::TYPE_UNIQUE && $this->isUnique($cmd, $data['job_hash'] ?? '', $time_wait)) {
            unset($cmd, $data, $group, $type_mask, $time_wait);
            return '';
        }

        //Pack job data in JSON
        $job_idx  = hash('md5', uniqid(mt_rand(), true));
        $job_data = json_encode($data + ['QID' => &$job_idx], JSON_FORMAT);

        //Add realtime job
        if (($type_mask & self::TYPE_REALTIME) === self::TYPE_REALTIME) {
            $this->addRealtime($group, $job_data) && $this->redis->hIncrBy($this->key_slot['idx'], $job_idx, 1);
        }

        //Add delay job
        if (($type_mask & self::TYPE_DELAY) === self::TYPE_DELAY) {
            $this->addDelay($group, $job_data, $time_wait) && $this->redis->hIncrBy($this->key_slot['idx'], $job_idx, 1);
        }

        unset($cmd, $data, $group, $type_mask, $time_wait, $job_data);
        return $job_idx;
    }

    /**
     * Kill worker process
     *
     * @param string $worker_key
     *
     * @return int
     */
    public function kill(string $worker_key = ''): int
    {
        //Get process list
        $worker_list = '' === $worker_key
            ? array_keys($this->getKeys($this->key_slot['watch']))
            : [$this->key_slot['worker'] . $worker_key];

        if (empty($worker_list)) {
            return 0;
        }

        //Remove worker from process list
        $result = call_user_func_array([$this->redis, 'del'], $worker_list);

        //Remove worker keys
        array_unshift($worker_list, $this->key_slot['watch']);
        call_user_func_array([$this->redis, 'hDel'], $worker_list);

        unset($worker_key, $worker_list);
        return $result;
    }

    /**
     * Remove a job by decreasing idx counter
     *
     * @param string $job_idx
     *
     * @return int left job idx counter (n <= 0 means removed)
     */
    public function remove(string $job_idx): int
    {
        $left = $this->redis->hIncrBy($this->key_slot['idx'], $job_idx, -1);

        if (0 >= $left) {
            $this->redis->hDel($this->key_slot['idx'], $job_idx);
        }

        unset($job_idx);
        return $left;
    }

    /**
     * Rollback a failed job to realtime list
     *
     * @param string $job_json
     *
     * @return bool
     */
    public function rollback(string $job_json): bool
    {
        //Decode job data
        if (is_null($job_data = json_decode($job_json, true))) {
            unset($job_json, $job_data);
            return false;
        }

        //Get failed list key
        $failed_key = $this->getLogKey('failed');

        //Remove from failed list
        if (0 === (int)($this->redis->lRem($failed_key, $job_json, 1))) {
            unset($job_json, $job_data, $failed_key);
            return false;
        }

        //Add job as realtime job in rollback group
        $result = $this->addRealtime('rollback', json_encode($job_data['data'], JSON_FORMAT));

        unset($job_json, $job_data, $failed_key);
        return $result;
    }

    /**
     * Remove a log
     *
     * @param string $type
     * @param string $job_json
     *
     * @return int
     */
    public function delLog(string $type, string $job_json): int
    {
        return (int)($this->redis->lRem($this->getLogKey($type), $job_json, 1));
    }

    /**
     * Clear logs
     *
     * @param string $type
     *
     * @return int
     */
    public function clearLogs(string $type = 'success'): int
    {
        return $this->redis->del($this->getLogKey($type));
    }

    /**
     * Get success/failed logs
     *
     * @param string $type
     * @param int    $start
     * @param int    $end
     *
     * @return array
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
     */
    public function getQueueList(): array
    {
        return $this->redis->sMembers($this->key_slot['listen']);
    }

    /**
     * Get worker process list
     *
     * @return array
     */
    public function getWorkerList(): array
    {
        return $this->getKeys($this->key_slot['watch']);
    }

    /**
     * Start queue main process
     *
     * @param int $max_fork
     * @param int $max_exec
     *
     * @throws \Exception
     */
    public function start(int $max_fork = 10, int $max_exec = 1000): void
    {
        //Check job handler
        if (empty($this->job_handler) || !method_exists(...$this->job_handler)) {
            throw new \Exception('Job Handler NOT found!');
        }

        //Set max forks
        if (0 < $max_fork) {
            $this->max_fork = &$max_fork;
        }

        //Set max executes
        if (0 < $max_exec) {
            $this->max_exec = &$max_exec;
        }

        unset($max_fork, $max_exec);

        //Build master hash and key
        $master_hash = hash('md5', uniqid(mt_rand(), true));
        $master_key  = $this->key_slot['worker'] . HOSTNAME;

        //Exit when master process exists
        if (!$this->redis->setnx($master_key, $master_hash)) {
            echo 'Already running!';
            exit(0);
        }

        //Close on shutdown
        register_shutdown_function([$this, 'kill']);

        //Set process life and add to watch list
        $this->redis->expire($master_key, self::LIFETIME);
        $this->redis->hSet($this->key_slot['watch'], $master_key, time());

        //Build job processor command
        $job_proc = '"' . $this->OSUnit->getPhpPath() . '" "' . $this->app->script_path . '" ';
        $job_proc .= '-c"' . $this->IOData->encodeData('/' . $this->job_handler[0] . '/' . $this->job_handler[1]) . '" -r"none" ';

        //Build delay command
        $cmd_delay = $this->OSUnit->setCmd($job_proc . '-d"' . $this->IOData->encodeData(json_encode(['type' => 'delay'], JSON_FORMAT)) . '"')->setAsBg()->setEnvPath()->fetchCmd();

        //Build realtime command
        $cmd_realtime = $this->OSUnit->setCmd($job_proc . '-d"' . $this->IOData->encodeData(json_encode(['type' => 'realtime'], JSON_FORMAT)) . '"')->setAsBg()->setEnvPath()->fetchCmd();

        while ($this->redis->get($master_key) === $master_hash && $this->redis->expire($master_key, self::LIFETIME)) {
            //Call delay processor
            $this->callDelay($cmd_delay);

            //Wait on no job or job process is running
            $job_key = $this->redis->sRandMember($this->key_slot['listen']);

            if (false === $job_key || 1 < count($this->getKeys($this->key_slot['watch']))) {
                usleep(self::WAIT_TIME);
                continue;
            }

            //Wait on no job
            if (0 === (int)$this->redis->lLen($job_key)) {
                usleep(self::WAIT_TIME);
                continue;
            }

            //Call realtime processor
            $this->callRealtime($cmd_realtime);
        }

        unset($master_hash, $master_key, $job_proc, $cmd_delay, $cmd_realtime, $job_key);
    }

    /**
     * Queue job handler
     *
     * @param string $type
     */
    public function jobHandler(string $type = 'realtime'): void
    {
        $exec_count = 0;

        if ('realtime' === $type) {
            //Build processor hash and key
            $proc_hash = hash('crc32b', uniqid(mt_rand(), true));
            $proc_key  = $this->key_slot['worker'] . $proc_hash;

            //Add to watch list
            $this->redis->set($proc_key, '', self::LIFETIME);
            $this->redis->hSet($this->key_slot['watch'], $proc_key, time());

            //Close on exit
            register_shutdown_function([$this, 'kill'], $proc_hash);

            while (0 < (int)$this->redis->exists($proc_key) && $this->redis->expire($proc_key, self::LIFETIME) && $exec_count < $this->max_exec) {
                //Exit on no job
                $job_key = $this->redis->sRandMember($this->key_slot['listen']);

                if (false === $job_key) {
                    break;
                }

                $job_json = $this->getJob($job_key);

                if ('' === $job_json) {
                    break;
                }

                $this->execJob($job_json);

                ++$exec_count;
            }

            unset($proc_hash, $proc_key, $job_key, $job_json);
        } else {
            //Read delay jobs
            $job_list = $this->redis->zRangeByScore($this->key_slot['delay_time'], 0, time());

            if (!empty($job_list)) {
                //Seek for jobs
                foreach ($job_list as $time_rec) {
                    $time_key = (string)$time_rec;

                    while ($exec_count < $this->max_exec) {
                        $delay_job = $this->redis->rPop($this->key_slot['delay_jobs'] . $time_key);

                        //No delay job found
                        if (false === $delay_job) {
                            $this->redis->zRem($this->key_slot['delay_time'], $time_key);
                            $this->redis->hDel($this->key_slot['delay_lock'], $time_key);
                            break;
                        }

                        $job_data = json_decode($delay_job, true);

                        //Add as realtime job
                        $this->addRealtime($job_data['group'], $job_data['job']);
                    }

                    ++$exec_count;
                }
            }

            unset($job_list, $time_rec, $time_key, $delay_job, $job_data);
        }

        unset($type, $exec_count);
    }

    /**
     * Add a realtime job
     *
     * @param string $group
     * @param string $data
     *
     * @return bool
     */
    private function addRealtime(string $group, string $data): bool
    {
        $key = $this->key_slot['jobs'] . $group;
        $this->redis->sAdd($this->key_slot['listen'], $key);
        $result = $this->redis->lPush($key, $data);

        unset($group, $data, $key);
        return is_int($result) && 0 < $result;
    }

    /**
     * Add a delay job
     *
     * @param string $group
     * @param string $data
     * @param int    $time
     *
     * @return bool
     */
    private function addDelay(string $group, string $data, int $time): bool
    {
        //Calculate delay time
        $delay = time() + $time;

        //Set time lock
        $this->redis->hSet($this->key_slot['delay_lock'], $delay, $delay);

        //Add time rec
        $this->redis->zAdd($this->key_slot['delay_time'], $delay, $delay);

        //Add delay job
        $result = $this->redis->lPush(
            $this->key_slot['delay_jobs'] . (string)$delay,
            json_encode([
                'group' => &$group,
                'job'   => &$data
            ], JSON_FORMAT)
        );

        unset($group, $data, $time, $delay);
        return is_int($result) && 0 < $result;
    }

    /**
     * Get a job from queue stack
     *
     * @param string $job_key
     *
     * @return string
     */
    private function getJob(string $job_key): string
    {
        $job = $this->redis->brPop($job_key, self::WAIT_TIME);

        if (empty($job)) {
            $this->redis->sRem($this->key_slot['listen'], $job_key);
            return '';
        }

        $job_json = trim($job[1]);

        unset($job_key, $job);
        return $job_json;
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

        if (0 === $this->redis->hLen($key)) {
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
     */
    private function getLogKey(string $type): string
    {
        if (!in_array($type, ['success', 'failed'], true)) {
            $type = 'success';
        }

        return $this->key_slot[$type];
    }

    /**
     * Call delay process
     *
     * @param string $cmd
     */
    private function callDelay(string $cmd): void
    {
        pclose(popen($cmd, 'rb'));
        unset($cmd);
    }

    /**
     * Call realtime process
     *
     * @param string $cmd
     */
    private function callRealtime(string $cmd): void
    {
        //Count running processes
        $runs = count($this->getKeys($this->key_slot['watch']));

        //Calculate left jobs
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

        //Call child processes
        for ($i = 0; $i < $need; ++$i) {
            pclose(popen($cmd, 'rb'));
        }

        unset($cmd, $runs, $left, $list, $jobs, $item, $need, $i);
    }

    /**
     * Process a job
     *
     * @param string $json
     */
    private function execJob(string $json): void
    {
        //Decode data from JSON
        $data = json_decode($json, true);

        //Source data parse failed
        if (!is_array($data)) {
            $this->redis->lPush($this->key_slot['failed'], json_encode(['time' => date('Y-m-d H:i:s'), 'data' => &$json, 'return' => 'Data ERROR!'], JSON_FORMAT));
            return;
        }

        //Check job QID
        if (isset($data['QID'])) {
            $left = $this->redis->hIncrBy($this->key_slot['idx'], $data['QID'], -1);

            //Remove useless QID
            if (0 >= $left) {
                $this->redis->hDel($this->key_slot['idx'], $data['QID']);
            }

            //Skip job
            if (0 > $left) {
                return;
            }

            unset($left);
        }

        try {
            $cgi_cmd = $this->router->parseCgi($data['c']);

            if (!empty($cgi_cmd)) {
                while (is_array($cmd_data = array_shift($cgi_cmd))) {
                    $method_args = parent::buildArgs(Reflect::getMethod($cmd_data[0], $cmd_data[1])->getParameters(), $data);

                    $class_args = method_exists($cmd_data[0], '__construct')
                        ? Factory::buildArgs(Reflect::getMethod($cmd_data[0], '__construct')->getParameters(), $data)
                        : [];

                    $result = $this->caller->runMethod($cmd_data, $method_args, $class_args);

                    if (!empty($result)) {
                        //Get return data
                        $return = current($result);

                        //Build queue log
                        $log = json_encode(['time' => date('Y-m-d H:i:s'), 'data' => $this->IOData->src_input, 'return' => &$return], JSON_FORMAT);

                        //Save to queue history
                        if (true === $return) {
                            $this->redis->lPush($this->key_slot['success'], $log);
                            $this->redis->lTrim($this->key_slot['success'], 0, $this->max_hist - 1);
                        } else {
                            $this->redis->lPush($this->key_slot['failed'], $log);
                        }
                    }

                    unset($return, $log);
                }
            }

            $cli_cmd = $this->router->parseCli($data['c']);

            if (!empty($cli_cmd)) {
                while (is_array($cmd_data = array_shift($cli_cmd))) {
                    $this->caller->runProgram(
                        $cmd_data,
                        $data['argv'] ?? [],
                        $data['cwd'] ?? '',
                        $this->app->core_debug
                    );
                }
            }

            unset($cgi_cmd, $cli_cmd, $cmd_data, $method_args, $class_args, $result);
        } catch (\Throwable $throwable) {
            $this->redis->lPush($this->key_slot['failed'], json_encode(['time' => date('Y-m-d H:i:s'), 'data' => &$data, 'return' => $throwable->getMessage()], JSON_FORMAT));
            unset($throwable);
        }

        unset($json, $data);
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
        $result = true;

        //Build job unique key
        $unique_key = $this->key_slot['unique'] . $job_cmd;

        //Append defined unique id
        if ('' !== $job_hash) {
            $unique_key .= ':' . $job_hash;
        }

        //Check job with unique id
        if ($this->redis->setnx($unique_key, time() + $time_wait)) {
            $this->redis->expire($unique_key, $time_wait);
            $result = false;
        }

        unset($job_cmd, $job_hash, $time_wait, $unique_key);
        return $result;
    }
}