<?php

/**
 * Proc Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\Error;

class ProcMgr extends Factory
{
    public array $proc_cmd;

    public array $read_at = [0, 20000];

    protected array $proc_list      = [];
    protected array $proc_job_count = [];
    protected array $proc_callbacks = [];

    public string $argv_end_char = "\n";

    public string|null $work_dir = null;

    /**
     * @param string ...$proc_cmd
     */
    public function __construct(string ...$proc_cmd)
    {
        $this->proc_cmd = &$proc_cmd;
    }

    /**
     * @param string $end_char
     *
     * @return $this
     */
    public function setArgvEndChar(string $end_char): self
    {
        $this->argv_end_char = &$end_char;

        unset($end_char);
        return $this;
    }

    /**
     * @param string $working_path
     *
     * @return $this
     */
    public function setWorkDir(string $working_path): self
    {
        $this->work_dir = &$working_path;

        unset($working_path);
        return $this;
    }

    /**
     * @param int      $seconds
     * @param int|null $microseconds
     *
     * @return $this
     */
    public function readAt(int $seconds, int $microseconds = null): self
    {
        $this->read_at = [&$seconds, &$microseconds];

        unset($seconds, $microseconds);
        return $this;
    }

    /**
     * @return string
     */
    public function getCmd(): string
    {
        return implode(' ', $this->proc_cmd);
    }

    /**
     * @param int $idx
     *
     * @return void
     * @throws \Exception
     */
    public function runProc(int $idx = 0): void
    {
        $proc = proc_open(
            $this->proc_cmd,
            [
                ['pipe', 'rb'],
                ['socket', 'wb'],
                ['socket', 'wb']
            ],
            $pipes,
            $this->work_dir
        );

        if (!is_resource($proc)) {
            throw new \Exception('Failed to open "' . $this->getCmd() . '"', E_USER_ERROR);
        }

        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->proc_list[$idx]['stdin']  = $pipes[0];
        $this->proc_list[$idx]['stdout'] = $pipes[1];
        $this->proc_list[$idx]['stderr'] = $pipes[2];
        $this->proc_list[$idx]['proc']   = $proc;

        $this->proc_job_count[$idx] = 0;
        $this->proc_callbacks[$idx] = [];

        register_shutdown_function([$this, 'close'], $idx);

        unset($idx, $proc, $pipes);
    }

    /**
     * @param int $proc_num
     *
     * @return $this
     * @throws \Exception
     */
    public function runPLB(int $proc_num): self
    {
        for ($i = 0; $i < $proc_num; ++$i) {
            $this->runProc($i);
        }

        unset($proc_num, $i);
        return $this;
    }

    /**
     * @param int $idx
     *
     * @return bool
     */
    public function isAlive(int $idx = 0): bool
    {
        $proc_status = proc_get_status($this->proc_list[$idx]['proc']);
        $proc_alive  = $proc_status['running'] ?? false;

        unset($idx, $proc_status);
        return $proc_alive;
    }

    /**
     * @param int $idx
     *
     * @return void
     * @throws \Exception
     */
    public function keepAlive(int $idx = 0): void
    {
        if (!$this->isAlive($idx)) {
            $this->runProc($idx);
        }

        unset($idx);
    }

    /**
     * @param string        $argv
     * @param callable|null $msg_callback
     * @param callable|null $err_callback
     *
     * @return void
     * @throws \Exception
     */
    public function putMsg(string $argv, callable $msg_callback = null, callable $err_callback = null): void
    {
        $proc_idx = key($this->proc_list);

        $this->keepAlive($proc_idx);

        ++$this->proc_job_count[$proc_idx];
        array_unshift($this->proc_callbacks[$proc_idx], [$msg_callback, $err_callback]);

        fwrite($this->proc_list[$proc_idx]['stdin'], $argv . $this->argv_end_char);

        $proc_idx !== array_key_last($this->proc_list) ? next($this->proc_list) : reset($this->proc_list);

        unset($argv, $msg_callback, $err_callback, $proc_idx);
    }

    /**
     * @param bool $await
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getMsg(bool $await = false): array
    {
        $result = [];
        $Error  = Error::new();

        do {
            $stdout_list = array_column($this->proc_list, 'stdout');
            $stdout_data = $this->readStream($stdout_list);

            $stderr_list = array_column($this->proc_list, 'stderr');
            $stderr_data = $this->readStream($stderr_list);

            $stream_data = $stdout_data + $stderr_data;

            foreach ($stream_data as $idx => $io_data) {
                --$this->proc_job_count[$idx];

                $callbacks = array_pop($this->proc_callbacks[$idx]);

                if (isset($stdout_data[$idx])) {
                    $result[$idx]['stdout'] = $stdout_data[$idx];

                    if (isset($callbacks[0]) && is_callable($callbacks[0])) {
                        try {
                            call_user_func($callbacks[0], $stdout_data[$idx]);
                        } catch (\Throwable $throwable) {
                            $Error->exceptionHandler($throwable, false, false);
                            unset($throwable);
                        }
                    }
                }

                if (isset($stderr_data[$idx])) {
                    $result[$idx]['stderr'] = $stderr_data[$idx];

                    if (isset($callbacks[1]) && is_callable($callbacks[1])) {
                        try {
                            call_user_func($callbacks[1], $stderr_data[$idx]);
                        } catch (\Throwable $throwable) {
                            $Error->exceptionHandler($throwable, false, false);
                            unset($throwable);
                        }
                    }
                }
            }
        } while ($await && 0 < array_sum($this->proc_job_count));

        unset($await, $stdout_list, $stdout_data, $stderr_list, $stderr_data, $stream_data, $idx, $io_data, $callbacks);
        return $result;
    }

    /**
     * @param int $idx
     *
     * @return void
     */
    public function close(int $idx = 0): void
    {
        fclose($this->proc_list[$idx]['stdin']);
        fclose($this->proc_list[$idx]['stdout']);
        fclose($this->proc_list[$idx]['stderr']);
        proc_close($this->proc_list[$idx]['proc']);

        unset($this->proc_list[$idx], $this->proc_job_count[$idx], $this->proc_callbacks[$idx], $idx);
    }

    /**
     * @param array $stream_list
     *
     * @return array
     */
    public function readStream(array $stream_list): array
    {
        $result = $write = $except = [];

        if (0 < stream_select($stream_list, $write, $except, $this->read_at[0], $this->read_at[1])) {
            foreach ($stream_list as $idx => $stream) {
                $result[$idx] = trim(fgets($stream));
            }
        }

        unset($stream_list, $write, $except, $idx, $stream);
        return $result;
    }
}