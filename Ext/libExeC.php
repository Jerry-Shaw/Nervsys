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
use Core\OSUnit;

/**
 * Class libExeC
 *
 * @package Ext
 */
class libExeC extends Factory
{
    //ExeC key prefix
    const PREFIX = 'EXEC:';

    /** @var \Redis $redis */
    public \Redis $redis;

    public int $idle_time   = 10;
    public int $max_hist    = 1000;
    public int $status_life = 86400;

    public string $stop_cmd = 'STOP';

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
     * @param string $cmd
     *
     * @return bool
     */
    public function setStatus(string $cmd): bool
    {
        if (!$this->redis->hSetNx($this->key_status, 'cmd', $cmd)) {
            return false;
        }

        $msg = 'Command started at ' . date('Y-m-d H:i:s');

        $this->redis->lPush($this->key_logs, $msg);
        $this->redis->hMSet($this->key_status, ['start' => time(), 'last_msg' => $msg]);
        $this->redis->expire($this->key_status, $this->status_life);

        unset($cmd, $msg);
        return true;
    }

    /**
     * Start a process
     *
     * @param string      $cmd
     * @param string|null $cwd
     *
     * @return void
     */
    public function start(string $cmd, string $cwd = null): void
    {
        if (!$this->setStatus($cmd)) {
            return;
        }

        $proc = proc_open(
            OSUnit::new()->setCmd($cmd)->setEnvPath()->fetchCmd(),
            [
                ['pipe', 'rb'],
                ['pipe', 'wb'],
                ['pipe', 'wb']
            ],
            $pipes,
            $cwd
        );

        if (!is_resource($proc)) {
            return;
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (true) {
            $this->saveLogs([$pipes[1], $pipes[2]]);

            if (0 === $this->redis->lLen($this->key_command)) {
                continue;
            }

            $command = $this->redis->brPop($this->key_command, $this->idle_time);

            if (empty($command)) {
                continue;
            }

            $input = trim($command[1]);

            if ($input === $this->stop_cmd) {
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                proc_close($proc);
                $this->cleanup();

                break;
            }

            fwrite($pipes[0], $input . "\n");
        }

        unset($cmd, $cwd, $proc, $pipes, $command, $input, $pipe);
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
        $this->redis->lPush($this->key_command, $command);

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
        $this->redis->lPush($this->key_command, $this->stop_cmd);
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
        foreach ($pipes as $pipe) {
            while (!feof($pipe)) {
                $msg = fgets($pipe);

                if (false === $msg || '' === trim($msg)) {
                    continue;
                }

                $this->redis->lPush($this->key_logs, $msg);
                $this->redis->lTrim($this->key_logs, 0, $this->max_hist - 1);
                $this->redis->hSet($this->key_status, 'last_msg', $msg);
            }
        }

        unset($pipes, $pipe, $msg);
    }
}