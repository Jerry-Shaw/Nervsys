<?php

/**
 * cli Router Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2018 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace core\ctr\router;

use core\ctr\router, core\ctr\os;

class cli extends router
{
    //CLI data values
    public static $cli_data = '';

    //CLI argv values
    public static $cmd_argv = [];

    //CGI mode detection
    private static $cgi_mode = false;

    //Pipe timeout option
    private static $timeout = 2000;

    //Record option
    private static $record = '';

    //Log option
    private static $log = false;

    //CLI configuration
    private static $config = [];

    //CLI config file
    const config = ROOT . '/core/cfg.ini';

    //Working path
    const work_path = ROOT . '/core/cli/';

    /**
     * Run CLI Router
     */
    public static function run(): void
    {
        //Prepare data
        self::prep_data();
        //Execute & Record
        self::$cgi_mode ? self::exec_cgi() : self::exec_cli();
    }

    /**
     * Prepare CLI data
     */
    private static function prep_data(): void
    {
        $cmd = false;

        //Prepare option data
        $optind = self::prep_opt($cmd);

        //Merge arguments
        $argument = array_slice($_SERVER['argv'], $optind);
        if (empty($argument)) return;

        //No command, point to first argument
        if (!$cmd) parent::$data['cmd'] = array_shift($argument);

        //Merge data to self::$cmd_data
        if (!empty($argument)) self::$cmd_argv = &$argument;

        //Prepare cmd
        self::prep_cmd();
        unset($cmd, $optind, $argument);
    }

    /**
     * Prepare option data
     *
     * @param bool $cmd
     *
     * @return int
     */
    private static function prep_opt(bool &$cmd): int
    {
        /**
         * CLI options
         *
         * c/cmd: command
         * d/data: CGI data content
         * p/pipe: CLI pipe content
         * r/record: record type (result (default) / error / data / cmd, multiple options)
         * t/timeout: timeout for return (in microseconds, default value is 5000ms when r/return is set)
         * l/log: log option
         */
        $opt = getopt('c:d:p:r:t:l', ['cmd:', 'data:', 'pipe', 'record:', 'timeout:', 'log'], $optind);
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

        //Process return option
        $val = parent::opt_val($opt, ['r', 'record']);
        if ($val['get'] && '' !== $val['data']) self::$record = &$val['data'];

        //Process pipe read timeout
        $val = parent::opt_val($opt, ['t', 'timeout']);
        if ($val['get'] && is_numeric($val['data'])) self::$timeout = (int)$val['data'];

        //Process log option
        $val = parent::opt_val($opt, ['l', 'log']);
        if ($val['get']) self::$log = true;

        //Merge options to parent
        if (!empty($opt)) parent::$data = array_merge(parent::$data, $opt);

        //Get CMD & build data structure
        if (self::prep_cmd()) {
            $cmd = true;
            parent::build_struc();
        }

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
        $get = false;
        $val = parent::opt_val(parent::$data, ['c', 'cmd']);

        if ($val['get'] && is_string($val['data']) && '' !== $val['data']) {
            if (false !== strpos($val['data'], '/')) self::$cgi_mode = true;
            parent::$cmd = &$val['data'];
            $get = true;
        }

        unset($val);
        return $get;
    }

    /**
     * Parse command
     *
     * @return string
     * @throws \Exception
     */
    private static function parse_cmd(): string
    {
        $cmd = self::$config;
        $keys = false === strpos(parent::$cmd, ':') ? [parent::$cmd] : explode(':', parent::$cmd);

        foreach ($keys as $key) {
            if (!isset($cmd[$key])) throw new \Exception('[' . parent::$cmd . '] NOT configured!');
            $cmd = $cmd[$key];
        }

        if (!is_string($cmd)) throw new \Exception('[' . parent::$cmd . '] NOT configured!');

        unset($keys, $key);
        return '"' . trim($cmd, ' "\'\t\n\r\0\x0B') . '"';
    }

    /**
     * Load CLI config file
     */
    private static function load_config(): void
    {
        if ('' === self::config) throw new \Exception('Config file path NOT defined!');

        $path = realpath(self::config);
        if (false === $path) throw new \Exception('File [' . self::config . '] NOT found!');

        $config = parse_ini_file($path, true);
        if (!is_array($config) || empty($config)) throw new \Exception('[' . self::config . '] setting incorrect!');

        self::$config = array_merge($config, os::get_env());
        unset($path, $config);
    }

    /**
     * Execute CGI
     */
    private static function exec_cgi(): void
    {
        cgi::run();
        $result = $logs = [];

        //Save logs
        if (self::$log) {
            $logs['cmd'] = parent::$cmd;
            $logs['data'] = json_encode(parent::$data, JSON_OPT);
            $logs['result'] = json_encode(parent::$result, JSON_OPT);
            self::log_rec($logs);
        }

        //Save result
        if ('' !== self::$record) {
            if (false !== strpos(self::$record, 'cmd')) $result['cmd'] = parent::$cmd;
            if (false !== strpos(self::$record, 'data')) $result['data'] = parent::$data;
            if (false !== strpos(self::$record, 'result')) $result['result'] = parent::$result;
        }

        //Write result
        parent::$result = &$result;
        unset($result, $logs);
    }

    /**
     * Execute CLI
     */
    private static function exec_cli(): void
    {
        try {
            //Load config
            self::load_config();

            //Parse command
            $command = self::parse_cmd();
            //Fill command argv
            if (!empty(self::$cmd_argv)) $command .= ' ' . implode(' ', self::$cmd_argv);

            //Create process
            $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, self::work_path);
            if (!is_resource($process)) throw new \Exception('Access denied or [' . $command . '] ERROR!');
            if ('' !== self::$cli_data) fwrite($pipes[0], self::$cli_data . PHP_EOL);

            //Record CLI Runtime values
            self::cli_rec(['cmd' => &$command, 'proc' => &$process, 'pipe' => &$pipes]);

            //Close Pipes (keep process)
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
        $result = $logs = [];

        //Save logs
        if (self::$log) {
            $logs['cmd'] = &$resource['cmd'];
            $logs['data'] = self::$cli_data;
            $logs['error'] = self::get_stream([$resource['proc'], $resource['pipe'][2]]);
            $logs['result'] = self::get_stream([$resource['proc'], $resource['pipe'][1]]);
            self::log_rec($logs);
        }

        //Build result
        if ('' !== self::$record) {
            if (false !== strpos(self::$record, 'cmd')) $result['cmd'] = &$resource['cmd'];
            if (false !== strpos(self::$record, 'data')) $result['data'] = self::$cli_data;
            if (false !== strpos(self::$record, 'error')) $result['error'] = $logs['error'] ?? self::get_stream([$resource['proc'], $resource['pipe'][2]]);
            if (false !== strpos(self::$record, 'result')) $result['result'] = $logs['result'] ?? self::get_stream([$resource['proc'], $resource['pipe'][1]]);
        }

        //Write result
        parent::$result = &$result;
        unset($resource, $result, $logs);
    }

    /**
     * Record logs
     *
     * @param array $logs
     */
    private static function log_rec(array $logs): void
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
     * @param array $stream
     *
     * @return string
     */
    private static function get_stream(array $stream): string
    {
        $time = 0;
        $result = '';

        //Keep checking process
        while ($time <= self::$timeout) {
            //Get status of process
            $status = proc_get_status($stream[0]);

            //Get stream content when process terminated
            if (false === $status['running']) {
                $result = trim(stream_get_contents($stream[1]));
                break;
            }

            //Wait for process
            usleep(10);
            $time += 10;
        }

        //Return false once the elapsed time reaches the limit
        unset($stream, $time, $status);
        return $result;
    }
}