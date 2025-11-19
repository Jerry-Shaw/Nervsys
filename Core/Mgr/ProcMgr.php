<?php

/**
 * Proc Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\Caller;
use Nervsys\Core\Lib\Error;
use Nervsys\Core\Lib\Router;

class ProcMgr extends Factory
{
    const P_STDIN  = 1;
    const P_STDOUT = 2;
    const P_STDERR = 4;

    public Error $error;

    public array $command;

    public int $read_seconds        = 0;
    public int $read_microseconds   = 50000;
    public int $proc_max_executions = 2000;

    public string $argv_end_char = "\n";

    public string|null $work_dir = null;

    protected array $proc_pid      = [];
    protected array $proc_list     = [];
    protected array $proc_idle     = [];
    protected array $proc_stdin    = [];
    protected array $proc_stdout   = [];
    protected array $proc_stderr   = [];
    protected array $proc_status   = [];
    protected array $proc_job_done = [];

    protected array $proc_job_await = [];
    protected array $proc_callbacks = [];

    /**
     * @param array $command
     *
     * @throws \ReflectionException
     */
    public function __construct(array $command)
    {
        $this->error   = Error::new();
        $this->command = $command;

        unset($command);
    }

    /**
     * @param string $end_char
     *
     * @return $this
     */
    public function setArgvEndChar(string $end_char): self
    {
        $this->argv_end_char = $end_char;

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
        $this->work_dir = $working_path;

        unset($working_path);
        return $this;
    }

    /**
     * @param int      $seconds
     * @param int|null $microseconds
     *
     * @return $this
     */
    public function readAt(int $seconds, int|null $microseconds = null): self
    {
        $this->read_seconds      = $seconds;
        $this->read_microseconds = $microseconds;

        unset($seconds, $microseconds);
        return $this;
    }

    /**
     * @param int $idx
     *
     * @return $this
     * @throws \Exception
     */
    public function runProc(int $idx = 0): self
    {
        try {
            $proc = proc_open(
                $this->command,
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
        } catch (\Throwable) {
            throw new \Exception('Failed to open "' . $this->getCmd() . '"', E_USER_ERROR);
        }

        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $this->proc_idle['P' . $idx] = $idx;

        $this->proc_pid[$idx]    = proc_get_status($proc)['pid'];
        $this->proc_list[$idx]   = $proc;
        $this->proc_stdin[$idx]  = $pipes[0];
        $this->proc_stdout[$idx] = $pipes[1];
        $this->proc_stderr[$idx] = $pipes[2];
        $this->proc_status[$idx] = self::P_STDIN | self::P_STDOUT | self::P_STDERR;

        $this->proc_job_done[$idx]  = 0;
        $this->proc_job_await[$idx] = 0;
        $this->proc_callbacks[$idx] = [];

        register_shutdown_function([$this, 'close'], $idx);

        unset($idx, $proc, $pipes);
        return $this;
    }

    /**
     * @param int $run_proc
     * @param int $max_executions
     *
     * @return $this
     * @throws \Exception
     */
    public function runPLB(int $run_proc = 8, int $max_executions = 2000): self
    {
        $this->proc_max_executions = $max_executions;

        for ($i = 0; $i < $run_proc; ++$i) {
            $this->runProc($i);
        }

        unset($run_proc, $max_executions, $i);
        return $this;
    }

    /**
     * @return string
     */
    public function getCmd(): string
    {
        return implode(' ', $this->command);
    }

    /**
     * @param int $idx
     *
     * @return int
     */
    public function getPid(int $idx = 0): int
    {
        return $this->proc_pid[$idx];
    }

    /**
     * @param int $idx
     *
     * @return int
     */
    public function getStatus(int $idx = 0): int
    {
        if (0 === $this->proc_status[$idx]) {
            return 0;
        }

        if ($this->proc_job_done[$idx] >= $this->proc_max_executions) {
            if (0 === $this->proc_job_await[$idx]) {
                $this->proc_status[$idx] = 0;
                return 0;
            }

            if (self::P_STDIN === ($this->proc_status[$idx] & self::P_STDIN)) {
                $this->proc_status[$idx] ^= self::P_STDIN;
            }
        }

        $proc_status = proc_get_status($this->proc_list[$idx]);

        if (!$proc_status['running'] && self::P_STDIN === ($this->proc_status[$idx] & self::P_STDIN)) {
            $this->proc_status[$idx] ^= self::P_STDIN;
        }

        $this->syncProcStatus($idx, self::P_STDOUT, $proc_status['running'], stream_get_meta_data($this->proc_stdout[$idx]));
        $this->syncProcStatus($idx, self::P_STDERR, $proc_status['running'], stream_get_meta_data($this->proc_stderr[$idx]));

        unset($proc_status);
        return $this->proc_status[$idx];
    }

    /**
     * @param int $idx
     *
     * @return void
     * @throws \Exception
     */
    public function keepAlive(int $idx = 0): void
    {
        if (0 === $this->getStatus($idx)) {
            $this->close($idx);
            $this->runProc($idx);
        }

        unset($idx);
    }

    /**
     * @param string        $job_argv
     * @param callable|null $stdout_callback
     * @param callable|null $stderr_callback
     *
     * @return self
     */
    public function putJob(string $job_argv, callable|null $stdout_callback = null, callable|null $stderr_callback = null): self
    {
        try {
            $idx = $this->getIdleProcIdx();

            fwrite($this->proc_stdin[$idx], $job_argv . $this->argv_end_char);
            array_unshift($this->proc_callbacks[$idx], [$stdout_callback, $stderr_callback]);

            $this->proc_job_await[$idx] = 1;

            unset($idx);
        } catch (\Throwable) {
            $this->putJob($job_argv, $stdout_callback, $stderr_callback);
        }

        unset($job_argv, $stdout_callback, $stderr_callback);
        return $this;
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function awaitJobs(): void
    {
        while (in_array(1, $this->proc_job_await, true)) {
            $this->readIPC();
        }
    }

    /**
     * @param callable|null $stdout_callback
     * @param callable|null $stderr_callback
     * @param callable|null ...$other_callbacks
     *
     * @return void
     * @throws \ReflectionException
     */
    public function awaitProc(callable|null $stdout_callback = null, callable|null $stderr_callback = null, callable|null ...$other_callbacks): void
    {
        $idx = key($this->proc_pid);

        while (0 < $this->getStatus($idx)) {
            foreach ($other_callbacks as $callback) {
                try {
                    $argv = call_user_func($callback);

                    if (is_string($argv) && '' !== $argv) {
                        fwrite($this->proc_stdin[$idx], $argv . $this->argv_end_char);
                    }
                } catch (\Throwable $throwable) {
                    $this->error->exceptionHandler($throwable, false, false);
                    unset($throwable);
                }
            }

            $this->readIPC($stdout_callback, $stderr_callback);
        }

        unset($stdout_callback, $stderr_callback, $other_callbacks, $idx, $callback, $argv);
    }

    /**
     * @param int $idx
     *
     * @return void
     */
    public function close(int $idx = 0): void
    {
        if (isset($this->proc_list[$idx])) {
            fclose($this->proc_stdin[$idx]);
            fclose($this->proc_stdout[$idx]);
            fclose($this->proc_stderr[$idx]);
            proc_close($this->proc_list[$idx]);
        }

        unset($this->proc_list[$idx], $this->proc_stdin[$idx], $this->proc_stdout[$idx], $this->proc_stderr[$idx], $idx);
    }

    /**
     * @return void
     */
    public function cleanup(): void
    {
        foreach ($this->proc_status as $idx => $status) {
            if (0 === $this->getStatus($idx)) {
                $this->close($idx);
            }
        }

        unset($idx, $status);
    }

    /**
     * @return void
     */
    public function exit(): void
    {
        foreach ($this->proc_status as $idx => $status) {
            $this->close($idx);
        }

        unset($idx, $status);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function worker(): void
    {
        $caller = Caller::new();
        $router = Router::new();

        while (true) {
            $job_json = fgets(STDIN);

            if (false === $job_json) {
                break;
            }

            $job_json = trim($job_json);
            $job_data = json_decode($job_json, true);

            try {
                if (!is_array($job_data) || !isset($job_data['c']) || empty($cmd = $router->parseCgi($job_data['c']))) {
                    throw new \Exception('Process worker data ERROR: ' . $job_json, E_USER_NOTICE);
                }
            } catch (\Throwable $throwable) {
                $this->error->exceptionHandler($throwable, false, false);
                echo $throwable->getMessage() . "\n";
                unset($throwable);
                continue;
            }

            $result = [];

            try {
                $result = $caller->runApiFn($cmd, $job_data, false);
            } catch (\Throwable $throwable) {
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }

            echo json_encode($result, JSON_FORMAT) . "\n";

            unset($job_json, $job_data, $cmd, $result);
        }

        unset($caller, $router);
        exit(0);
    }

    /**
     * @param callable|null ...$stdio_callbacks
     *
     * @return void
     * @throws \ReflectionException
     */
    protected function readIPC(callable|null ...$stdio_callbacks): void
    {
        $write   = [];
        $except  = [];
        $streams = [];

        foreach ($this->proc_stdout as $key => $value) {
            $streams['out_' . $key] = $value;
        }

        foreach ($this->proc_stderr as $key => $value) {
            $streams['err_' . $key] = $value;
        }

        if (0 < stream_select($streams, $write, $except, $this->read_seconds, $this->read_microseconds)) {
            foreach ($streams as $key => $stream) {
                [$type, $idx] = explode('_', $key, 2);

                $idx = (int)$idx;

                while ('' !== ($output = trim(fgets($stream)))) {
                    if (empty($stdio_callbacks)) {
                        ++$this->proc_job_done[$idx];
                        $stdio_callbacks = array_pop($this->proc_callbacks[$idx]);
                    }

                    if (is_array($stdio_callbacks)) {
                        $this->callFn($output, 'out' === $type ? $stdio_callbacks[0] : $stdio_callbacks[1]);
                    }
                }

                $this->proc_job_await[$idx]  = 0;
                $this->proc_idle['P' . $idx] = $idx;
            }
        }

        unset($stdio_callbacks, $write, $except, $streams, $key, $value, $stream, $type, $idx, $output);
    }

    /**
     * @param string        $output
     * @param callable|null $callback
     *
     * @return void
     * @throws \ReflectionException
     */
    protected function callFn(string $output, callable|null $callback): void
    {
        if (is_callable($callback)) {
            try {
                call_user_func($callback, $output);
            } catch (\Throwable $throwable) {
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }
        }

        unset($output, $callback);
    }

    /**
     * @param int   $idx
     * @param int   $const_def
     * @param bool  $proc_running
     * @param array $stream_status
     *
     * @return void
     */
    protected function syncProcStatus(int $idx, int $const_def, bool $proc_running, array $stream_status): void
    {
        if ($const_def === ($this->proc_status[$idx] & $const_def)) {
            if ($stream_status['eof'] || (!$proc_running && 0 === $stream_status['unread_bytes'])) {
                $this->proc_status[$idx] ^= $const_def;
            }
        }

        unset($idx, $const_def, $proc_running, $stream_status);
    }

    /**
     * @return int
     * @throws \ReflectionException
     * @throws \Exception
     */
    protected function getIdleProcIdx(): int
    {
        if (empty($this->proc_idle)) {
            $this->readIPC();

            return $this->getIdleProcIdx();
        }

        $idx    = array_shift($this->proc_idle);
        $status = $this->getStatus($idx);

        if (self::P_STDIN === ($status & self::P_STDIN)) {
            unset($status);
            return $idx;
        }

        if (0 === $status) {
            $this->close($idx);
            $this->runProc($idx);
        }

        unset($idx, $status);
        return $this->getIdleProcIdx();
    }
}