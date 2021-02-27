<?php

/**
 * Multi-Process Controller Extension
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
    public int    $buf_size = 4096;
    public string $php_path = '';
    public string $proc_cmd = '';

    public array $proc_list = [];
    public array $pipe_list = [];

    public array $job_mtk    = [];
    public array $job_count  = [];
    public array $job_result = [];

    /**
     * Set pipe buffer size (default 4096 bytes, block when overflow, set carefully)
     *
     * @param int $buf_size
     *
     * @return $this
     */
    public function setBufSize(int $buf_size): self
    {
        $this->buf_size = &$buf_size;

        unset($buf_size);
        return $this;
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
        $this->app      = App::new();
        $this->proc_cmd = '"' . $this->app->script_path . '" -c"/' . strtr(__CLASS__, '\\', '/') . '/daemonProc"';

        //Create process
        for ($i = 0; $i < $this->proc_cnt; ++$i) {
            $this->createProc($i);
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
        //Get current job count and increase
        $job_count = ++$this->job_count[$this->proc_idx];

        //Generate increased job ticket
        $ticket = base_convert($this->job_mtk[$this->proc_idx], 10, 36);

        //Add "c" & "mtk" into data
        $data['c']   = &$c;
        $data['mtk'] = &$ticket;

        //Communicate via STDIN
        fwrite($this->pipe_list[$this->proc_idx][0], json_encode($data, JSON_FORMAT) . PHP_EOL);

        //Check & read from STDOUT
        $this->buf_size < (fstat($this->pipe_list[$this->proc_idx][1]))['size'] && $this->readPipe($this->proc_idx, $job_count);

        //Get current job mtk
        $mtk = (string)$this->proc_idx . ':' . $ticket;

        //Move/Reset job_mtk
        if ((++$this->job_mtk[$this->proc_idx]) >= PHP_INT_MAX) {
            $this->job_mtk[$this->proc_idx] = 0;
        }

        //Move/Reset proc_idx
        if ((++$this->proc_idx) >= $this->proc_cnt) {
            $this->proc_idx = 0;
        }

        unset($c, $data, $job_count, $ticket);
        return $mtk;
    }

    /**
     * Fetch data by ticket (json|string)
     *
     * @param string $ticket
     *
     * @return string
     */
    public function fetch(string $ticket): string
    {
        //Get idx
        $idx = (int)substr($ticket, 0, strpos($ticket, ':'));

        //Read STDOUT data
        $this->readPipe($idx, $this->job_count[$idx]);

        //Fetch result by ticket
        $tk_data = $this->job_result[$ticket] ?? '';
        $result  = is_string($tk_data) ? $tk_data : json_encode($tk_data, JSON_FORMAT);

        unset($this->job_result[$ticket], $ticket, $idx, $tk_data);
        return $result;
    }

    /**
     * Create process
     *
     * @param int $pid
     *
     * @return bool
     */
    public function createProc(int $pid): bool
    {
        //Create process
        $proc = proc_open(
            $this->php_path . ' ' . $this->proc_cmd,
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

        //Save process properties
        $this->job_mtk[$pid]   = 0;
        $this->job_count[$pid] = 0;
        $this->proc_list[$pid] = $proc;
        $this->pipe_list[$pid] = $pipes;

        unset($pid, $proc, $pipes);
        return true;
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
        $this->io_unit = IOUnit::new();
        $this->os_unit = OSUnit::new();

        while (true) {
            //Pipe broken
            if (false === ($stdin = fgets(STDIN))) {
                return;
            }

            //On exit code
            if ('exit' === ($stdin = trim($stdin))) {
                return;
            }

            //Parse data
            if ('' === $stdin || !is_array($data = json_decode($stdin, true))) {
                echo PHP_EOL;
                continue;
            }

            //Check "c" & "mtk"
            if (!isset($data['c']) || !isset($data['mtk'])) {
                echo PHP_EOL;
                continue;
            }

            //Fetch job data
            $result = $this->execJob($data);

            //Output via STDOUT
            echo json_encode([$data['mtk'], 1 === count($result) ? current($result) : $result], JSON_FORMAT) . PHP_EOL;

            //Free memory
            unset($stdin, $data, $result);
        }
    }

    /**
     * Close one process
     *
     * @param int $idx
     *
     * @return $this
     */
    public function close(int $idx): self
    {
        $status = proc_get_status($this->proc_list[$idx]);

        if ($status['running']) {
            foreach ($this->pipe_list[$idx] as $key => $pipe) {
                if (0 === $key) {
                    //Send "exit" signal to child STDIN
                    fwrite($pipe, 'exit' . PHP_EOL);
                }

                fclose($pipe);
            }

            proc_close($this->proc_list[$idx]);
        }

        $this->job_mtk[$idx]   = 0;
        $this->job_count[$idx] = 0;

        unset($this->proc_list[$idx], $this->pipe_list[$idx], $idx, $status, $key, $pipe);
        return $this;
    }

    /**
     * Close All process
     *
     * @return $this
     */
    public function closeAll(): self
    {
        foreach ($this->proc_list as $idx => $proc) {
            $this->close($idx);
        }

        unset($idx, $proc);
        return $this;
    }

    /**
     * Close in the end
     */
    public function __destruct()
    {
        $this->closeAll();
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
            $this->router->parse($data['c']);

            //Call CGI
            if (!empty($this->router->cgi_cmd)) {
                //Remap input data
                $this->io_unit->src_input = $data;

                //Process CGI command
                while (is_array($cmd_pair = array_shift($this->router->cgi_cmd))) {
                    //Extract CMD contents
                    [$cmd_class, $cmd_method] = $cmd_pair;
                    //Run script method
                    $result += $this->execute->runScript($this->reflect, $cmd_class, $cmd_method, $cmd_pair[2] ?? implode('/', $cmd_pair));
                }
            }

            //Call CLI
            if (!empty($this->router->cli_cmd)) {
                //Remap argv data
                $this->io_unit->src_argv = $data['argv'] ?? '';

                //Process CLI command
                while (is_array($cmd_pair = array_shift($this->router->cli_cmd))) {
                    //Extract CMD contents
                    [$cmd_name, $exe_path] = $cmd_pair;

                    if ('' !== ($exe_path = trim($exe_path))) {
                        //Run external program
                        $this->execute->runProgram($this->os_unit, $cmd_name, $exe_path);
                    }
                }
            }
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable, false, false);
            unset($throwable);
        }

        unset($data, $cmd_pair, $cmd_class, $cmd_method, $cmd_name, $exe_path);
        return $result;
    }

    /**
     * Read data from pipe
     *
     * @param int $idx
     * @param int $count
     */
    private function readPipe(int $idx, int $count): void
    {
        while (0 <= --$count && 0 <= --$this->job_count[$idx]) {
            //Read from pipe STDOUT
            if (false === ($stdout = fgets($this->pipe_list[$idx][1]))) {
                $this->close($idx);
                $this->createProc($idx);
                break;
            }

            if ('' === ($stdout = trim($stdout)) || !is_array($job_data = json_decode($stdout, true))) {
                continue;
            }

            //Save to result block
            $this->job_result += [(string)$idx . ':' . $job_data[0] => $job_data[1]];
        }

        unset($idx, $count, $stdout, $job_data);
    }
}