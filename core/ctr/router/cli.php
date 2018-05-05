<?php

/**
 * cli Router Module
 *
 * Copyright 2016-2018 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
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

namespace core\ctr\router;

use core\ctr\os, core\ctr\router;

class cli extends router
{
    //Wait cycle (in microseconds)
    const WORK_WAIT = 1000;

    //Working path
    const WORK_PATH = ROOT . '/core/cli/';

    /**
     * Run CLI Router
     */
    public static function run(): void
    {
        try {
            //Acquire OS environment for 'PHP_EXE' value
            if (in_array('PHP_EXE', parent::$cli_cmd, true)) parent::$conf_cli['PHP_EXE'] = os::get_env();
        } catch (\Throwable $throwable) {
            debug(parent::$cli_cmd, $throwable->getMessage());
            unset($throwable);
            exit;
        }

        //Process CMD
        foreach (parent::$cli_cmd as $cmd) {
            if ('' === $cmd) continue;

            try {
                //Check signal
                if (0 !== parent::$signal) throw new \Exception(parent::get_signal());

                //Get CMD ready
                $command = '"' . parent::$conf_cli[$cmd] . '"';
                if (!empty(parent::$cli_data['argv'])) $command .= ' ' . implode(' ', parent::$cli_data['argv']);

                //Create process
                $process = proc_open(os::cmd_proc($command), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, self::WORK_PATH);
                if (!is_resource($process)) throw new \Exception('Access denied or [' . $cmd . '] ERROR!');

                //Write data via STDIN
                if ('' !== parent::$cli_data['pipe']) fwrite($pipes[0], parent::$cli_data['pipe'] . PHP_EOL);

                //Record CLI Runtime values
                self::cli_rec(['cmd' => &$cmd, 'pipe' => &$pipes, 'proc' => &$process]);

                //Close pipes (ignore process)
                foreach ($pipes as $pipe) fclose($pipe);
            } catch (\Throwable $throwable) {
                debug($cmd, $throwable->getMessage());
                unset($throwable);
            }

            unset($cmd, $command, $process, $pipes, $pipe);
        }
    }

    /**
     * Record CLI Runtime values
     *
     * @param array $resource
     */
    private static function cli_rec(array $resource): void
    {
        $logs = [];

        //Write logs
        if (parent::$cli_data['log']) {
            $logs['cmd'] = &$resource['cmd'];
            $logs['argv'] = parent::$cli_data['argv'];
            $logs['pipe'] = parent::$cli_data['pipe'];
            $logs['error'] = self::get_stream([$resource['proc'], $resource['pipe'][2]]);
            $logs['result'] = self::get_stream([$resource['proc'], $resource['pipe'][1]]);

            self::save_log($logs);
        }

        //Build result
        if (parent::$cli_data['ret']) parent::$result[$resource['cmd']] = $logs['result'] ?? self::get_stream([$resource['proc'], $resource['pipe'][1]]);

        unset($resource, $logs);
    }

    /**
     * Save logs
     *
     * @param array $logs
     */
    private static function save_log(array $logs): void
    {
        $time = time();
        $logs = ['time' => date('Y-m-d H:i:s', $time)] + $logs;

        foreach ($logs as $key => $value) $logs[$key] = strtoupper($key) . ': ' . $value;
        file_put_contents(self::WORK_PATH . 'logs/' . date('Y-m-d', $time) . '.log', PHP_EOL . implode(PHP_EOL, $logs) . PHP_EOL, FILE_APPEND);

        unset($logs, $time, $key, $value);
    }

    /**
     * Get stream content
     *
     * @param array $resource
     *
     * @return string
     */
    private static function get_stream(array $resource): string
    {
        $time = 0;
        $result = '';

        //Keep checking pipe
        while (0 === parent::$cli_data['time'] || $time <= parent::$cli_data['time']) {
            if (proc_get_status($resource[0])['running']) {
                usleep(self::WORK_WAIT);
                $time += self::WORK_WAIT;
            } else {
                $result = trim(stream_get_contents($resource[1]));
                break;
            }
        }

        //Return empty once elapsed time reaches the limit
        unset($resource, $time);
        return $result;
    }
}