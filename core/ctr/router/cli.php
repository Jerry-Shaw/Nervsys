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
    //CLI data values
    public static $cli_data = '';

    //CLI argv values
    public static $cmd_argv = [];

    //CGI callable mode
    private static $call_cgi = false;

    //Return option
    private static $ret = false;

    //Log option
    private static $log = false;

    //Pipe read time (in microseconds)
    private static $time = 0;

    //Wait cycle (in microseconds)
    const work_wait = 1000;

    //Working path
    const work_path = ROOT . '/core/cli/';

    /**
     * Run CLI Router
     */
    public static function run(): void
    {
        //Prepare data
        self::prep_data();

        //Execute command
        self::$call_cgi ? self::exec_cgi() : self::exec_cli();
    }

    /**
     * Get cmd from config
     *
     * @param string $command
     *
     * @return string
     * @throws \Exception
     */
    public static function get_cmd(string $command): string
    {
        if (!isset(parent::$conf_cli[$command]) || !is_string(parent::$conf_cli[$command])) throw new \Exception('[' . $command . '] NOT configured!');

        return '"' . trim(parent::$conf_cli[$command], ' "\'\t\n\r\0\x0B') . '"';
    }

    /**
     * Prepare CLI data
     */
    private static function prep_data(): void
    {
        //Prepare option data
        $optind = self::prep_opt();

        //Prepare cmd value
        $get_cmd = self::prep_cmd();

        //Extract arguments
        $argument = array_slice($_SERVER['argv'], $optind);
        if (empty($argument)) return;

        //Redirect cmd to first argument
        if (!$get_cmd) parent::$data['cmd'] = array_shift($argument);

        //Merge data to self::$cmd_data
        if (!empty($argument)) self::$cmd_argv = &$argument;

        //Prepare cmd value
        self::prep_cmd();

        unset($optind, $argument);
    }

    /**
     * Prepare option data
     *
     * @return int
     */
    private static function prep_opt(): int
    {
        /**
         * CLI options
         *
         * c/cmd: command
         * d/data: CGI data content
         * p/pipe: CLI pipe content
         * r/ret: process return option
         * l/log: process log option (cmd, data, error, result)
         * t/time: read time (in microseconds; default "0" means read till done. Works when r/ret or l/log is set)
         */
        $opt = getopt('c:d:p:t:rl', ['cmd:', 'data:', 'pipe', 'time:', 'ret', 'log'], $optind);
        if (empty($opt)) return $optind;

        //Process cgi data value
        $val = parent::opt_val($opt, ['d', 'data']);
        if ($val['get']) {
            $data = self::parse_data($val['data']);
            if (!empty($data)) parent::$data = array_merge(parent::$data, $data);
            unset($data);
        }

        //Process cli data value
        $val = parent::opt_val($opt, ['p', 'pipe']);
        if ($val['get'] && '' !== $val['data']) self::$cli_data = &$val['data'];

        //Process pipe read time
        $val = parent::opt_val($opt, ['t', 'time']);
        if ($val['get'] && is_numeric($val['data'])) self::$time = (int)$val['data'];

        //Process return option
        $val = parent::opt_val($opt, ['r', 'ret']);
        if ($val['get']) self::$ret = true;

        //Process log option
        $val = parent::opt_val($opt, ['l', 'log']);
        if ($val['get']) self::$log = true;

        //Merge options to parent
        if (!empty($opt)) parent::$data = array_merge(parent::$data, $opt);

        unset($opt, $val);
        return $optind;
    }

    /**
     * Parse data content
     *
     * @param string $value
     *
     * @return array
     */
    private static function parse_data(string $value): array
    {
        if ('' === $value) return [];

        //Decode data in JSON
        $json = json_decode($value, true);
        if (is_array($json)) {
            unset($value);
            return $json;
        }

        //Decode data in HTTP Query
        parse_str($value, $data);

        unset($value, $json);
        return $data;
    }

    /**
     * Prepare cmd data
     *
     * @return bool
     */
    private static function prep_cmd(): bool
    {
        $val = parent::opt_val(parent::$data, ['c', 'cmd']);
        if (!$val['get'] || !is_string($val['data']) || '' === $val['data']) return false;

        parent::$cmd = &$val['data'];
        self::$call_cgi = self::chk_cgi($val['data']);

        unset($val);
        return true;
    }

    /**
     * Check CGI command
     *
     * @param string $cmd
     *
     * @return bool
     */
    private static function chk_cgi(string $cmd): bool
    {
        //Check NAMESPACE slashes
        if (false !== strpos($cmd, '/')) return true;

        //Check CGI config
        if (empty(parent::$conf_cgi)) return false;

        //Check mapping keys
        $data = false !== strpos($cmd, '-') ? explode('-', $cmd) : [$cmd];
        $data = array_intersect_key(parent::$conf_cgi, array_flip($data));
        $for_cgi = false !== strpos(implode($data), '/');

        unset($cmd, $data);
        return $for_cgi;
    }

    /**
     * Execute CGI
     */
    private static function exec_cgi(): void
    {
        cgi::run();

        //Write logs
        if (self::$log) {
            $logs = [];
            $logs['cmd'] = parent::$cmd;
            $logs['data'] = json_encode(parent::$data, JSON_OPT);
            $logs['result'] = json_encode(parent::$result, JSON_OPT);
            self::save_log($logs);
            unset($logs);
        }

        //Build result
        if (!self::$ret) parent::$result = [];
    }

    /**
     * Execute CLI
     */
    private static function exec_cli(): void
    {
        try {
            //Add OS environment
            parent::$conf_cli['PHP_EXE'] = os::get_env();

            //Get cmd from config
            $command = self::get_cmd(parent::$cmd);

            //Fill cmd argv
            if (!empty(self::$cmd_argv)) $command .= ' ' . implode(' ', self::$cmd_argv);

            //Create process
            $process = proc_open(os::cmd_proc($command), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, self::work_path);
            if (!is_resource($process)) throw new \Exception('Access denied or [' . $command . '] ERROR!');

            //Write data via STDIN
            if ('' !== self::$cli_data) fwrite($pipes[0], self::$cli_data . PHP_EOL);

            //Record CLI Runtime values
            self::cli_rec(['cmd' => &$command, 'pipe' => &$pipes, 'proc' => &$process]);

            //Close pipes (ignore process)
            foreach ($pipes as $pipe) fclose($pipe);

            unset($command, $process, $pipes, $pipe);
        } catch (\Throwable $exception) {
            debug('CLI', $exception->getMessage());
            unset($exception);
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
        if (self::$log) {
            $logs['cmd'] = &$resource['cmd'];
            $logs['data'] = self::$cli_data;
            $logs['error'] = self::get_stream([$resource['proc'], $resource['pipe'][2]]);
            $logs['result'] = self::get_stream([$resource['proc'], $resource['pipe'][1]]);
            self::save_log($logs);
        }

        //Build result
        if (self::$ret) parent::$result[$resource['cmd']] = $logs['result'] ?? self::get_stream([$resource['proc'], $resource['pipe'][1]]);

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
        file_put_contents(self::work_path . 'logs/' . date('Y-m-d', $time) . '.log', PHP_EOL . implode(PHP_EOL, $logs) . PHP_EOL, FILE_APPEND);

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
        while (0 === self::$time || $time <= self::$time) {
            if (proc_get_status($resource[0])['running']) {
                usleep(self::work_wait);
                $time += self::work_wait;
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