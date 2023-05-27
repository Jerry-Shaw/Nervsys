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
use Nervsys\Core\Lib\Caller;
use Nervsys\Core\Lib\Error;
use Nervsys\Core\Lib\Router;

class ProcMgr extends Factory
{
    const P_STDIN  = 1;
    const P_STDOUT = 2;
    const P_STDERR = 4;

    public Error $error;

    public array $proc_cmd;

    public array $read_at = [0, 20000];

    public string $argv_end_char = "\n";

    public string|null $work_dir = null;

    protected array $proc_list   = [];
    protected array $proc_stdin  = [];
    protected array $proc_stdout = [];
    protected array $proc_stderr = [];
    protected array $proc_status = [];

    protected array $proc_job_count = [];
    protected array $proc_callbacks = [];

    /**
     * @param string ...$proc_cmd
     *
     * @throws \ReflectionException
     */
    public function __construct(string ...$proc_cmd)
    {
        $this->error    = Error::new();
        $this->proc_cmd = &$proc_cmd;

        unset($proc_cmd);
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
     * @param int $idx
     *
     * @return $this
     * @throws \Exception
     */
    public function runProc(int $idx = 0): self
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

        $this->proc_list[$idx]   = $proc;
        $this->proc_stdin[$idx]  = $pipes[0];
        $this->proc_stdout[$idx] = $pipes[1];
        $this->proc_stderr[$idx] = $pipes[2];
        $this->proc_status[$idx] = self::P_STDIN | self::P_STDOUT | self::P_STDERR;

        $this->proc_job_count[$idx] = 0;
        $this->proc_callbacks[$idx] = [];

        register_shutdown_function([$this, 'close'], $idx);

        unset($idx, $proc, $pipes);
        return $this;
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
     * @return string
     */
    public function getCmd(): string
    {
        return implode(' ', $this->proc_cmd);
    }

    /**
     * @param int $idx
     *
     * @return int
     */
    public function getStatus(int $idx = 0): int
    {
        $proc_status = proc_get_status($this->proc_list[$idx]);

        if (!$proc_status['running'] && self::P_STDIN === ($this->proc_status[$idx] & self::P_STDIN)) {
            $this->proc_status[$idx] ^= self::P_STDIN;
        }

        $stdout_status = stream_get_meta_data($this->proc_stdout[$idx]);

        if ($stdout_status['eof'] && self::P_STDOUT === ($this->proc_status[$idx] & self::P_STDOUT)) {
            $this->proc_status[$idx] ^= self::P_STDOUT;
        }

        $stderr_status = stream_get_meta_data($this->proc_stderr[$idx]);

        if ($stderr_status['eof'] && self::P_STDERR === ($this->proc_status[$idx] & self::P_STDERR)) {
            $this->proc_status[$idx] ^= self::P_STDERR;
        }

        unset($proc_status, $stdout_status, $stderr_status);
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
     * @param string        $argv
     * @param callable|null $msg_callback
     * @param callable|null $err_callback
     *
     * @return void
     * @throws \Exception
     */
    public function putArgv(string $argv, callable $msg_callback = null, callable $err_callback = null): void
    {
        $idx = $this->getRunningIdx();

        ++$this->proc_job_count[$idx];
        fwrite($this->proc_stdin[$idx], $argv . $this->argv_end_char);
        array_unshift($this->proc_callbacks[$idx], [$msg_callback, $err_callback]);

        unset($argv, $msg_callback, $err_callback, $idx);
    }

    /**
     * @return void
     * @throws \ReflectionException
     */
    public function awaitJobs(): void
    {
        while (0 < array_sum($this->proc_job_count)) {
            $this->readIo();
            $this->cleanup();
        }
    }

    /**
     * @param callable|null $msg_callback
     * @param callable|null $err_callback
     *
     * @return void
     * @throws \ReflectionException
     */
    public function awaitProc(callable $msg_callback = null, callable $err_callback = null): void
    {
        while (!empty($this->proc_status)) {
            $this->readIo([$msg_callback, $err_callback]);
            $this->cleanup();
        }

        unset($msg_callback, $err_callback);
    }

    /**
     * @param int $idx
     *
     * @return void
     */
    public function close(int $idx = 0): void
    {
        if (isset($this->proc_status[$idx])) {
            fclose($this->proc_stdin[$idx]);
            fclose($this->proc_stdout[$idx]);
            fclose($this->proc_stderr[$idx]);
            proc_close($this->proc_list[$idx]);
        }

        unset($this->proc_list[$idx], $this->proc_stdin[$idx], $this->proc_stdout[$idx], $this->proc_stderr[$idx], $this->proc_job_count[$idx], $this->proc_callbacks[$idx], $idx);
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
     * @param int $jobs
     *
     * @return void
     * @throws \ReflectionException
     */
    public function worker(int $jobs = 2000): void
    {
        $do = 0;

        $caller = Caller::new();
        $router = Router::new();

        while (++$do <= $jobs) {
            $job_json = fgets(STDIN);

            if (false === $job_json) {
                return;
            }

            $job_json = trim($job_json);
            $job_data = json_decode($job_json, true);

            try {
                if (!is_array($job_data) || !isset($job_data['c']) || empty($c_list = $router->parseCgi($job_data['c']))) {
                    throw new \Exception('Proc worker data ERROR: ' . $job_json, E_USER_NOTICE);
                }
            } catch (\Throwable $throwable) {
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
                echo "\n";
                continue;
            }

            $result = [];

            while (is_array($cmd_data = array_shift($c_list))) {
                try {
                    $result += $caller->runApiFn($cmd_data, $job_data);
                } catch (\Throwable $throwable) {
                    $this->error->exceptionHandler($throwable, false, false);
                    unset($throwable);
                }
            }

            echo json_encode(1 === count($result) ? current($result) : $result, JSON_FORMAT) . "\n";

            unset($job_json, $job_data, $c_list, $result, $cmd_data);
        }

        unset($jobs, $do, $caller, $router);
    }

    /**
     * @param array $proc_callbacks
     *
     * @return void
     * @throws \ReflectionException
     */
    protected function readIo(array $proc_callbacks = []): void
    {
        $stdout_data = $this->readLine($this->proc_stdout);
        $stderr_data = $this->readLine($this->proc_stderr);

        $stream_data = $stdout_data + $stderr_data;

        foreach ($stream_data as $idx => $io_data) {
            $job_callbacks = array_pop($this->proc_callbacks[$idx]);

            if (0 > --$this->proc_job_count[$idx]) {
                $this->proc_job_count[$idx] = 0;
            }

            if (isset($stdout_data[$idx])) {
                $this->callIoFn($stdout_data[$idx], [$job_callbacks[0] ?? null, $proc_callbacks[0] ?? null]);
            }

            if (isset($stderr_data[$idx])) {
                $this->callIoFn($stderr_data[$idx], [$job_callbacks[1] ?? null, $proc_callbacks[1] ?? null]);
            }
        }

        unset($proc_callbacks, $stdout_data, $stderr_data, $stream_data, $idx, $io_data, $job_callbacks);
    }

    /**
     * @param string $output
     * @param array  $callbacks
     *
     * @return void
     * @throws \ReflectionException
     */
    protected function callIoFn(string $output, array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            if (!is_callable($callback)) {
                continue;
            }

            try {
                call_user_func($callback, $output);
            } catch (\Throwable $throwable) {
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }
        }

        unset($output, $callbacks, $callback);
    }

    /**
     * @return void
     */
    protected function cleanup(): void
    {
        foreach ($this->proc_status as $idx => $status) {
            if (0 === $this->getStatus($idx)) {
                $this->close($idx);
            }
        }

        unset($idx, $status);
    }

    /**
     * @param array $stream_list
     *
     * @return array
     */
    protected function readLine(array $stream_list): array
    {
        $result = $write = $except = [];

        if (0 < stream_select($stream_list, $write, $except, $this->read_at[0], $this->read_at[1])) {
            foreach ($stream_list as $idx => $stream) {
                $result[$idx] = trim(fgets($stream));
                $this->getStatus($idx);
            }
        }

        unset($stream_list, $write, $except, $idx, $stream);
        return $result;
    }

    /**
     * @return int
     * @throws \Exception
     */
    protected function getRunningIdx(): int
    {
        $idx    = key($this->proc_status);
        $status = $this->getStatus($idx);

        switch ($status) {
            case self::P_STDIN:
            case self::P_STDIN | self::P_STDOUT:
            case self::P_STDIN | self::P_STDERR:
            case self::P_STDIN | self::P_STDOUT | self::P_STDERR:
                break;
            case 0:
                $this->close($idx);
                $this->runProc($idx);
                break;
            default:
                $idx !== array_key_last($this->proc_status) ? next($this->proc_status) : reset($this->proc_status);
                $idx = $this->getRunningIdx();
                break;
        }

        $idx !== array_key_last($this->proc_status) ? next($this->proc_status) : reset($this->proc_status);

        unset($status);
        return $idx;
    }
}