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
use Core\Lib\App;
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
    public string $log_file_path;

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

        $log_path = App::new()->log_path . DIRECTORY_SEPARATOR . 'exec';

        try {
            !is_dir($log_path) && mkdir($log_path, 0777, true);
        } catch (\Throwable $throwable) {
            unset($throwable);
        }

        $this->log_file_path = $log_path . DIRECTORY_SEPARATOR . $this->cmd_id . '.log';

        unset($cmd_id, $log_path);
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

        $this->redis->hMSet($this->key_status, ['start' => time(), 'last_msg' => 'Command starts!']);
        $this->redis->expire($this->key_status, $this->status_life);

        unset($cmd);
        return true;
    }

    /**
     * Start a process
     *
     * @param string $cmd
     *
     * @return void
     */
    public function start(string $cmd): void
    {
        if (!$this->setStatus($cmd)) {
            return;
        }

        $proc = proc_open(
            OSUnit::new()->setCmd($cmd)->setEnvPath()->fetchCmd(),
            [
                ['pipe', 'r'],
                ['file', $this->log_file_path, 'wb'],
                ['file', $this->log_file_path, 'wb']
            ],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (false === $proc) {
            return;
        }

        $log_fp = fopen($this->log_file_path, 'rb');

        while (true) {
            while (!feof($log_fp)) {
                $this->redis->lPush($this->key_logs, trim(fgets($log_fp)));
                $this->redis->lTrim($this->key_logs, 0, $this->max_hist - 1);
            }

            if (0 === $this->redis->lLen($this->key_command)) {
                continue;
            }

            $command = $this->redis->brPop($this->key_command, $this->idle_time);

            if (empty($command)) {
                continue;
            }

            $input = trim($command[1]);

            if ($input === $this->stop_cmd) {
                fclose($log_fp);
                fclose($pipes[0]);
                proc_close($proc);

                $this->cleanup();
                break;
            }

            fwrite($pipes[0], $input . "\n");
        }

        unset($cmd, $proc, $pipes, $log_fp, $command, $input);
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

        if (is_file($this->log_file_path)) {
            unlink($this->log_file_path);
        }
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
}