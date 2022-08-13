<?php

/**
 * Proc Manager library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;

class ProcMgr extends Factory
{
    public OSMgr    $OSMgr;
    public FiberMgr $fiberMgr;

    public bool $auto_create = false; //Auto create process

    public int $watch_timeout = 200000; //microseconds

    private string $command;
    private string $working_path = '';

    private array $proc_cmd;
    private array $load_list   = [];
    private array $proc_list   = [];
    private array $input_list  = [];
    private array $output_list = [];

    /**
     * @param string $command
     * @param string $working_path
     *
     * @throws \ReflectionException
     */
    public function __construct(string $command, string $working_path = '')
    {
        $this->OSMgr    = OSMgr::new();
        $this->fiberMgr = FiberMgr::new();

        $this->working_path = &$working_path;

        $this->command  = $this->OSMgr->useProfile(true)->buildCmd($command);
        $this->proc_cmd = $this->OSMgr->buildProcCmd($this->command);

        unset($command, $working_path);
    }

    /**
     * @param bool $auto_create
     *
     * @return $this
     */
    public function autoCreateProc(bool $auto_create): self
    {
        $this->auto_create = &$auto_create;

        unset($auto_create);
        return $this;
    }

    /**
     * @param int $microseconds
     *
     * @return $this
     */
    public function setWatchTimeout(int $microseconds): self
    {
        $this->watch_timeout = &$microseconds;

        unset($microseconds);
        return $this;
    }

    /**
     * @param int $proc_count
     *
     * @return $this
     * @throws \Exception
     */
    public function create(int $proc_count = 1): self
    {
        for ($i = 0; $i < $proc_count; ++$i) {
            $this->createProc($i);
        }

        register_shutdown_function([$this, 'closeAllProc']);

        unset($proc_count, $i);
        return $this;
    }

    /**
     * @param string        $argv
     * @param callable|null $callable
     *
     * @return $this
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function sendArgv(string $argv, callable $callable = null): self
    {
        $proc_idx = array_search(min($this->load_list), $this->load_list, true);

        if (false === $proc_idx || !$this->isProcAlive($proc_idx)) {
            return $this->sendArgv($argv, $callable);
        }

        ++$this->load_list[$proc_idx];

        $this->writeProc($proc_idx, $argv);

        $this->fiberMgr->async($this->fiberMgr->await([$this, 'await'], [$proc_idx]), $callable);

        unset($argv, $callable, $proc_idx);
        return $this;
    }

    /**
     * @param int $proc_idx
     *
     * @return string
     * @throws \Throwable
     */
    public function await(int $proc_idx): string
    {
        $write = $except = [];

        while (true) {
            $read = [$this->output_list[$proc_idx]];

            if (0 === (int)stream_select($read, $write, $except, 0, $this->watch_timeout)) {
                \Fiber::suspend();
            } else {
                --$this->load_list[$proc_idx];
                break;
            }
        }

        $result = trim(fgets($this->output_list[$proc_idx]));

        unset($proc_idx, $write, $except, $read);
        return $result;
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function commit(): void
    {
        $this->fiberMgr->commit();
    }

    /**
     * @return void
     */
    public function closeAllProc(): void
    {
        foreach ($this->proc_list as $proc_idx => $proc_resource) {
            $this->closeProc($proc_idx);
        }

        unset($proc_idx, $proc_resource);
    }

    /**
     * @param int $proc_idx
     *
     * @return bool
     * @throws \Exception
     */
    public function isProcAlive(int $proc_idx): bool
    {
        $proc_status = proc_get_status($this->proc_list[$proc_idx]);

        if (!$proc_status['running']) {
            $this->closeProc($proc_idx);

            if ($this->auto_create) {
                $this->createProc($proc_idx);
            }

            unset($proc_idx, $proc_status);
            return false;
        }

        unset($proc_idx, $proc_status);
        return true;
    }

    /**
     * @param int $proc_idx
     *
     * @return $this
     * @throws \Exception
     */
    public function createProc(int $proc_idx): self
    {
        $proc = proc_open(
            $this->proc_cmd,
            [
                ['pipe', 'rb'],
                ['socket', 'wb'],
                ['socket', 'wb']
            ],
            $pipes,
            '' !== $this->working_path ? $this->working_path : null
        );

        if (!is_resource($proc)) {
            throw new \Exception('Process create ERROR: "' . $this->command . '"', E_USER_ERROR);
        }

        stream_set_blocking($pipes[0], false);
        stream_set_blocking($pipes[1], false);

        fclose($pipes[2]);

        $this->load_list[$proc_idx]   = 0;
        $this->proc_list[$proc_idx]   = $proc;
        $this->input_list[$proc_idx]  = $pipes[0];
        $this->output_list[$proc_idx] = $pipes[1];

        unset($proc_idx, $proc, $pipes);
        return $this;
    }

    /**
     * @param int    $proc_idx
     * @param string $argv
     *
     * @return void
     */
    public function writeProc(int $proc_idx, string $argv): void
    {
        fwrite($this->input_list[$proc_idx], $argv . "\n");
        unset($proc_idx, $argv);
    }

    /**
     * @param int $proc_idx
     *
     * @return void
     */
    public function closeProc(int $proc_idx): void
    {
        fclose($this->input_list[$proc_idx]);
        fclose($this->output_list[$proc_idx]);
        proc_close($this->proc_list[$proc_idx]);

        unset($this->load_list[$proc_idx], $this->proc_list[$proc_idx], $this->input_list[$proc_idx], $this->output_list[$proc_idx], $proc_idx);
    }
}