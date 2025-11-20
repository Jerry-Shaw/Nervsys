<?php

/**
 * Queue Extension (on Redis)
 *
 * Copyright 2016-2025 秋水之冰 <27206617@qq.com>
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
use Nervsys\Core\Lib\App;
use Nervsys\Core\Lib\Error;
use Nervsys\Core\Lib\Router;
use Nervsys\Core\Lib\Security;
use Nervsys\Core\Mgr\OSMgr;
use Nervsys\Core\Mgr\ProcMgr;
use Nervsys\Core\Reflect;

class libQueue extends Factory
{
    public \Redis|libRedis $redis;

    private array $proc_redis_conf;

    private string $proc_worker_key;

    private string $queue_name;
    private string $QProc_key;
    private string $realtime_key;
    private string $delay_set_key;
    private string $delay_job_key;
    private string $error_log_key;
    private string $unique_hash_key;

    /**
     * @param string $name
     */
    public function __construct(string $name = 'default')
    {
        $this->proc_worker_key = 'Q:' . $name;

        $this->queue_name      = $name;
        $this->QProc_key       = $this->proc_worker_key . ':QProc';
        $this->realtime_key    = $this->proc_worker_key . ':realtime';
        $this->delay_set_key   = $this->proc_worker_key . ':delaySet';
        $this->delay_job_key   = $this->proc_worker_key . ':delayJob:';
        $this->error_log_key   = $this->proc_worker_key . ':errorList';
        $this->unique_hash_key = $this->proc_worker_key . ':uniqueJob:';

        unset($name);
    }

    /**
     * @param array $redis_conf
     *
     * @return $this
     * @throws \RedisException
     * @throws \ReflectionException
     */
    public function setRedisConf(array $redis_conf): self
    {
        $this->proc_redis_conf = $redis_conf;

        $this->redis = libRedis::new($redis_conf)->connect();

        unset($redis_conf);
        return $this;
    }

    /**
     * @param string $cmd
     * @param array  $data
     * @param string $unique_hash
     * @param int    $unique_ttl
     *
     * @return int
     * @throws \RedisException
     */
    public function addRealtime(string $cmd, array $data, string $unique_hash = '', int $unique_ttl = 60): int
    {
        $pass_unique = '' === $unique_hash || $this->passUnique($unique_hash, $unique_ttl);

        $result = $pass_unique ? $this->saveQJob($this->realtime_key, $cmd, $data, $unique_hash) : -1;

        unset($cmd, $data, $unique_hash, $unique_ttl, $pass_unique);
        return $result;
    }

    /**
     * @param string $cmd
     * @param array  $data
     * @param int    $run_at
     * @param string $unique_hash
     * @param int    $unique_ttl
     *
     * @return int
     * @throws \RedisException
     */
    public function addDelay(string $cmd, array $data, int $run_at, string $unique_hash = '', int $unique_ttl = 60): int
    {
        $result = -1;

        $pass_unique = '' === $unique_hash || $this->passUnique($unique_hash, $unique_ttl);

        if ($pass_unique) {
            $this->redis->zAdd($this->delay_set_key, $run_at, $run_at);

            $result = $this->saveQJob($this->delay_job_key . $run_at, $cmd, $data, $unique_hash);
        }

        unset($cmd, $data, $run_at, $unique_hash, $unique_ttl, $pass_unique);
        return $result;
    }

    /**
     * @param int $proc_num
     * @param int $cycle_jobs
     * @param int $watch_microseconds
     *
     * @return void
     * @throws \RedisException
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function start(int $proc_num = 10, int $cycle_jobs = 2000, int $watch_microseconds = 20000): void
    {
        if (false === $this->redis->setnx($this->proc_worker_key, date('Y-m-d H:i:s'))) {
            exit('Queue "' . $this->queue_name . '" already running!');
        }

        register_shutdown_function(function (string $worker_key): void
        {
            $this->redis->del($worker_key);
            unset($worker_key);
        }, $this->proc_worker_key);

        $app     = App::new();
        $OSMgr   = OSMgr::new();
        $procMgr = ProcMgr::new();

        $procMgr->setWorkDir(dirname($app->script_path))
            ->command([
                $OSMgr->getPhpPath(),
                $app->script_path,
                '-c', '/' . __CLASS__ . '/QProc',
                '-d', json_encode(['name' => $this->queue_name, 'redis' => $this->proc_redis_conf, 'cycles' => $cycle_jobs])
            ])
            ->runMP($proc_num);

        while ($this->redis->expire($this->proc_worker_key, 30)) {
            $this->syncDelayJobs();

            $job_num  = $this->redis->lLen($this->realtime_key);
            $need_num = min($proc_num, ceil($job_num / $cycle_jobs));

            if (0 < $need_num) {
                for ($i = 0; $i < $need_num; ++$i) {
                    $procMgr->keepAlive($i);
                }
            }

            usleep($watch_microseconds);
        }
    }

    /**
     * @param array $redis
     * @param int   $cycles
     *
     * @return void
     * @throws \RedisException
     * @throws \ReflectionException
     */
    public function QProc(array $redis, int $cycles): void
    {
        $error    = Error::new();
        $router   = Router::new();
        $security = Security::new();

        $this->redis = libRedis::new($redis)->connect();

        $proc_id   = getmypid();
        $jobs_done = 0;

        $this->redis->hSet($this->QProc_key, $proc_id, date('Y-m-d H:i:s'));
        $this->redis->expire($this->QProc_key, 60);

        register_shutdown_function(function (string $QProc_key, int $proc_id): void
        {
            $this->redis->hDel($QProc_key, $proc_id);
            unset($QProc_key, $proc_id);
        }, $this->QProc_key, $proc_id);

        while (!empty($job = $this->redis->brPop([$this->realtime_key], 10))) {
            $this->redis->expire($this->QProc_key, 60);

            $job_data = json_decode($job[1], true);

            try {
                if (!is_array($job_data)) {
                    $job_data = [$job[1]];
                    throw new \Exception('Queue data ERROR!', E_USER_NOTICE);
                }

                if (isset($job_data['!'])) {
                    $unique_key = $this->getUniqueKey($job_data['!']);

                    if (-1 === $this->redis->ttl($unique_key)) {
                        $this->redis->del($unique_key);
                    }
                }

                if (!isset($job_data['@']) || empty($cmd = $router->parseCgi($job_data['@']))) {
                    throw new \Exception('Queue CMD ERROR!', E_USER_NOTICE);
                }
            } catch (\Throwable $throwable) {
                $this->saveError($job[0], $job_data, $throwable->getMessage());
                unset($throwable);
                continue;
            }

            try {
                if (Security::class === $cmd[0]) {
                    throw new \Exception('Queue CMD ERROR, redirected to: "' . $cmd[0] . '/' . $cmd[1] . '"', E_USER_NOTICE);
                }

                $resource = $security->getApiResource($cmd[0], $cmd[1], $job_data, \ReflectionMethod::IS_PUBLIC);
                $api_args = parent::buildArgs(Reflect::getCallable($resource['api'])->getParameters(), $resource['args']);

                call_user_func($resource['api'], ...$api_args);
            } catch (\Throwable $throwable) {
                $this->saveError($job[0], $job_data, $throwable->getMessage());
                $error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }

            if (++$jobs_done >= $cycles) {
                break;
            }
        }
    }

    /**
     * @param string $unique_hash
     *
     * @return string
     */
    private function getUniqueKey(string $unique_hash): string
    {
        return $this->unique_hash_key . $unique_hash;
    }

    /**
     * @param string $key
     * @param string $cmd
     * @param array  $data
     * @param string $hash
     *
     * @return int
     * @throws \RedisException
     */
    private function saveQJob(string $key, string $cmd, array $data, string $hash): int
    {
        $data['@'] = $cmd;

        if ('' !== $hash) {
            $data['!'] = $hash;
        }

        $job_length = (int)$this->redis->lPush($key, json_encode($data, JSON_FORMAT));

        unset($key, $cmd, $data, $hash);
        return $job_length;
    }

    /**
     * @param string $job_key
     * @param array  $job_data
     * @param string $error_msg
     *
     * @return void
     * @throws \RedisException
     */
    private function saveError(string $job_key, array $job_data, string $error_msg): void
    {
        $error_data = [
            'key'       => $job_key,
            'time'      => date('Y-m-d H:i:s'),
            'job_data'  => $job_data,
            'error_msg' => $error_msg
        ];

        $this->redis->lPush($this->error_log_key, json_encode($error_data, JSON_FORMAT));

        unset($job_key, $job_data, $error_msg, $error_data);
    }

    /**
     * @return void
     * @throws \RedisException
     */
    private function syncDelayJobs(): void
    {
        $job_key_list = $this->redis->zRangeByScore($this->delay_set_key, 0, time());

        if (empty($job_key_list)) {
            return;
        }

        foreach ($job_key_list as $timestamp) {
            $job_key = $this->delay_job_key . $timestamp;

            while (false !== ($job_data = $this->redis->rPop($job_key))) {
                $this->redis->lPush($this->realtime_key, $job_data);
            }

            $this->redis->zRem($this->delay_set_key, $timestamp);
        }

        unset($job_key_list, $timestamp, $job_key, $job_data);
    }

    /**
     * @param string $unique_hash
     * @param int    $unique_ttl
     *
     * @return bool
     * @throws \RedisException
     */
    private function passUnique(string $unique_hash, int $unique_ttl): bool
    {
        $unique_key = $this->getUniqueKey($unique_hash);

        if (false === $this->redis->setnx($unique_key, date('Y-m-d H:i:s', time() + $unique_ttl))) {
            return false;
        }

        if (0 < $unique_ttl) {
            $this->redis->expire($unique_key, $unique_ttl);
        }

        unset($unique_hash, $unique_ttl, $unique_key);
        return true;
    }
}