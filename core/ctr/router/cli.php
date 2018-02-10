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

use core\ctr\os, core\ctr\router;

class cli extends router
{
    //CLI data values
    public static $cli_data = '';

    //CLI argv values
    public static $cmd_argv = [];

    //CGI callable mode
    private static $call_cgi = false;

    //Pipe timeout option (in microseconds)
    private static $timeout = 0;

    //Record option
    private static $record = '';

    //Log option
    private static $log = false;

    //CLI configurations
    private static $config = [];

    //Wait cycle (in microseconds)
    const wait = 1000;

    //CLI config file path
    const config = ROOT . '/core/cfg.ini';

    //CLI working path
    const work_path = ROOT . '/core/cli/';

    /**
     * Run CLI Router
     */
    public static function run(): void
    {
        //Prepare data
        self::prep_data();

        //Execute & Record
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
        //Copy config
        $config = self::load_cfg();

        //Parse command
        $keys = false === strpos($command, ':') ? [$command] : explode(':', $command);

        foreach ($keys as $key) {
            if (!isset($config[$key])) throw new \Exception('[' . $command . '] NOT configured!');
            $config = $config[$key];
        }

        if (!is_string($config)) throw new \Exception('[' . $command . '] NOT configured!');

        $cmd = '"' . trim($config, ' "\'\t\n\r\0\x0B') . '"';

        unset($command, $config, $keys, $key);
        return $cmd;
    }

    /**
     * Load config from "cfg.ini" & "os::get_env"
     *
     * @return array
     * @throws \Exception
     */
    private static function load_cfg(): array
    {
        if (!empty(self::$config)) return self::$config;

        if ('' === self::config) throw new \Exception('Config file path NOT defined!');

        $path = realpath(self::config);
        if (false === $path) throw new \Exception('File [' . self::config . '] NOT found!');

        $config = parse_ini_file($path, true);
        if (false === $config) throw new \Exception('[' . self::config . '] setting incorrect!');

        self::$config = $config + os::get_env();

        unset($path, $config);
        return self::$config;
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
         * t/timeout: timeout for return (in microseconds; default "0" means wait till done. Works when r/record or l/log is set)
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
            if (false !== strpos($val['data'], '/')) self::$call_cgi = true;
            parent::$cmd = &$val['data'];
            $get = true;
        }

        unset($val);
        return $get;
    }

    /**
     * Execute CGI
     */
    private static function exec_cgi(): void
    {
        cgi::run();
        $logs = $result = [];

        //Write logs
        if (self::$log) {
            $logs['cmd'] = parent::$cmd;
            $logs['data'] = json_encode(parent::$data, JSON_OPT);
            $logs['result'] = json_encode(parent::$result, JSON_OPT);
            self::save_log($logs);
        }

        //Build result
        if ('' !== self::$record) {
            if (false !== strpos(self::$record, 'cmd')) $result['cmd'] = parent::$cmd;
            if (false !== strpos(self::$record, 'data')) $result['data'] = parent::$data;
            if (false !== strpos(self::$record, 'result')) $result['result'] = parent::$result;
        }

        //Write result
        self::save_result($result);
        unset($logs, $result);
    }

    /**
     * Execute CLI
     */
    private static function exec_cli(): void
    {
        try {
            //Get command from config
            $command = self::get_cmd(parent::$cmd);

            //Fill command argv
            if (!empty(self::$cmd_argv)) $command .= ' ' . implode(' ', self::$cmd_argv);

            //Create process
            $process = proc_open(os::proc_cmd($command), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, self::work_path);
            if (!is_resource($process)) throw new \Exception('Access denied or [' . $command . '] ERROR!');
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
        $logs = $result = [];

        //Write logs
        if (self::$log) {
            $logs['cmd'] = &$resource['cmd'];
            $logs['data'] = self::$cli_data;
            $logs['error'] = self::get_stream([$resource['proc'], $resource['pipe'][2]]);
            $logs['result'] = self::get_stream([$resource['proc'], $resource['pipe'][1]]);
            self::save_log($logs);
        }

        //Build result
        if ('' !== self::$record) {
            if (false !== strpos(self::$record, 'cmd')) $result['cmd'] = &$resource['cmd'];
            if (false !== strpos(self::$record, 'data')) $result['data'] = self::$cli_data;
            if (false !== strpos(self::$record, 'error')) $result['error'] = $logs['error'] ?? self::get_stream([$resource['proc'], $resource['pipe'][2]]);
            if (false !== strpos(self::$record, 'result')) $result['result'] = $logs['result'] ?? self::get_stream([$resource['proc'], $resource['pipe'][1]]);
        }

        //Write result
        self::save_result($result);
        unset($resource, $logs, $result);
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
     * Save result
     *
     * @param array $result
     */
    private static function save_result(array $result): void
    {
        switch (count($result)) {
            case 0:
                parent::$result = [];
                break;
            case 1:
                parent::$result = current($result);
                break;
            default:
                parent::$result = &$result;
                break;
        }

        unset($result);
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
        while (0 === self::$timeout || $time <= self::$timeout) {
            if (proc_get_status($resource[0])['running']) {
                usleep(self::wait);
                $time += self::wait;
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