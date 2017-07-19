<?php

/**
 * CLI Module
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

namespace core\ctrl;

class cli
{
    //Options
    public static $opt = [];

    //Variables
    public static $var = [];

    //Option Details
    private static $opt_cmd = '';//Option for Internal Mode
    private static $opt_map = '';//Option for Internal Mode
    private static $opt_get = '';//Result (Valid values: "cmd", "data", "error", "result" or "cmd", "map", "data", "result"; empty: no returns)
    private static $opt_log = false;//Log setting, set to true to log all ("time", "cmd", "data", "error", "result" or "time", "cmd", "map", "data", "result")
    private static $opt_try = 200;//Default try times for stream checking
    private static $opt_wait = 1;//Default time wait for stream checking (in microseconds)
    private static $opt_data = '';//Request data, will try to read STDIN when empty
    private static $opt_path = ROOT . '/_cli/cfg.json';//Default CFG file path

    //CLI Runtime Settings
    private static $cli_cmd = '';//CLI Command
    private static $cli_cfg = [];//CLI Configurations

    /**
     * Load options
     */
    private static function load_opt(): void
    {
        //Return when option is empty
        if (empty(self::$opt)) return;
        //Get "log" option
        if (isset(self::$opt['log']) || isset(self::$opt['l'])) self::$opt_log = true;
        //Get "cmd" option
        self::$opt_cmd = self::get_opt(['cmd', 'c']);
        //Get "map" option
        self::$opt_map = self::get_opt(['map', 'm']);
        //Get "get" option
        self::$opt_get = self::get_opt(['get', 'g']);
        //Get "path" option
        self::$opt_path = self::get_opt(['path', 'p']);
        //Get "data" from option/STDIN
        self::$opt_data = self::get_opt(['data', 'd']);
        if ('' === self::$opt_data) self::$opt_data = self::get_stream([STDIN]);
        //Get "try" option
        $try = (int)self::get_opt(['try', 't']);
        if (0 < $try) self::$opt['try'] = &$try;
        //Get "wait" option
        $wait = (int)self::get_opt(['wait', 'w']);
        if (0 < $wait) self::$opt['wait'] = &$wait;
        unset($try, $wait);
    }

    /**
     * Get option value
     *
     * @param array $keys
     *
     * @return string
     */
    private static function get_opt(array $keys): string
    {
        //Check every key in options
        foreach ($keys as $key) if (isset(self::$opt[$key]) && false !== self::$opt[$key] && '' !== self::$opt[$key]) return self::$opt[$key];
        //Return empty if not found
        return '';
    }

    /**
     * Load configurations
     */
    private static function load_cfg(): void
    {
        //Check CFG file
        if (!is_file(self::$opt_path)) return;
        //Get CFG file content
        $json = (string)file_get_contents(self::$opt_path);
        if ('' === $json) return;
        //Decode file content and map to CFG
        $data = json_decode($json, true);
        if (isset($data)) self::$cli_cfg = &$data;
        unset($json, $data);
    }

    /**
     * Build var for Internal Mode
     */
    private static function build_var(): void
    {
        //Regroup request data
        self::$var = ['cmd' => self::$opt_cmd];
        //Merge "map" data when exists
        if ('' !== self::$opt_map) self::$var['map'] = self::$opt_map;
        //Process input data
        if ('' === self::$opt_data) return;
        //Parse HTTP query data
        parse_str(self::$opt_data, $data);
        //Merge input data when exists
        if (!empty($data)) self::$var = array_merge(self::$var, $data);
        unset($data);
    }

    /**
     * Build CMD for External Mode
     */
    private static function build_cmd(): void
    {
        //Check variables
        if (empty(self::$var)) return;
        //Check specific language in configurations
        if (!isset(self::$cli_cfg[self::$var[0]])) return;
        //Rebuild all commands
        foreach (self::$var as $k => $v) if (isset(self::$cli_cfg[$v])) self::$var[$k] = self::$cli_cfg[$v];
        //Create command
        self::$cli_cmd = implode(' ', self::$var);
        unset($k, $v);
    }

    /**
     * Save logs
     *
     * @param array $data
     */
    private static function save_log(array $data): void
    {
        $logs = array_merge(['time' => date('Y-m-d H:i:s', time())], $data);
        foreach ($logs as $key => $value) $logs[$key] = strtoupper($key) . ': ' . $value;
        file_put_contents(CLI_LOG_PATH . date('Y-m-d', time()) . '.log', PHP_EOL . implode(PHP_EOL, $logs) . PHP_EOL, FILE_APPEND);
        unset($data, $logs, $key, $value);
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
        $try = 0;
        $result = '';
        //Get the resource
        $resource = current($stream);
        //Keep checking the stat of stream
        while ($try < self::$opt_try) {
            //Get the stat of stream
            $stat = fstat($resource);
            //Check the stat of stream
            if (false !== $stat && 0 < $stat['size']) {
                //Get trimmed stream content
                $result = trim(stream_get_contents($resource));
                break;
            }
            //Wait for process
            usleep(self::$opt_wait);
            ++$try;
        }
        //Return false once the elapsed time reaches the limit
        unset($stream, $try, $resource, $stat);
        return $result;
    }

    /**
     * Call Internal API
     *
     * @return array
     */
    private static function call_api(): array
    {
        $result = [];
        //Pass data to Data Pool Module
        pool::$data = self::$var;
        //Start Data Pool Module
        pool::start();
        //Save logs
        if (self::$opt_log) {
            $logs = ['cmd' => self::$opt_cmd];
            $logs['map'] = self::$opt_map;
            $logs['data'] = self::$opt_data;
            $logs['result'] = json_encode(pool::$pool);
            self::save_log($logs);
            unset($logs);
        }
        //Build results
        if ('' !== self::$opt_get) {
            if (false !== strpos(self::$opt_get, 'cmd')) $result['cmd'] = self::$opt_cmd;
            if (false !== strpos(self::$opt_get, 'map')) $result['map'] = self::$opt_map;
            if (false !== strpos(self::$opt_get, 'data')) $result['data'] = self::$opt_data;
            if (false !== strpos(self::$opt_get, 'result')) $result['result'] = pool::$pool;
        }
        return $result;
    }

    /**
     * Run External Process
     *
     * @return array
     */
    private static function run_exec(): array
    {
        //Command error
        if ('' === self::$cli_cmd) return ['error' => 'Command ERROR!'];
        //Create process
        $process = proc_open(self::$cli_cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, CLI_WORK_PATH);
        //Process create failed
        if (!is_resource($process)) return ['error' => 'Process ERROR!'];
        //Process input data
        if ('' !== self::$opt_data) fwrite($pipes[0], self::$opt_data . PHP_EOL);
        //Build detailed results/logs
        $result = $logs = [];
        //Save logs
        if (self::$opt_log) {
            $logs['cmd'] = self::$cli_cmd;
            $logs['data'] = self::$opt_data;
            $logs['error'] = self::get_stream([$pipes[2]]);
            $logs['result'] = self::get_stream([$pipes[1]]);
            self::save_log($logs);
        }
        //Build results
        if ('' !== self::$opt_get) {
            if (false !== strpos(self::$opt_get, 'cmd')) $result['cmd'] = self::$cli_cmd;
            if (false !== strpos(self::$opt_get, 'data')) $result['data'] = self::$opt_data;
            if (false !== strpos(self::$opt_get, 'error')) $result['error'] = $logs['error'] ?? self::get_stream([$pipes[2]]);
            if (false !== strpos(self::$opt_get, 'result')) $result['result'] = $logs['result'] ?? self::get_stream([$pipes[1]]);
        }
        //Close all pipes
        foreach ($pipes as $pipe) fclose($pipe);
        //Close Process
        proc_close($process);
        unset($process, $pipes, $logs, $pipe);
        return $result;
    }

    /**
     * Start CLI
     *
     * @return array
     */
    public static function start(): array
    {
        //Parse options
        self::load_opt();
        //Detect CLI Mode
        if ('' !== self::$opt_cmd) {
            //Internal Mode
            //Build internal var
            self::build_var();
            //Call API
            return self::call_api();
        } else {
            //External Mode
            //Load CFG setting
            self::load_cfg();
            //Build external CMD
            self::build_cmd();
            //Run process
            return self::run_exec();
        }
    }
}
