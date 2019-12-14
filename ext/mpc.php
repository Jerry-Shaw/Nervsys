<?php

/**
 * Multi-Process Controller Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

namespace ext;

use core\lib\std\io;
use core\lib\std\os;
use core\lib\std\pool;

/**
 * Class mpc
 *
 * @package ext
 */
class mpc extends factory
{
    /**
     * PHP executable path
     * key: "PHP", defined in "cli" section in ""app.ini"
     *
     * @var string
     */
    private $php_exe = '';

    //Basic command
    private $php_cmd = '';

    //Job list
    private $jobs = [];

    /** @var \core\lib\std\pool $unit_pool */
    private $unit_pool;

    /** @var \core\lib\std\io $unit_io */
    private $unit_io;

    /** @var \core\lib\std\os $unit_os */
    private $unit_os;

    /**
     * mpc constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        /** @var \core\lib\std\pool unit_pool */
        $this->unit_pool = \core\lib\stc\factory::build(pool::class);

        //Get PHP executable path
        if ('' === $this->php_exe = $this->unit_pool->conf['cli']['PHP'] ?? '') {
            throw new \Exception('"PHP" NOT defined in "app.ini"', E_USER_ERROR);
        }

        /** @var \core\lib\std\io unit_io */
        $this->unit_io = \core\lib\stc\factory::build(io::class);

        /** @var \core\lib\std\os unit_os */
        $this->unit_os = \core\lib\stc\factory::build(os::class);
    }

    /**
     * Add job
     *
     * @param array $job
     *
     * @return $this
     * @throws \Exception
     */
    public function add(array $job = []): object
    {
        if (!isset($job['c'])) {
            throw new \Exception('Missing param: "c"!', E_USER_ERROR);
        }

        //Add job
        $this->jobs[] = &$job;

        unset($job);
        return $this;
    }

    /**
     * Commit jobs
     *
     * @param bool $wait_ret
     * @param int  $max_fork
     *
     * @return array
     * @throws \Exception
     */
    public function go(bool $wait_ret = true, int $max_fork = 10): array
    {
        //Check jobs
        if (empty($this->jobs)) {
            return [];
        }

        //Split jobs
        $job_packs = count($this->jobs) > $max_fork
            ? array_chunk($this->jobs, $max_fork, true)
            : [$this->jobs];

        //Free jobs
        $this->jobs = [];

        //Build basic command
        $this->php_cmd = $this->php_exe . ' "' . ENTRY_SCRIPT . '"';

        //Add wait option
        if ($wait_ret) {
            $this->php_cmd .= ' -r"json"';
        }

        $result = [];
        foreach ($job_packs as $jobs) {
            //Execute jobs and merge result
            if (!empty($data = $this->execute($jobs, $wait_ret))) {
                $result += $data;
            }
        }

        unset($wait_ret, $max_fork, $job_packs, $jobs, $data);
        return $result;
    }

    /**
     * Execute jobs
     *
     * @param array $jobs
     * @param bool  $wait
     *
     * @return array
     * @throws \Exception
     */
    private function execute(array $jobs, bool $wait): array
    {
        //Resource list
        $resource = [];

        //Start process
        foreach ($jobs as $key => $job) {
            //Build cmd
            $cmd = $this->php_cmd . ' -c"' . $this->unit_io->encode($job['c']) . '"';

            //Append data
            if (!empty($job['d'])) {
                $cmd .= ' -d"' . $this->unit_io->encode(json_encode($job['d'])) . '"';
            }

            //Append pipe
            if (!empty($job['p'])) {
                $cmd .= ' -p"' . $this->unit_io->encode(json_encode($job['p'])) . '"';
            }

            //Append argv
            if (!empty($job['a'])) {
                $cmd .= ' ' . implode(' ', $job['a']);
            }

            //Add no wait option
            if (!$wait) {
                $cmd = $this->unit_os->cmd_bg($cmd);
            }

            //Create process
            $process = proc_open(
                $this->unit_os->cmd_proc($cmd),
                [
                    ['pipe', 'r'],
                    ['pipe', 'w'],
                    ['file', \core\lib\stc\factory::build(pool::class)->conf['log']['save_path'] . DIRECTORY_SEPARATOR . date('Ymd') . '_mpc_error' . '.log', 'ab+']
                ],
                $pipes
            );

            //Create failed
            if (!is_resource($process)) {
                throw new \Exception('"' . $job['c'] . '" create failed!', E_USER_ERROR);
            }

            //Check process status
            $status = proc_get_status($process);

            if (!$status['running'] && 0 < $status['exitcode']) {
                proc_close($process);
                throw new \Exception('"' . $job['c'] . '" calling failed!', E_USER_ERROR);
            }

            //Merge resource
            $resource[$key]['res']  = true;
            $resource[$key]['cmd']  = $job['c'];
            $resource[$key]['pipe'] = $pipes;
            $resource[$key]['proc'] = $process;
        }

        unset($jobs, $key, $job, $cmd, $pipes, $status);

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