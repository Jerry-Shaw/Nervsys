<?php

/**
 * Multi-Process Controller Extension
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

use Nervsys\LC\Caller;
use Nervsys\LC\Error;
use Nervsys\LC\Factory;
use Nervsys\LC\OSUnit;
use Nervsys\LC\Reflect;
use Nervsys\Lib\App;
use Nervsys\Lib\IOData;
use Nervsys\Lib\Router;

class libMPC extends Factory
{
    public App    $app;
    public Error  $error;
    public Router $router;
    public Caller $caller;
    public IOData $IOData;
    public OSUnit $OSUnit;

    public string $php_path;

    public array $proc_idx   = [];
    public array $proc_busy  = [];
    public array $proc_list  = [];
    public array $proc_await = [];

    public array $pipe_read  = [];
    public array $pipe_write = [];

    /**
     * libMPC constructor
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->app    = App::new();
        $this->error  = Error::new();
        $this->caller = Caller::new();
        $this->router = Router::new();
        $this->IOData = IOData::new();
        $this->OSUnit = OSUnit::new();
    }

    /**
     * Set PHP executable path
     *
     * @param string $php_path
     *
     * @return $this
     */
    public function setPhpPath(string $php_path): self
    {
        $this->php_path = &$php_path;

        unset($php_path);
        return $this;
    }

    /**
     * Build full command for NS API
     *
     * @param string $c
     * @param array  $data
     *
     * @return string
     */
    public function buildCmd(string $c, array $data): string
    {
        $cmd = '"' . $this->php_path . '" "' . $this->app->script_path . '" ';
        $cmd .= '-c"' . $this->IOData->encodeData($c) . '" ';

        if (isset($data['cwd'])) {
            $cmd .= '-w"' . $data['cwd'] . '" ';
            unset($data['cwd']);
        }

        if (isset($data['return'])) {
            $cmd .= '-r"' . $data['return'] . '" ';
            unset($data['return']);
        }

        $argv = '';

        if (isset($data['argv'])) {
            $argv = '-- ' . $data['argv'];
            unset($data['argv']);
        }

        if (!empty($data)) {
            $cmd .= '-d"' . $this->IOData->encodeData(json_encode($data, JSON_FORMAT)) . '" ';
        }

        $cmd .= $argv;

        unset($c, $data, $argv);
        return $cmd;
    }

    /**
     * Start MPC
     *
     * @param int $max_fork
     *
     * @return $this
     * @throws \Exception
     */
    public function start(int $max_fork = 10): self
    {
        $proc_cmd = $this->OSUnit
            ->setCmd('"' . $this->php_path . '" "' . $this->app->script_path . '" -c"/' . __CLASS__ . '/procUnit" -r"json"')
            ->setEnvPath()
            ->fetchCmd();

        //Initialize processes and pipes
        for ($i = 0; $i < $max_fork; ++$i) {
            $proc = proc_open(
                $proc_cmd,
                [
                    ['socket', 'rb'],
                    ['socket', 'wb'],
                    ['socket', 'wb']
                ],
                $pipes,
                $this->app->root_path
            );

            if (!is_resource($proc)) {
                throw new \Exception('Process initial error!', E_USER_ERROR);
            }

            stream_set_blocking($pipes[0], false);
            stream_set_blocking($pipes[1], false);

            $this->proc_idx[$i]  = 0;
            $this->proc_busy[$i] = 0;

            $this->pipe_write[$i] = $pipes[0];
            $this->pipe_read[$i]  = $pipes[1];
            $this->proc_list[$i]  = $proc;

            fclose($pipes[2]);
        }

        //Register MPC closeAll function
        register_shutdown_function([$this, 'closeAll']);

        unset($max_fork, $proc_cmd, $i, $proc, $pipes);
        return $this;
    }

    /**
     * Add MPC job
     *
     * @param string        $cmd
     * @param array         $data
     * @param callable|null $callable
     *
     * @return void
     */
    public function add(string $cmd, array $data = [], callable $callable = null): void
    {
        $data['c'] = &$cmd;
        $free_proc = $this->getFreeProc();
        $proc_idx  = array_pop($free_proc);

        if (is_callable($callable)) {
            $job_mid     = $proc_idx . dechex($this->proc_idx[$proc_idx]++);
            $data['mid'] = &$job_mid;

            $this->proc_await[$job_mid] = $callable;

            unset($job_mid);
        }

        $this->proc_busy[$proc_idx] = 1;

        fwrite($this->pipe_write[$proc_idx], json_encode($data, JSON_FORMAT) . "\n");

        unset($cmd, $data, $callable, $free_proc, $proc_idx);
    }

    /**
     * Await for all process done
     *
     * @return $this
     */
    public function await(): self
    {
        $write = $except = [];

        while (in_array(1, $this->proc_busy, true)) {
            $read = $this->pipe_read;

            if (0 === (int)stream_select($read, $write, $except, 1)) {
                continue;
            }

            $this->read($read);
        }

        unset($write, $except, $read);
        return $this;
    }

    /**
     * Close a process
     *
     * @param int $idx
     */
    public function closeProc(int $idx): void
    {
        if (is_resource($this->proc_list[$idx])) {
            fclose($this->pipe_read[$idx]);
            fclose($this->pipe_write[$idx]);

            proc_close($this->proc_list[$idx]);
        }

        unset($this->proc_list[$idx], $idx);
    }

    /**
     * Close All processes
     */
    public function closeAll(): void
    {
        foreach ($this->proc_list as $idx => $proc) {
            $this->closeProc($idx);
        }

        unset($idx, $proc);
    }

    /**
     * Daemon process unit
     */
    public function procUnit(): void
    {
        while (true) {
            //Pipe broken
            if (false === ($stdin = fgets(STDIN))) {
                return;
            }

            //Parse data
            if ('' === $stdin || !is_array($data = json_decode($stdin, true))) {
                echo "\n";
                continue;
            }

            //Execute job
            try {
                $result = $this->execJob($data);

                if (isset($data['mid'])) {
                    echo json_encode(['mid' => $data['mid'], 'data' => $result], JSON_FORMAT);
                }
            } catch (\Throwable $throwable) {
                unset($throwable);
            }

            echo "\n";

            unset($stdin, $data, $result);
        }
    }

    /**
     * Read pipe data & free process
     *
     * @param array $read
     *
     * @return void
     */
    private function read(array $read): void
    {
        foreach ($read as $idx => $pipe) {
            while (!feof($pipe)) {
                $msg = fgets($pipe);

                if (false === $msg) {
                    break;
                }

                $this->proc_busy[$idx] = 0;

                if ('' === ($msg = trim($msg))) {
                    continue;
                }

                $data = json_decode($msg, true);

                if (is_array($data) && isset($data['mid']) && is_callable($this->proc_await[$data['mid']])) {
                    call_user_func_array($this->proc_await[$data['mid']], array_values($data['data']));

                    $this->proc_await[$data['mid']] = null;
                    unset($this->proc_await[$data['mid']]);
                }
            }
        }

        unset($read, $idx, $pipe, $msg, $data);
    }

    /**
     * Execute a job
     *
     * @param array $data
     *
     * @return array
     * @throws \ReflectionException
     * @throws \Throwable
     */
    private function execJob(array $data): array
    {
        $result = [];

        try {
            $cgi_cmd = $this->router->parseCgi($data['c']);

            if (!empty($cgi_cmd)) {
                while (is_array($cmd_data = array_shift($cgi_cmd))) {
                    $method_args = parent::buildArgs(Reflect::getMethod($cmd_data[0], $cmd_data[1])->getParameters(), $data);

                    $class_args = method_exists($cmd_data[0], '__construct')
                        ? Factory::buildArgs(Reflect::getMethod($cmd_data[0], '__construct')->getParameters(), $data)
                        : [];

                    $result += $this->caller->runMethod($cmd_data, $method_args, $class_args);
                }
            }

            $cli_cmd = $this->router->parseCli($data['c']);

            if (!empty($cli_cmd)) {
                while (is_array($cmd_data = array_shift($cli_cmd))) {
                    $result += $this->caller->runProgram(
                        $cmd_data,
                        $data['argv'] ?? [],
                        $data['cwd'] ?? '',
                        $this->app->core_debug
                    );
                }
            }
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable, false, false);
        }

        unset($data, $cgi_cmd, $cli_cmd, $cmd_data, $method_args, $class_args);
        return $result;
    }

    /**
     * Get free process idx list
     *
     * @return array
     */
    private function getFreeProc(): array
    {
        $free_proc = array_keys($this->proc_busy, 0, true);

        if (!empty($free_proc)) {
            return $free_proc;
        }

        $write = $except = [];
        $read  = $this->pipe_read;

        if (0 === (int)stream_select($read, $write, $except, 1)) {
            return $this->getFreeProc();
        }

        $this->read($read);

        unset($free_proc, $write, $except, $read);
        return $this->getFreeProc();
    }
}