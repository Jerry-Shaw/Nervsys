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
    private App     $app;
    private Error   $error;
    private Router  $router;
    private Reflect $reflect;
    private Execute $execute;
    private IOUnit  $io_unit;
    private OSUnit  $os_unit;

    public int    $proc_idx = 0;
    public int    $proc_cnt = 10;
    public string $php_path = '';

    public array $proc_list  = [];
    public array $pipe_list  = [];
    public array $job_count  = [];
    public array $job_result = [];

    /**
     * libMPC constructor.
     */
    public function __construct()
    {
        $this->io_unit = IOUnit::new();
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
        $this->app = App::new();
        $proc_cmd  = '"' . $this->app->script_path . '" -c"/' . strtr(__CLASS__, '\\', '/') . '/daemonProc"';

        //Create process
        for ($i = 0; $i < $this->proc_cnt; ++$i) {
            $this->createProc($i, $proc_cmd);
        }

        //Register MPC closeAll function
        register_shutdown_function([$this, 'closeAll']);

        unset($i);
        return $this;
    }

    /**
     * Add MPC job
     *
     * @param string $c
     * @param array  $data
     *
     * @return string
     */
    public function addJob(string $c, array $data = []): string
    {
        //Move idx
        $idx = $this->proc_idx++;

        //Reset idx
        if ($idx >= $this->proc_cnt) {
            $idx = $this->proc_idx = 0;
        }

        //Generate job ticket
        $ticket = (string)$idx . '-' . (string)(++$this->job_count[$idx]);

        //Add "c" & "mtk" into data
        $data['c']   = &$c;
        $data['mtk'] = &$ticket;

        //Communicate via STDIN
        fwrite($this->pipe_list[$idx][0], json_encode($data, JSON_FORMAT) . PHP_EOL);

        //Check & read from STDOUT
        while (0 < (fstat($this->pipe_list[$idx][1]))['size'] && 0 < $this->job_count[$idx]--) {
            $stdout = fgets($this->pipe_list[$idx][1]);

            if (false === $stdout) {
                $this->close($idx);
                break;
            }

            $this->job_result += json_decode(trim($stdout), true);
        }

        unset($c, $data, $idx, $stdout);
        return $ticket;
    }

    /**
     * @param string $ticket
     *
     * @return string
     */
    public function fetch(string $ticket): string
    {
        //Get idx
        $idx = (int)substr($ticket, 0, strpos($ticket, '-'));

        //Get status
        $status = proc_get_status($this->proc_list[$idx]);

        //Read STDOUT data
        if ($status['running']) {
            while (0 < (fstat($this->pipe_list[$idx][1]))['size'] && 0 < $this->job_count[$idx]--) {
                $this->job_result += json_decode(fgets($this->pipe_list[$idx][1]), true);
            }
        }

        $result = json_encode($this->job_result[$ticket] ?? '', JSON_FORMAT);

        unset($this->job_result[$ticket], $ticket, $idx, $status);
        return $result;
    }

    /**
     * Daemon process
     */
    public function daemonProc(): void
    {
        //Init modules & libraries
        $this->error   = Error::new();
        $this->router  = Router::new();
        $this->reflect = Reflect::new();
        $this->execute = Execute::new();
        $this->os_unit = OSUnit::new();

        while (true) {
            $stdin = fgets(STDIN);

            //Receive error and exit code
            if (false === $stdin || 'exit' === ($stdin = trim($stdin))) {
                return;
            }

            //Parse data
            if (!is_array($data = json_decode($stdin, true))) {
                continue;
            }

            //Check "c" & "mtk"
            if (!isset($data['c']) || !isset($data['mtk'])) {
                continue;
            }

            //Fetch job data
            $result = $this->execJob($data);

            //Output via STDOUT
            echo json_encode([$data['mtk'] => 1 === count($result) ? current($result) : $result], JSON_FORMAT) . PHP_EOL;

            //Free memory
            unset($stdin, $data, $result);
        }
    }

    /**
     * Close one process
     *
     * @param int $idx
     */
    public function close(int $idx): void
    {
        $status = proc_get_status($this->proc_list[$idx]);

        if (!$status['running']) {
            unset($this->proc_list[$idx], $this->pipe_list[$idx]);
            return;
        }

        foreach ($this->pipe_list[$idx] as $key => $pipe) {
            if (0 === $key) {
                fwrite($pipe, 'exit' . PHP_EOL);
            }

            fclose($pipe);
        }

        proc_close($this->proc_list[$idx]);
        unset($this->proc_list[$idx], $this->pipe_list[$idx], $idx, $status, $key, $pipe);
    }

    /**
     * Close All process
     */
    public function closeAll(): void
    {
        foreach ($this->proc_list as $idx => $proc) {
            $this->close($idx);
        }

        unset($idx, $proc);
    }

    /**
     * Close in the end
     */
    public function __destruct()
    {
        $this->closeAll();
    }

    /**
     * Create process
     *
     * @param int    $pid
     * @param string $cmd
     *
     * @return bool
     */
    private function createProc(int $pid, string $cmd): bool
    {
        //Create process
        $proc = proc_open(
            $this->php_path . ' ' . $cmd,
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['file', $this->app->log_path . DIRECTORY_SEPARATOR . date('Ymd') . '-MPC-' . (string)$pid . '.log', 'ab+']
            ],
            $pipes
        );

        if (!is_resource($proc)) {
            return false;
        }

        //Save proc & pipes
        $this->job_count[$pid] = 0;
        $this->proc_list[$pid] = $proc;
        $this->pipe_list[$pid] = $pipes;

        unset($pid, $cmd, $proc, $pipes);
        return true;
    }

    /**
     * Execute a job
     *
     * @param array $data
     *
     * @return array
     */
    private function execJob(array $data): array
    {
        $result = [];

        try {
            //Parse CMD
            $cmd_group = $this->router->parse($data['c']);

            //Call CGI
            if (!empty($cmd_group['cgi'])) {
                //Remap input data
                $this->io_unit->src_input = $data;

                //Process CGI command
                while (is_array($cmd_pair = array_shift($cmd_group['cgi']))) {
                    //Extract CMD contents
                    [$cmd_class, $cmd_method] = $cmd_pair;
                    //Run script method
                    $result += $this->execute->runScript($this->reflect, $cmd_class, $cmd_method, $cmd_pair[2] ?? implode('/', $cmd_pair));
                }
            }

            //Call CLI
            if (!empty($cmd_group['cli'])) {
                //Remap argv data
                $this->io_unit->src_argv = $data['argv'] ?? '';

                //Process CLI command
                while (is_array($cmd_pair = array_shift($cmd_group['cli']))) {
                    //Extract CMD contents
                    [$cmd_name, $exe_path] = $cmd_pair;

                    if ('' !== ($exe_path = trim($exe_path))) {
                        //Run external program
                        $this->execute->runProgram($this->os_unit, $cmd_name, $exe_path);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable, false);
            unset($throwable);
        }

        unset($data, $cmd_group, $cmd_pair, $cmd_class, $cmd_method, $cmd_name, $exe_path);
        return $result;
    }
}