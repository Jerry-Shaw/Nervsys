<?php

/**
 * Multi-Process Controller Extension
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

use Core\Execute;
use Core\Factory;
use Core\Lib\App;
use Core\Lib\Error;
use Core\Lib\IOUnit;
use Core\Lib\Router;
use Core\OSUnit;
use Core\Reflect;

/**
 * Class libMPC
 *
 * @package Ext
 */
class libMPC extends Factory
{
    private App    $app;
    private Error  $error;
    private IOUnit $io_unit;
    private OSUnit $os_unit;

    public int    $proc_cnt = 10;
    public string $php_path = '';
    public string $proc_cmd = '';

    public array $proc_list = [];
    public array $pipe_list = [];

    /**
     * libMPC constructor.
     */
    public function __construct()
    {
        $this->app     = App::new();
        $this->error   = Error::new();
        $this->io_unit = IOUnit::new();
        $this->os_unit = OSUnit::new();

        $this->proc_cmd = '"' . $this->app->script_path . '" -c"/' . strtr(__CLASS__, '\\', '/') . '/daemonProc"';
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
     * Set number of process
     *
     * @param int $proc_count
     *
     * @return $this
     */
    public function setProcNum(int $proc_count): self
    {
        $this->proc_cnt = &$proc_count;

        unset($proc_count);
        return $this;
    }

    /**
     * Start MPC
     *
     * @return $this
     */
    public function start(): self
    {
        //Create process
        for ($i = 0; $i < $this->proc_cnt; ++$i) {
            if (!$this->createProc($i)) {
                --$i;
                continue;
            }
        }

        //Register MPC close function
        register_shutdown_function([$this, 'closeAll']);

        unset($i);
        return $this;
    }

    /**
     * Daemon process
     *
     * @param int $pid
     */
    public function daemonProc(int $pid): void
    {
        while (true) {
            $stdin = fgets(STDIN);

            //Receive exit code
            if ('exit' === ($stdin = trim($stdin))) {
                break;
            }

            $this->io_unit->src_output = $this->execJob($stdin, Router::new(), Reflect::new(), Execute::new());

            call_user_func($this->io_unit->output_handler, $this->io_unit);

            echo PHP_EOL;
        }
    }

    /**
     * @param string $c
     * @param array  $data
     *
     * @return string
     */
    public function addJob(string $c, array $data): string
    {
        $data['c'] = $c;

        $to = mt_rand(0, $this->proc_cnt - 1);

        fputs($this->pipe_list[$to][0], json_encode($data, JSON_FORMAT) . PHP_EOL);

        return fgets($this->pipe_list[$to][1]);
    }


    /**
     * Close one process
     *
     * @param int $index
     */
    public function close(int $index): void
    {
        $status = proc_get_status($this->proc_list[$index]);

        if (!$status['running']) {
            unset($this->proc_list[$index], $this->pipe_list[$index]);
            return;
        }

        foreach ($this->pipe_list[$index] as $key => $pipe) {
            if (0 === $key) {
                fputs($pipe, 'exit' . PHP_EOL);
            }

            fclose($pipe);
        }

        proc_close($this->proc_list[$index]);
        unset($this->proc_list[$index], $this->pipe_list[$index], $index, $status, $key, $pipe);
    }

    /**
     * Close All process
     */
    public function closeAll(): void
    {
        foreach ($this->proc_list as $index => $proc) {
            $this->close($index);
        }

        unset($index, $proc);
    }

    /**
     * Close in the end
     */
    public function __destruct()
    {
        $this->closeAll();
    }

    /**
     * @param string           $data
     * @param \Core\Lib\Router $router
     * @param \Core\Reflect    $reflect
     * @param \Core\Execute    $execute
     *
     * @return array
     */
    private function execJob(string $data, Router $router, Reflect $reflect, Execute $execute): array
    {

        $result = [];
        //Decode data in JSON
        $input_data = json_decode($data, true);

        try {
            //Source data parse failed
            if (!is_array($input_data) || !isset($input_data['c'])) {
                throw new \Exception('"c" NOT found or MPC data error');
            }

            //Parse CMD
            $cmd_group = $router->parse($input_data['c']);

            //Call CGI
            if (!empty($cmd_group['cgi'])) {
                //Remap input data
                $this->io_unit->src_input = $input_data;

                //Process CGI command
                while (is_array($cmd_pair = array_shift($cmd_group['cgi']))) {
                    //Extract CMD contents
                    [$cmd_class, $cmd_method] = $cmd_pair;
                    //Run script method
                    $result += $execute->runScript($reflect, $cmd_class, $cmd_method, $cmd_pair[2] ?? implode('/', $cmd_pair));
                }

            }

            //Call CLI
            if (!empty($cmd_group['cli'])) {
                //Remap argv data
                $this->io_unit->src_argv = $input_data['argv'] ?? '';

                //Process CLI command
                while (is_array($cmd_pair = array_shift($cmd_group['cli']))) {
                    //Extract CMD contents
                    [$cmd_name, $exe_path] = $cmd_pair;

                    if ('' !== ($exe_path = trim($exe_path))) {
                        //Run external program
                        $execute->runProgram($this->os_unit, $cmd_name, $exe_path);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable);
            unset($throwable);
        }

        unset($data, $router, $reflect, $execute, $input_data, $cmd_group);
        return $result;
    }


    /**
     * Create process
     *
     * @param int $pid
     *
     * @return bool
     */
    private function createProc(int $pid): bool
    {
        //Create process
        $proc = proc_open(
            $this->php_path . ' ' . $this->proc_cmd . ' -d"' . $this->io_unit->encodeData(json_encode(['pid' => $pid], JSON_FORMAT)) . '"',
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['file', $this->app->log_path . DIRECTORY_SEPARATOR . date('Ymd') . '-MPC' . (string)$pid . '.log', 'ab+']
            ],
            $pipes
        );

        if (!is_resource($proc)) {
            return false;
        }

        //Save proc & pipes
        $this->proc_list[$pid] = $proc;
        $this->pipe_list[$pid] = $pipes;

        unset($pid, $proc, $pipes);
        return true;
    }

}