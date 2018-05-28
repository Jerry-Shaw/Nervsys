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

use core\handler\logger;
use core\handler\platform;

use core\parser\data;

use core\pool\config;

class mpc
{
    //Wait for process result
    public static $wait = true;

    //Wait timeout (in microseconds)
    public static $wait_time = 10000;

    //Read time (in microseconds)
    public static $read_time = 0;

    //Max running processes
    public static $max_runs = 10;

    //PHP key name in "setting.ini"
    public static $php_key = 'php';

    //PHP executable path in "conf.ini"
    public static $php_exe = '';

    //Basic command
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

        //Get php CLI CMD
        if ('' !== self::$php_exe) {
            return;
        }

        //Get command
        if (!isset(config::$CLI[self::$php_key])) {
            throw new \Exception('[' . self::$php_key . '] NOT found!');
        }

        self::$php_exe = config::$CLI[self::$php_key];
    }

    /**
     * Add to process list
     *
     * @param string $cmd
     * @param array  $argv
     * @param string $key
     */
    public static function add(string $cmd, array $argv, string $key = ''): void
    {
        '' === $key ? self::$jobs[] = ['cmd' => &$cmd, 'argv' => &$argv] : self::$jobs[$key] = ['cmd' => &$cmd, 'argv' => &$argv];

        unset($cmd, $argv, $key);
    }

    /**
     * Commit to process
     */
    public static function commit(): array
    {
        //Empty job
        if (empty(self::$jobs)) {
            return [];
        }

        //Split jobs
        $job_pack = count(self::$jobs) < self::$max_runs ? [self::$jobs] : array_chunk(self::$jobs, self::$max_runs, true);

        //Build command
        self::$mpc_cmd = self::$php_exe . ' "' . ROOT . DIRECTORY_SEPARATOR . 'api.php"';

        if (self::$wait) {
            self::$mpc_cmd .= ' --ret';
        }

        if (0 < self::$read_time) {
            self::$mpc_cmd .= ' --time ' . self::$read_time;
        }

        $result = [];

        foreach ($job_pack as $jobs) {
            //Execute process
            $data = self::execute($jobs);

            //Merge result
            if (!empty($data)) {
                $result += $data;
            }
        }

        unset($job_pack, $jobs, $data);
        return $result;
    }

    /**
     * Execute processes
     *
     * @param array $jobs
     *
     * @return array
     */
    private static function execute(array $jobs): array
    {
        //Resource list
        $resource = [];

        //Start process
        foreach ($jobs as $key => $item) {
            $cmd = self::$mpc_cmd . ' --cmd "' . data::encode($item['cmd']) . '"';

            if (!empty($item['argv'])) {
                $cmd .= ' --data "' . data::encode(json_encode($item['argv'])) . '"';
            }

            //Create process
            $process = proc_open(platform::cmd_proc($cmd), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

            //Store resource
            if (is_resource($process)) {
                $resource[$key]['exec'] = true;
                $resource[$key]['pipe'] = $pipes;
                $resource[$key]['proc'] = $process;
            } else {
                logger::log('warning', $item['cmd'] . ': Access denied or command ERROR!', [$cmd]);
                $resource[$key]['exec'] = false;
            }
        }

        unset($jobs, $key, $item, $cmd, $pipes);

        //Check wait options
        if (!self::$wait) {
            return [];
        }

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

        //Collect data
        while (!empty($resource)) {
            foreach ($resource as $key => $item) {
                //Build result
                if (!isset($result[$key])) {
                    $result[$key]['exec'] = $item['exec'];
                    $result[$key]['data'] = '';
                }

                //Unset failed process
                if (!$item['exec']) {
                    unset($resource[$key]);
                    continue;
                }

                //Unset finished process
                if (feof($item['pipe'][1])) {
                    //Close pipes & process
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

            $json = json_decode($item['data'], true);
            $result[$key]['data'] = !is_null($json) ? $json : $item['data'];
        }

        unset($resource, $key, $item, $pipe, $json);
        return $result;
    }
}