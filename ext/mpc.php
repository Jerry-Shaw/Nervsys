<?php

/**
 * Multi-Process Controller Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

use core\system;

use core\handler\platform;

use core\helper\log;

use core\parser\data;

class mpc extends system
{
    //Wait for process result
    public static $wait = true;

    //Wait timeout (in microseconds)
    public static $wait_time = 10000;

    //Read time (in microseconds)
    public static $read_time = 0;

    //Max running processes
    public static $max_runs = 10;

    //PHP key name in "system.ini"
    public static $php_key = 'php';

    //Basic MPC command
    private static $mpc_cmd = '';

    //Process jobs
    private static $jobs = [];

    /**
     * Begin process
     */
    public static function begin(): void
    {
        //Reset jobs
        self::$jobs = [];

        //Check mpc_cmd
        if ('' !== self::$mpc_cmd) {
            return;
        }

        //Check php_key
        if (!isset(parent::$cli[self::$php_key])) {
            throw new \Exception('[' . self::$php_key . '] NOT found!');
        }

        //Build command
        self::$mpc_cmd = parent::$cli[self::$php_key] . ' "' . ROOT . 'api.php"';

        //Add wait option
        if (self::$wait) {
            self::$mpc_cmd .= ' --ret';
        }

        //Add read time option
        if (0 < self::$read_time) {
            self::$mpc_cmd .= ' --time ' . self::$read_time;
        }
    }

    /**
     * Add job
     *
     * @param string $cmd
     * @param array  $param
     * @param string $job_key
     */
    public static function add(string $cmd, array $param = [], string $job_key = ''): void
    {
        '' === $job_key ? self::$jobs[] = [&$cmd, &$param] : self::$jobs[$job_key] = [&$cmd, &$param];

        unset($cmd, $param, $job_key);
    }

    /**
     * Commit process
     *
     * @return array
     * @throws \Exception
     */
    public static function commit(): array
    {
        if (empty(self::$jobs)) {
            throw new \Exception('No MPC jobs!');
        }

        //Split jobs
        $job_pack = count(self::$jobs) < self::$max_runs ? [self::$jobs] : array_chunk(self::$jobs, self::$max_runs, true);

        $result = [];

        foreach ($job_pack as $jobs) {
            //Execute process
            if (!empty($data = self::execute($jobs))) {
                $result += $data;
            }
        }

        unset($job_pack, $jobs, $data);
        return $result;
    }

    /**
     * Build params
     *
     * @param array $param
     *
     * @return array
     */
    private static function build_param(array $param): array
    {
        $params = [];

        //Get argv
        if (isset($param['argv'])) {
            $params['argv'] = &$param['argv'];
            unset($param['argv']);
        }

        //Get pipe
        if (isset($param['pipe'])) {
            $params['pipe'] = &$param['pipe'];
            unset($param['pipe']);
        }

        //Get data
        if (isset($param['data'])) {
            $params['data'] = &$param['data'];
            unset($param['data']);
        } elseif (!empty($param)) {
            $params['data'] = &$param;
        }

        unset($param);
        return $params;
    }

    /**
     * Execute processes
     *
     * @param array $jobs
     *
     * @return array
     * @throws \Exception
     */
    private static function execute(array $jobs): array
    {
        //Resource list
        $resource = [];

        //Start process
        foreach ($jobs as $key => $item) {
            //Build param
            $param = self::build_param($item[1]);

            //Build CMD
            $cmd = self::$mpc_cmd . ' --cmd "' . data::encode($item[0]) . '"';

            //Append data
            if (isset($param['data'])) {
                $cmd .= ' --data "' . data::encode(json_encode($param['data'])) . '"';
            }

            //Append pipe
            if (!empty($param['pipe'])) {
                $cmd .= ' --pipe "' . data::encode(json_encode($param['pipe'])) . '"';
            }

            //Append argv
            if (!empty($param['argv'])) {
                $cmd .= ' ' . implode(' ', $param['argv']);
            }

            //Create process
            if (!is_resource($process = proc_open(platform::cmd_proc($cmd), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes))) {
                log::warning($item[0] . ': Access denied or command ERROR!', [$cmd, $param]);
                $resource[$key]['exec'] = false;
                continue;
            }

            //Add resource list
            $resource[$key]['exec'] = true;
            $resource[$key]['pipe'] = $pipes;
            $resource[$key]['proc'] = $process;
        }

        unset($jobs, $key, $item, $param, $cmd, $pipes);

        //Check wait options
        if (!self::$wait) {
            return [];
        }

        //Check wait_time option
        if (0 < self::$wait_time) {
            usleep(self::$wait_time);
        }

        //Collect result
        $result = self::collect($resource);

        unset($resource, $process);
        return $result;
    }

    /**
     * Collect result
     *
     * @param array $resource
     *
     * @return array
     */
    private static function collect(array $resource): array
    {
        $result = [];

        while (!empty($resource)) {
            foreach ($resource as $key => $item) {
                //Build result
                if (!isset($result[$key])) {
                    $result[$key]['exec'] = $item['exec'];
                    $result[$key]['data'] = '';
                }

                //Remove failed process
                if (!$item['exec']) {
                    unset($resource[$key]);
                    continue;
                }

                //Remove finished process
                if (feof($item['pipe'][1])) {
                    foreach ($item['pipe'] as $pipe) {
                        fclose($pipe);
                    }

                    proc_close($item['proc']);

                    unset($resource[$key]);
                    continue;
                }

                //Read pipe
                $result[$key]['data'] .= trim((string)fgets($item['pipe'][1], 4096));
            }
        }

        //Process data
        foreach ($result as $key => $item) {
            if ('' === $item['data']) {
                continue;
            }

            $result[$key]['data'] = !is_null($json = json_decode($item['data'], true)) ? $json : $item['data'];
        }

        unset($resource, $key, $item, $pipe, $json);
        return $result;
    }
}