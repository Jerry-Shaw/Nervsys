<?php

/**
 * Executable program Controller Extension
 *
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

use Core\Factory;

/**
 * Class libExeC
 *
 * @package Ext
 */
class libExeC extends Factory
{
    //ExeC key prefix
    const PREFIX   = 'EXEC:';
    const WORKER   = self::PREFIX . 'W';
    const STOP_CMD = 'ExecStop';

    /** @var \Redis $redis */
    public \Redis $redis;

    public int $idle_time = 3;
    public int $max_hist  = 1000;

    public string $cmd_id;
    public string $key_logs;
    public string $key_status;
    public string $key_command;

    /**
     * libExeC constructor.
     *
     * @param string $cmd_id
     */
    public function __construct(string $cmd_id)
    {
        $this->cmd_id = &$cmd_id;

        $this->key_logs    = self::PREFIX . $cmd_id . ':L';
        $this->key_status  = self::PREFIX . $cmd_id . ':S';
        $this->key_command = self::PREFIX . $cmd_id . ':C';

        unset($cmd_id);
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
     * Get process status
     *
     * @return array
     */
    public function getStatus(): array
    {
        return $this->redis->hGetAll($this->key_status);
    }

    /**
     * Set process status
     *
     * @return bool
     */
    public function setStatus(): bool
    {
        if (!$this->redis->hSetNx($this->key_status, 'start', time())) {
            return false;
        }

        $msg = 'Command started at ' . date('Y-m-d H:i:s');

        $this->redis->lPush($this->key_logs, $msg);
        $this->redis->hSet($this->key_status, 'msg', $msg);
        $this->redis->expire($this->key_status, $this->idle_time * 3);

        unset($msg);
        return true;
    }

    /**
     * Start a process
     *
     * @param array         $cmd_params
     * @param string|null   $cwd_path
     * @param callable|null $func
     *
     * @return void
     */
    public function start(array $cmd_params, string $cwd_path = null, callable $func = null): void
    {
        if (!$this->setStatus()) {
            return;
        }

        $proc = proc_open(
            $cmd_params,
            [
                ['pipe', 'rb'],
                ['socket', 'wb'],
                ['socket', 'wb']
            ],
            $pipes,
            $cwd_path
        );

        if (!is_resource($proc)) {
            return;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $proc_status = proc_get_status($proc);

        $this->redis->hSet(self::WORKER, $this->cmd_id, time());
        $this->redis->hMSet($this->key_status, ['pid' => $proc_status['pid'], 'cmd' => $proc_status['command']]);

        while (proc_get_status($proc)['running']) {
            if (is_callable($func)) {
                call_user_func($func, $this);
            }

            $this->saveLogs([$pipes[1], $pipes[2]]);
            $this->redis->expire($this->key_status, $this->idle_time * 3);

            $command = $this->redis->brPop($this->key_command, $this->idle_time);

            if (empty($command)) {
                continue;
            }

            $input = trim($command[1]);

            if ($input === self::STOP_CMD) {
                break;
            }

            fwrite($pipes[0], $input . "\n");
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_terminate($proc);
        proc_close($proc);
        $this->cleanup();

        unset($cmd_params, $cwd_path, $func, $proc, $pipes, $proc_status, $command, $input);
    }

    /**
     * Send command to process
     *
     * @param string $command
     *
     * @return void
     */
    public function run(string $command): self
    {
        if (!empty($this->getStatus())) {
            $this->redis->lPush($this->key_command, $command);
        }

        unset($command);
        return $this;
    }

    /**
     * Send stop code to controller
     *
     * @return void
     */
    public function stop(): void
    {
        if (!empty($this->getStatus())) {
            $this->redis->lPush($this->key_command, self::STOP_CMD);
        }
    }

    /**
     * Cleanup process records
     *
     * @return void
     */
    private function cleanup(): void
    {
        $this->redis->lPush($this->key_logs, 'User stopped at ' . date('Y-m-d H:i:s'));
        $this->redis->lTrim($this->key_logs, 0, $this->max_hist - 1);

        $this->redis->hDel(self::WORKER, $this->cmd_id);

        $this->redis->del($this->key_status);
        $this->redis->del($this->key_command);
    }

    /**
     * Save output/error pipe logs
     *
     * @param array $pipes
     *
     * @return void
     */
    private function saveLogs(array $pipes): void
    {
        $write = $except = [];

        if (0 === (int)stream_select($pipes, $write, $except, $this->idle_time)) {
            return;
        }

        foreach ($pipes as $pipe) {
            while (!feof($pipe)) {
                $msg = fgets($pipe);

                if (false === $msg) {
                    break;
                }

                if ('' === ($msg = trim($msg))) {
                    continue;
                }

                $this->redis->lPush($this->key_logs, $msg);
                $this->redis->lTrim($this->key_logs, 0, $this->max_hist - 1);
                $this->redis->hSet($this->key_status, 'msg', $msg);
            }
        }

        unset($pipes, $write, $except, $pipe, $msg);
    }
}