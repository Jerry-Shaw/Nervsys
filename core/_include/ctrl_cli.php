<?php

/**
 * CLI Controlling Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author Yara <314850412@qq.com>
 *
 * Copyright 2016-2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 * Copyright 2017 Yara
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

    //STDIN data status
    private static $input = false;

    //wait for output
    private static $output = false;

    //Debug/Log options
    private static $debug = '';
    private static $log = '';

    //Input data
    private static $data = '';

    //CLI Config file path
    private static $cfg = CLI_CFG;

    //Stream check retry times
    private static $try = CLI_STREAM_TRY;

    //Stream check wait time
    private static $wait = CLI_STREAM_WAIT;

    //CLI Command
    private static $cmd = '';

    //Configurations
    private static $setting = [];

    //PHP Pipe settings
    const setting = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];

    /**
     * Load options
     */
    private static function load_opt()
    {
        if (!empty(self::$opt)) {
            //Get STDIN existence
            if (isset(self::$opt['i'])) self::$input = true;
            //Parse CFG option
            if (isset(self::$opt['c']) && false !== self::$opt['c'] && '' !== self::$opt['c']) self::$cfg = self::$opt['c'];
            //Parse input data content
            if (isset(self::$opt['data']) && false !== self::$opt['data'] && '' !== self::$opt['data']) self::$data = self::$opt['data'];
            //Parse debug/log options
            if (isset(self::$opt['d']) && in_array(self::$opt['d'], ['cmd', 'err', 'all'], true)) self::$debug = self::$opt['d'];
            if (isset(self::$opt['l']) && in_array(self::$opt['l'], ['cmd', 'err', 'all'], true)) self::$log = self::$opt['l'];
            //Parse retry option
            if (isset(self::$opt['t'])) {
                self::$opt['t'] = (int)self::$opt['t'];
                if (0 < self::$opt['t']) self::$try = self::$opt['t'];
            }
            //Parse wait option
            if (isset(self::$opt['w'])) {
                self::$output = true;
                self::$opt['w'] = (int)self::$opt['w'];
                if (0 < self::$opt['w']) self::$wait = self::$opt['w'];
            }
            //Get STDIN data instead of input data
            if ('' === self::$data && self::$input && self::wait_stream([STDIN])) self::$data = trim(stream_get_contents(STDIN));
        }
    }

    /**
     * Load CFG settings
     */
    private static function load_cfg()
    {
        //Check CFG file
        if (is_file(self::$cfg)) {
            //Load File Controlling Module
            load_lib('core', 'ctrl_file');
            //Get CFG file content
            $json = \ctrl_file::get_content(self::$cfg);
            if ('' !== $json) {
                //Decode file content and map to CFG
                $data = json_decode($json, true);
                if (isset($data)) self::$setting = &$data;
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
        self::$var = ['cmd' => self::$opt['cmd']];
        //Merge "map" data when exists
        if (isset(self::$opt['map']) && false !== self::$opt['map'] && '' !== self::$opt['map']) self::$var['map'] = self::$opt['map'];
        //Process input data
        if ('' !== self::$data) {
            //Parse HTTP query data
            parse_str(self::$data, $data);
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
            //Check specific language in CFG
            if (isset(self::$setting[self::$var[0]])) {
                //Rebuild all commands
                foreach (self::$var as $k => $v) if (isset(self::$setting[$v])) self::$var[$k] = self::$setting[$v];
                //Create command
                self::$cmd = implode(' ', self::$var);
                unset($k, $v);
            }
        }
    }

    /**
     * Check the stat of current stream
     *
     * @param array $stream
     *
     * @return bool
     */
    private static function wait_stream(array $stream): bool
    {
        $times = 0;
        $result = false;
        //Get the resource
        $resource = current($stream);
        //Keep checking the stat of stream
        while ($times < self::$try) {
            ++$times;
            //Wait for process
            usleep(self::$wait);
            //Get the stat of stream
            $stat = fstat($resource);
            //Check the stat of stream
            if (false !== $stat && 0 < $stat['size']) {
                $result = true;
                break;
            }
        }
        //Return false once the elapsed time reaches the limit
        unset($stream, $times, $resource, $stat);
        return $result;
    }

    /**
     * Get Logs
     *
     * @param string $level
     * @param array $data
     *
     * @return array
     */
    private static function get_logs(string $level, array $data): array
    {
        $logs = [date('Y-m-d H:i:s', time())];
        switch ($level) {
            //Log cmd
            case 'cmd':
                $logs[] = 'CMD: ' . self::$cmd;
                break;
            //Log err
            case 'err':
                $logs[] = 'CMD: ' . self::$cmd;
                $logs[] = '' !== $data['ERR'] ? 'ERR: ' . $data['ERR'] : 'ERR: NONE!';
                break;
            //Log all
            case 'all':
                $logs[] = 'CMD: ' . self::$cmd;
                $logs[] = '' !== $data['IN'] ? 'IN:  ' . $data['IN'] : 'IN:  NONE!';
                $logs[] = '' !== $data['OUT'] ? 'OUT: ' . $data['OUT'] : 'OUT: NONE!';
                $logs[] = '' !== $data['ERR'] ? 'ERR: ' . $data['ERR'] : 'ERR: NONE!';
                break;
            //No detailed logs
            default:
                break;
        }
        unset($level, $data);
        return $logs;
    }

    /**
     * Run external process
     *
     * @return array
     */
    private static function run_exec(): array
    {
        //Check command
        if ('' !== self::$cmd) {
            //Run process
            $process = proc_open(self::$cmd, self::setting, $pipes, CLI_WORK_PATH);
            //Parse process data
            if (is_resource($process)) {
                //Process input data
                if ('' !== self::$data) fwrite($pipes[0], self::$data . PHP_EOL);
                //Build detailed STDIO data when needed
                if (self::$output || '' !== self::$debug || '' !== self::$log) {
                    //Add input data
                    $data = ['IN' => self::$data];
                    //Process STDOUT/STDERR data
                    $data['OUT'] = self::wait_stream([$pipes[1]]) ? trim(stream_get_contents($pipes[1])) : '';
                    $data['ERR'] = self::wait_stream([$pipes[2]]) ? trim(stream_get_contents($pipes[2])) : '';
                    //Process debug and log
                    if ('' !== self::$debug) fwrite(STDOUT, PHP_EOL . implode(PHP_EOL, self::get_logs(self::$debug, $data)) . PHP_EOL);
                    if ('' !== self::$log) \ctrl_file::append_content(CLI_LOG_PATH . date('Y-m-d', time()) . '.log', PHP_EOL . implode(PHP_EOL, self::get_logs(self::$log, $data)) . PHP_EOL);
                    //Save output data when needed
                    $output = self::$output ? ['data' => &$data['OUT'], 'error' => &$data['ERR']] : ['data' => 'NOT Requested!', 'error' => 'NOT Requested!'];
                    unset($data);
                } else $output = ['data' => 'NOT Requested!', 'error' => 'NOT Requested!'];
                //Close all pipes
                foreach ($pipes as $pipe) fclose($pipe);
                //Close process
                $result['code'] = proc_close($process);
                //Merge result
                $result = array_merge($result, $output);
                unset($pipe, $output);
            } else $result = ['code' => -2, 'error' => 'Process ERROR!'];
            unset($process, $pipes);
        } else $result = ['code' => -1, 'error' => 'Command ERROR!'];
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
        if (isset(self::$opt['cmd']) && false !== self::$opt['cmd'] && '' !== self::$opt['cmd']) {
            //Internal Mode
            //Build internal var
            self::build_var();
            //Load Data Controlling Module
            load_lib('core', 'data_pool');
            //Pass data to Data Controlling Module
            \data_pool::$cli = self::$var;
            //Start data_pool process
            \data_pool::start();
            //Get raw result
            return \data_pool::$pool;
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