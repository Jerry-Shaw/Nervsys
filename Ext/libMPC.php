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

use Core\Factory;
use Core\Lib\App;
use Core\Lib\IOUnit;
use Core\OSUnit;

/**
 * Class libMPC
 *
 * @package Ext
 */
class libMPC extends Factory
{
    private App    $app;
    private IOUnit $io_unit;
    private OSUnit $os_unit;

    private string $php_path;
    private array  $job_list = [];

    /**
     * libMPC constructor.
     *
     * @param string $php_path
     */
    public function __construct(string $php_path)
    {
        $this->app      = App::new();
        $this->io_unit  = IOUnit::new();
        $this->os_unit  = OSUnit::new();
        $this->php_path = &$php_path;
        unset($php_path);
    }

    /**
     * Add a job
     *
     * @param string $c
     * @param array  $data
     * @param string $argv
     *
     * @return $this
     */
    public function add(string $c, array $data = [], string $argv = ''): self
    {
        $this->job_list[] = ['c' => &$c, 'd' => &$data, 'a' => &$argv];

        unset($c, $data, $argv);
        return $this;
    }

    /**
     * Commit jobs
     *
     * @param bool $wait_ret
     * @param int  $max_fork
     *
     * @return array
     */
    public function go(bool $wait_ret = false, int $max_fork = 10): array
    {
        //Check jobs
        if (empty($this->job_list)) {
            return [];
        }

        //Split jobs
        $job_packs = count($this->job_list) > $max_fork
            ? array_chunk($this->job_list, $max_fork, true)
            : [$this->job_list];

        //Free jobs
        $this->job_list = [];

        //Build basic command
        $php_cmd = $this->php_path . ' "' . $this->app->script_path . '"';

        //Add wait option
        if ($wait_ret) {
            $php_cmd .= ' -t"json"';
        }

        $result = [];

        //Execute jobs
        foreach ($job_packs as $jobs) {
            if (!empty($data = $this->execute($php_cmd, $jobs, $wait_ret))) {
                $result += $data;
            }
        }

        unset($wait_ret, $max_fork, $job_packs, $php_cmd, $jobs, $data);
        return $result;
    }

    /**
     * Execute job
     *
     * @param string $cmd
     * @param array  $jobs
     * @param bool   $wait
     *
     * @return array
     */
    private function execute(string $cmd, array $jobs, bool $wait): array
    {
        //Resource list
        $resource = [];

        //Start process
        foreach ($jobs as $key => $job) {
            //Build cmd
            $cmd .= ' -c"' . $this->io_unit->encodeData($job['c']) . '"';

            //Append data
            if (!empty($job['d'])) {
                $cmd .= ' -d"' . $this->io_unit->encodeData(json_encode($job['d'])) . '"';
            }

            //Append argv
            if ('' !== $job['a']) {
                $cmd .= ' ' . $job['a'];
            }

            //Add OS command
            $this->os_unit->setCmd($cmd);

            //Add no wait option
            if (!$wait) {
                $this->os_unit->setAsBg();
            }

            //Create process
            $process = proc_open(
                $this->os_unit->setEnvPath()->setForProc()->fetchCmd(),
                [
                    ['pipe', 'r'],
                    ['pipe', 'w'],
                    ['file', $this->app->log_path . DIRECTORY_SEPARATOR . date('Ymd') . '-MPC' . '.log', 'ab+']
                ],
                $pipes
            );

            //Create failed
            if (!is_resource($process)) {
                $resource[$key]['res'] = false;
                continue;
            }

            //Check process status
            $status = proc_get_status($process);

            if (!$status['running'] && 0 < $status['exitcode']) {
                $resource[$key]['res'] = false;
                proc_close($process);
                continue;
            }

            //Merge resource
            $resource[$key]['res']  = true;
            $resource[$key]['cmd']  = $job['c'];
            $resource[$key]['pipe'] = $pipes;
            $resource[$key]['proc'] = $process;
        }

        unset($cmd, $jobs, $key, $job, $pipes, $status);

        //Check wait option
        if (!$wait) {
            return [];
        }

        //Collect result
        $result = $this->collect($resource);

        unset($wait, $resource, $process);
        return $result;
    }

    /**
     * Collect result
     *
     * @param array $resource
     *
     * @return array
     */
    private function collect(array $resource): array
    {
        $result = [];

        while (!empty($resource)) {
            foreach ($resource as $key => $item) {
                //Collect process
                $result[$key]['res'] = $item['res'];
                $result[$key]['cmd'] = $item['cmd'];

                //Remove failed process
                if (!$item['res']) {
                    unset($resource[$key]);
                    continue;
                }

                //Build process result
                if (!isset($result[$key]['data'])) {
                    $result[$key]['data'] = '';
                }

                //Remove finished process
                if (feof($item['pipe'][1])) {
                    //Close pipes
                    foreach ($item['pipe'] as $pipe) {
                        fclose($pipe);
                    }

                    //Close process
                    proc_close($item['proc']);

                    unset($resource[$key], $pipe);
                    continue;
                }

                //Read pipe
                $result[$key]['data'] .= fread($item['pipe'][1], 8192);
            }
        }

        //Parse data content
        foreach ($result as $key => $item) {
            if ('' !== $item['data'] && !is_null($json = json_decode($item['data'], true))) {
                $result[$key]['data'] = $json;
            }
        }

        unset($resource, $key, $item, $json);
        return $result;
    }
}