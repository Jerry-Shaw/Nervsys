<?php

/**
 * CLI Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author Yara <314850412@qq.com>
 * Author 李盛青 <happyxiaohang@163.com>
 *
 * Copyright 2016-2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 * Copyright 2017 Yara
 * Copyright 2017 李盛青
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
class ctrl_cli
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
    private static $opt_try = 100;//Default try times for stream checking
    private static $opt_wait = 2;//Default time wait for stream checking (in microseconds)
    private static $opt_data = '';//Request data, will try to read STDIN when empty
    private static $opt_path = ROOT . '/_cli/cfg.json';//Default CFG file path

    //CLI Runtime Settings
    private static $cli_cmd = '';//CLI Command
    private static $cli_cfg = [];//CLI Configurations

    /**
     * Load options
     */
    private static function load_opt()
    {
        if (!empty(self::$opt)) {
            //Get "log" option
            if (isset(self::$opt['log']) || isset(self::$opt['l'])) self::$opt_log = true;
            //Get "cmd" option
            if (isset(self::$opt['cmd']) && false !== self::$opt['cmd'] && '' !== self::$opt['cmd']) self::$opt_cmd = self::$opt['cmd'];
            elseif (isset(self::$opt['c']) && false !== self::$opt['c'] && '' !== self::$opt['c']) self::$opt_cmd = self::$opt['c'];
            //Get "map" option
            if (isset(self::$opt['map']) && false !== self::$opt['map'] && '' !== self::$opt['map']) self::$opt_map = self::$opt['map'];
            elseif (isset(self::$opt['m']) && false !== self::$opt['m'] && '' !== self::$opt['m']) self::$opt_map = self::$opt['m'];
            //Get "get" option
            if (isset(self::$opt['get']) && false !== self::$opt['get'] && '' !== self::$opt['get']) self::$opt_get = self::$opt['get'];
            elseif (isset(self::$opt['g']) && false !== self::$opt['g'] && '' !== self::$opt['g']) self::$opt_get = self::$opt['g'];
            //Get "path" option
            if (isset(self::$opt['path']) && false !== self::$opt['path'] && '' !== self::$opt['path']) self::$opt_path = self::$opt['path'];
            elseif (isset(self::$opt['p']) && false !== self::$opt['p'] && '' !== self::$opt['p']) self::$opt_path = self::$opt['p'];
            //Get "data" from option/STDIN
            if (isset(self::$opt['data']) && false !== self::$opt['data'] && '' !== self::$opt['data']) self::$opt_data = self::$opt['data'];
            elseif (isset(self::$opt['d']) && false !== self::$opt['d'] && '' !== self::$opt['d']) self::$opt_data = self::$opt['d'];
            else self::$opt_data = self::get_stream([STDIN]);
            //Get "try" option
            if (isset(self::$opt['try'])) {
                self::$opt['try'] = (int)self::$opt['try'];
                if (0 < self::$opt['try']) self::$opt_try = self::$opt['try'];
            } elseif (isset(self::$opt['t'])) {
                self::$opt['t'] = (int)self::$opt['t'];
                if (0 < self::$opt['t']) self::$opt_try = self::$opt['t'];
            }
            //Get "wait" option
            if (isset(self::$opt['wait'])) {
                self::$opt['wait'] = (int)self::$opt['wait'];
                if (0 < self::$opt['wait']) self::$opt_wait = self::$opt['wait'];
            } elseif (isset(self::$opt['w'])) {
                self::$opt['w'] = (int)self::$opt['w'];
                if (0 < self::$opt['w']) self::$opt_wait = self::$opt['w'];
            }
        }
    }

    /**
     * Load configurations
     */
    private static function load_cfg()
    {
        //Check CFG file
        if (is_file(self::$opt_path)) {
            //Get CFG file content
            $json = (string)file_get_contents(self::$opt_path);
            if ('' !== $json) {
                //Decode file content and map to CFG
                $data = json_decode($json, true);
                if (isset($data)) self::$cli_cfg = &$data;
                unset($data);
            }
            unset($json);
        }
    }

    /**
     * Build var for Internal Mode
     */
    private static function build_var()
    {
        //Regroup request data
        self::$var = ['cmd' => self::$opt_cmd];
        //Merge "map" data when exists
        if ('' !== self::$opt_map) self::$var['map'] = self::$opt_map;
        //Process input data
        if ('' !== self::$opt_data) {
            //Parse HTTP query data
            parse_str(self::$opt_data, $data);
            //Merge input data when exists
            if (!empty($data)) self::$var = array_merge(self::$var, $data);
            unset($data);
        }
    }

    /**
     * Build CMD for External Mode
     */
    private static function build_cmd()
    {
        //Check variables
        if (!empty(self::$var)) {
            //Check specific language in configurations
            if (isset(self::$cli_cfg[self::$var[0]])) {
                //Rebuild all commands
                foreach (self::$var as $k => $v) if (isset(self::$cli_cfg[$v])) self::$var[$k] = self::$cli_cfg[$v];
                //Create command
                self::$cli_cmd = implode(' ', self::$var);
                unset($k, $v);
            }
        }
    }

    /**
     * Save logs
     *
     * @param array $data
     */
    private static function save_log(array $data)
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
            } else {
                //Wait for process
                usleep(self::$opt_wait);
                ++$try;
            }
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
        //Load Data Module
        load_lib('core', 'data_pool');
        //Pass data to Data Module
        \data_pool::$cli = self::$var;
        //Start data_pool process
        \data_pool::start();
        //Get API Result
        $data = \data_pool::$pool;
        //Save logs
        if (self::$opt_log) {
            $logs = ['cmd' => self::$opt_cmd];
            $logs['map'] = self::$opt_map;
            $logs['data'] = self::$opt_data;
            $logs['result'] = json_encode($data);
            self::save_log($logs);
            unset($logs);
        }
        //Build results
        if ('' !== self::$opt_get) {
            if (false !== strpos(self::$opt_get, 'cmd')) $result['cmd'] = self::$opt_cmd;
            if (false !== strpos(self::$opt_get, 'map')) $result['map'] = self::$opt_map;
            if (false !== strpos(self::$opt_get, 'data')) $result['data'] = self::$opt_data;
            if (false !== strpos(self::$opt_get, 'result')) $result['result'] = &$data;
        }
        unset($data);
        return $result;
    }

    /**
     * Run External Process
     *
     * @return array
     */
    private static function run_exec(): array
    {
        //Check command
        if ('' !== self::$cli_cmd) {
            //Run process
            $process = proc_open(self::$cli_cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, CLI_WORK_PATH);
            //Parse process data
            if (is_resource($process)) {
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
                unset($logs, $pipe);
            } else $result = ['error' => 'Process ERROR!'];
            unset($process, $pipes);
        } else $result = ['error' => 'Command ERROR!'];
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