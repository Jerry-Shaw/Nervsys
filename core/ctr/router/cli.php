<?php

/**
 * cli Router Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
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

use \core\ctr\router as router, \core\ctr\router\cgi as cgi;

class cli extends router
{
    //CGI command
    private static $cgi_mode = false;

    //CLI data value
    private static $cli_data = '';

    //CMD data value
    private static $cmd_data = [];

    //Pipe read timeout
    private static $timeout = 5000;

    //CLI config settings
    private static $config = [];

    //CLI config file
    const config = ROOT . '/core/cfg.ini';

    //work path
    const work_path = ROOT . '/core/cli/';

    /**
     * Run CLI Router
     */
    public static function run(): void
    {
        //Prepare data
        self::get_data();
        //Parse cmd data
        self::parse_cmd();
        //Execute cmd
        self::execute_cmd();
    }

    /**
     * Get CLI data
     */
    private static function get_data(): void
    {
        /**
         * Get CLI options
         *
         * c/cmd: command
         * d/data: CGI data content
         * p/pipe: CLI pipe content
         * r/return: return type (result (default) / error / data / cmd, multiple options)
         * t/timeout: timeout for return (in microseconds, default value is 5000ms when r/return is set)
         * l/log: log option
         */
        $command = false;
        $opt = getopt('c:d:p:r:t:l', ['cmd:', 'data:', 'pipe', 'return:', 'timeout:', 'log'], $optind);

        if (!empty($opt)) {
            //Process cgi data value
            $data_get = self::get_opt($opt, ['d', 'data']);
            if ($data_get['get']) {
                $cgi_data = self::parse_data($data_get['data']);
                //Merge data to parent
                if (!empty($cgi_data)) parent::$data = array_merge(parent::$data, $cgi_data);
                unset($cgi_data);
            }

            //Process cli data value
            $data_get = self::get_opt($opt, ['p', 'pipe']);
            if ($data_get['get'] && '' !== $data_get['data']) self::$cli_data = &$data_get['data'];

            //Process pipe read timeout
            $data_get = self::get_opt($opt, ['t', 'timeout']);
            if ($data_get['get'] && is_numeric($data_get['data'])) self::$timeout = (int)$data_get['data'];

            //Merge options to parent
            if (!empty($opt)) parent::$data = array_merge(parent::$data, $opt);
            if (self::get_cmd()) $command = true;
            unset($data_get);
        }

        //Merge arguments
        $argv_data = array_slice($_SERVER['argv'], $optind);
        if (empty($argv_data)) return;

        //No command, point to first argument
        if (!$command) parent::$data['cmd'] = array_shift($argv_data);

        //Merge data to self::$cmd_data
        if (!empty($argv_data)) self::$cmd_data = &$argv_data;

        //Build data structure
        if ($command || self::get_cmd()) parent::build_struct();
        unset($command, $opt, $argv_data);
    }

    /**
     * Parse data content
     *
     * @param string $input
     *
     * @return array
     */
    private static function parse_data(string $input): array
    {
        if ('' === $input) return [];

        //Decode data in JSON
        $json = json_decode($input, true);
        if (is_array($json)) {
            unset($input);
            return $json;
        }

        //Decode data in HTTP Query
        parse_str($input, $data);
        unset($input, $json);
        return $data;
    }

    /**
     * Get Option value from key name
     *
     * @param array $opt
     * @param array $keys
     *
     * @return array
     */
    private static function get_opt(array &$opt, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($opt[$key])) {
                $result = ['get' => true, 'data' => &$opt[$key]];
                unset($opt[$key]);
                return $result;
            }
        }
        return ['get' => false, 'data' => ''];
    }

    /**
     * Get cmd value from data
     *
     * @return bool
     */
    private static function get_cmd(): bool
    {
        foreach (['c', 'cmd'] as $key) {
            if (
                isset(parent::$data[$key]) &&
                is_string(parent::$data[$key]) &&
                '' !== parent::$data[$key]
            ) {
                parent::$cmd = parent::$data[$key];
                unset(parent::$data[$key]);
                return true;
            }
        }
        return false;
    }

    /**
     * Parse cmd data
     */
    private static function parse_cmd(): void
    {
        if (false !== strpos(parent::$cmd, '/')) self::$cgi_mode = true;
    }

    /**
     * Execute cmd
     */
    private static function execute_cmd(): void
    {
        if (self::$cgi_mode) {
            try {
                cgi::run();
                $error = '';
            } catch (\Throwable $exception) {
                $error = $exception->getMessage();
            }

            //Save logs
            if (self::get_opt(parent::$data, ['l', 'log'])['get']) {
                self::save_log(
                    [
                        'cmd'    => parent::$cmd,
                        'data'   => json_encode(parent::$data),
                        'error'  => &$error,
                        'result' => json_encode(parent::$result)
                    ]
                );
            }
            unset($error);
        } else {
            //Load config file
            self::load_config();
            self::auto_config();

            //Get command
            $command = self::command();
            if ('' === $command) return;
            $command = self::quote_command($command);

            if (!empty(parent::$data)) $command .= ' ' . implode(' ', self::$cmd_data);

            //Run command
            parent::$result = self::run_exec($command);
            unset($command);
        }
    }

    /**
     * Load CLI config file
     */
    private static function load_config(): void
    {
        if ('' === self::config) return;
        $path = realpath(self::config);
        if (false === $path) return;
        $config = parse_ini_file($path, true);
        if (!is_array($config) || empty($config)) return;
        self::$config = &$config;
        unset($path, $config);
    }

    /**
     * Automatically setup up config file
     */
    private static function auto_config(): void
    {
        $os = PHP_OS;
        try {
            $platform = '\\core\\ctr\\os\\' . strtolower($os);
            $exec_info = $platform::exec_info();
            if (0 === $exec_info['pid']) return;
            self::$config['PHP_CMD'] = &$exec_info['cmd'];
            self::$config['PHP_EXEC'] = self::quote_command($exec_info['path']);
            unset($platform, $exec_info);
        } catch (\Throwable $exception) {
            if (DEBUG) {
                fwrite(STDOUT, $os . ' NOT fully supported yet! ' . $exception->getMessage() . PHP_EOL);
                fclose(STDOUT);
            }
        }
        unset($os);
    }

    /**
     * Get command
     *
     * @return string
     */
    private static function command(): string
    {
        if (false === strpos(parent::$cmd, ':')) {
            if (isset(self::$config[parent::$cmd]) && is_string(self::$config[parent::$cmd])) return self::$config[parent::$cmd];
            else {
                if (DEBUG) {
                    fwrite(STDOUT, 'CMD config ERROR! Please check "cfg.ini"!' . PHP_EOL);
                    fclose(STDOUT);
                }
                return '';
            }
        } else {
            $cmd = self::$config;
            $keys = explode(':', parent::$cmd);
            foreach ($keys as $key) {
                if (isset($cmd[$key])) $cmd = $cmd[$key];
                else {
                    unset($cmd, $keys, $key);
                    if (DEBUG) {
                        fwrite(STDOUT, 'CMD not found! Please add to "cfg.ini"!' . PHP_EOL);
                        fclose(STDOUT);
                    }
                    return '';
                }
            }
            if (is_string($cmd)) return $cmd;
            else {
                unset($cmd, $keys, $key);
                if (DEBUG) {
                    fwrite(STDOUT, 'CMD config ERROR! Please check "cfg.ini"!' . PHP_EOL);
                    fclose(STDOUT);
                }
                return '';
            }
        }
    }

    /**
     * Add quotes to escape spaces in command path
     *
     * @param string $cmd
     *
     * @return string
     */
    private static function quote_command(string $cmd): string
    {
        return false === strpos($cmd, ' ') || ('"' === substr($cmd, 0, 1) && '"' === substr($cmd, -1, 1)) ? $cmd : '"' . $cmd . '"';
    }

    /**
     * Run External Process
     *
     * @param string $command
     *
     * @return array
     */
    private static function run_exec(string $command): array
    {
        //Create process
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, self::work_path);

        //Process create failed
        if (!is_resource($process)) return ['error' => 'Access denied! Check your "cfg.ini" and authority!'];

        //Write input data
        if ('' !== self::$cli_data) fwrite($pipes[0], self::$cli_data . PHP_EOL);

        //Build detailed results/logs
        $result = $logs = [];

        //Save logs
        if (self::get_opt(parent::$data, ['l', 'log'])['get']) {
            $logs['cmd'] = &$command;
            $logs['data'] = self::$cli_data;
            $logs['error'] = self::get_stream([$pipes[2]]);
            $logs['result'] = self::get_stream([$pipes[1]]);
            self::save_log($logs);
        }

        //Build results
        $result_get = self::get_opt(parent::$data, ['r', 'return']);
        if ($result_get['get']) {
            if (false !== strpos($result_get['data'], 'cmd')) $result['cmd'] = &$command;
            if (false !== strpos($result_get['data'], 'data')) $result['data'] = self::$cli_data;
            if (false !== strpos($result_get['data'], 'error')) $result['error'] = $logs['error'] ?? self::get_stream([$pipes[2]]);
            if (false !== strpos($result_get['data'], 'result')) $result['result'] = $logs['result'] ?? self::get_stream([$pipes[1]]);
        }

        //Close all pipes
        foreach ($pipes as $pipe) fclose($pipe);

        //Close Process
        proc_close($process);

        unset($command, $process, $pipes, $logs, $result_get, $pipe);
        return $result;
    }

    /**
     * Get the content of current stream
     *
     * @param array $stream
     *
     * @return string
     */
    private static function get_stream(array $stream): string
    {
        $time = 0;
        $result = '';

        //Get the resource
        $resource = current($stream);

        //Keep checking the stat of stream
        while ($time <= self::$timeout) {
            //Get the stat of stream
            $stat = fstat($resource);

            //Check the stat of stream
            if (false !== $stat && 0 < $stat['size']) {
                //Get trimmed stream content
                $result = trim(stream_get_contents($resource));
                break;
            }

            //Wait for process
            usleep(10);
            $time += 10;
        }

        //Return false once the elapsed time reaches the limit
        unset($stream, $time, $resource, $stat);
        return $result;
    }

    /**
     * Save logs
     *
     * @param array $data
     */
    private static function save_log(array $data): void
    {
        $time = time();
        $logs = array_merge(['time' => date('Y-m-d H:i:s', $time)], $data);
        foreach ($logs as $key => $value) $logs[$key] = strtoupper($key) . ': ' . $value;
        file_put_contents(self::work_path . 'logs/' . date('Y-m-d', $time) . '.log', PHP_EOL . implode(PHP_EOL, $logs) . PHP_EOL, FILE_APPEND);
        unset($data, $time, $logs, $key, $value);
    }
}