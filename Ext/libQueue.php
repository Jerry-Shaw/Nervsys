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

use Nervsys\Core\Factory;
use Nervsys\Core\Mgr\OSMgr;

class libQueue extends Factory
{
    const WORKER_KEY = 'Q:';

    public \Redis $redis;

    private string $worker_key;
    private string $realtime_key;
    private string $delay_set_key;
    private string $delay_job_key;
    private string $unique_hash_key;

    /**
     * @param string $queue_name
     */
    public function __construct(string $queue_name = 'default')
    {
        $this->worker_key = self::WORKER_KEY . $queue_name;

        $this->realtime_key    = $this->worker_key . ':realtime';
        $this->delay_set_key   = $this->worker_key . ':delaySet';
        $this->delay_job_key   = $this->worker_key . ':delayJob:';
        $this->unique_hash_key = $this->worker_key . ':uniqueJob:';

        unset($queue_name);
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
     * @param int $proc_num
     *
     * @return void
     * @throws \RedisException
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function start(int $proc_num = 10): void
    {
        if (false === $this->redis->setnx($this->worker_key, date('Y-m-d H:i:s'))) {
            exit('Already started!');
        }

        $this->redis->expire($this->worker_key, 60);

        register_shutdown_function(function (string $worker_key): void
        {
            $this->redis->del($worker_key);
            unset($worker_key);
        }, $this->worker_key);

        $libMPC = libMPC::new()->create(OSMgr::new()->getPhpPath(), $proc_num);

        while ($this->redis->exists($this->worker_key) && $this->redis->expire($this->worker_key, 60)) {
            $this->callDelayJobs($this->delay_set_key, $this->delay_job_key);

            $job_num  = $proc_num - 1;
            $job_data = $this->redis->brPop([$this->realtime_key], 30);

            if (empty($job_data)) {
                continue;
            }

            $this->sendJob($libMPC, $job_data[1]);

            while (0 < $job_num && false !== ($job_json = $this->redis->rPop($this->realtime_key))) {
                --$job_num;
                $this->sendJob($libMPC, $job_json);
            }

            $libMPC->commit();
        }

        unset($proc_num, $libMPC, $job_num, $job_data, $job_json);
    }

    /**
     * @param string $cmd
     * @param array  $data
     *
     * @return int
     * @throws \RedisException
     */
    public function addRealtime(string $cmd, array $data): int
    {
        $result = $this->addJob($this->realtime_key, $cmd, $data);

        unset($cmd, $data);
        return $result;
    }

    /**
     * @param string $cmd
     * @param array  $data
     * @param string $unique_hash
     * @param int    $bypass_after
     *
     * @return int
     * @throws \RedisException
     */
    public function addRealtimeUnique(string $cmd, array $data, string $unique_hash, int $bypass_after): int
    {
        $result = -1;

        if ($this->addUniqueHash($this->unique_hash_key, $unique_hash, $bypass_after)) {
            $result = $this->addRealtime($cmd, $data);
        }

        unset($cmd, $data, $unique_hash, $bypass_after);
        return $result;
    }

    /**
     * @param string $cmd
     * @param array  $data
     * @param int    $run_at
     *
     * @return int
     * @throws \RedisException
     */
    public function addDelay(string $cmd, array $data, int $run_at): int
    {
        $this->redis->zAdd($this->delay_set_key, $run_at, $run_at);

        $result = $this->addJob($this->delay_job_key . $run_at, $cmd, $data);

        unset($cmd, $data, $run_at);
        return $result;
    }

    /**
     * @param string $cmd
     * @param array  $data
     * @param int    $run_at
     * @param string $unique_hash
     * @param int    $bypass_after
     *
     * @return int
     * @throws \RedisException
     */
    public function addDelayUnique(string $cmd, array $data, int $run_at, string $unique_hash, int $bypass_after): int
    {
        $result = -1;

        if ($this->addUniqueHash($this->unique_hash_key, $unique_hash, $bypass_after)) {
            $result = $this->addDelay($cmd, $data, $run_at);
        }

        unset($cmd, $data, $run_at, $unique_hash, $bypass_after);
        return $result;
    }

    /**
     * @param string $key
     * @param string $cmd
     * @param array  $data
     *
     * @return int
     * @throws \RedisException
     */
    private function addJob(string $key, string $cmd, array $data): int
    {
        $data['@']  = &$cmd;
        $job_length = (int)$this->redis->lPush($key, json_encode($data, JSON_FORMAT));

        unset($key, $cmd, $data);
        return $job_length;
    }

    /**
     * @param string $key
     * @param string $unique_hash
     * @param int    $bypass_after
     *
     * @return bool
     * @throws \RedisException
     */
    private function addUniqueHash(string $key, string $unique_hash, int $bypass_after): bool
    {
        if (false === $this->redis->setnx($key, $unique_hash)) {
            return false;
        }

        $this->redis->expire($key, $bypass_after);

        unset($key, $unique_hash, $bypass_after);
        return true;
    }

    /**
     * @param string $delay_set_key
     * @param string $delay_job_key
     *
     * @return void
     * @throws \RedisException
     */
    private function callDelayJobs(string $delay_set_key, string $delay_job_key): void
    {
        $job_key_list = $this->redis->zRangeByScore($delay_set_key, 0, time());

        if (empty($job_key_list)) {
            return;
        }

        foreach ($job_key_list as $timestamp) {
            $job_key = $delay_job_key . $timestamp;

            while (false !== ($delay_job = $this->redis->rPop($job_key))) {
                $job_data = json_decode($delay_job, true);

                if (is_array($job_data) && isset($job_data['@'])) {
                    $this->addRealtime($job_data['@'], $job_data);
                }
            }

            $this->redis->zRem($delay_set_key, $timestamp);
        }

        unset($delay_set_key, $delay_job_key, $job_key_list, $timestamp, $job_key, $delay_job, $job_data);
    }

    /**
     * @param libMPC $libMPC
     * @param string $job_json
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    private function sendJob(libMPC $libMPC, string $job_json): void
    {
        $job_data = json_decode($job_json, true);

        if (is_array($job_data) && isset($job_data['@'])) {
            $libMPC->sendCMD($job_data['@'], $job_data);
        }

        unset($libMPC, $job_json, $job_data);
    }
}