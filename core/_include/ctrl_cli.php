<?php

/**
 * CLI Controlling Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author Yara <314850412@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
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
    //Variables
    public static $var = [];

    //Debug type and level
    public static $log = '';
    public static $debug = '';

    //CLI Command
    private static $cmd = '';

    //Configurations
    private static $cfg = [];

    //PHP Pipe settings
    const setting = [
        ['pipe', 'r'],
        ['pipe', 'w'],
        ['pipe', 'w']
    ];

    /**
     * Initial function
     */
    private static function cli_init()
    {
        //Load File Controlling Module
        load_lib('core', 'ctrl_file');
    }

    /**
     * Load CLI Configuration
     */
    private static function load_cfg()
    {
        if (is_file(CLI_CFG)) {
            //Get CFG content
            $json = \ctrl_file::get_content(CLI_CFG);
            if ('' !== $json) {
                $data = json_decode($json, true);
                if (isset($data)) self::$cfg = &$data;
                unset($data);
            }
            unset($json);
        }
    }

    /**
     * Create CMD
     */
    private static function get_cmd()
    {
        if (!empty(self::$var)) {
            //Check specific language in CFG
            if (isset(self::$cfg[self::$var[0]])) {
                //Rebuild all commands
                foreach (self::$var as $k => $v) if (isset(self::$cfg[$v])) self::$var[$k] = self::$cfg[$v];
                //Create command
                self::$cmd = implode(' ', self::$var);
            }
        }
    }

    /**
     * Save Log
     *
     * @param array $data
     */
    private static function cli_log(array $data)
    {
        $logs = [date('Y-m-d H:i:s', time())];
        switch (self::$log) {
            //Log cmd
            case 'cmd':
                $logs[] = "\t" . 'CMD: ' . self::$cmd;
                \ctrl_file::append_content(CLI_LOG_PATH . 'CLI_LOG_' . date('Y-m-d', time()) . '.txt', implode(PHP_EOL, $logs) . PHP_EOL . PHP_EOL);
                break;
            //Log err
            case 'err':
                if ('' !== $data['ERR']) {
                    $logs[] = "\t" . 'CMD: ' . self::$cmd;
                    $logs[] = "\t" . 'ERR: ' . $data['ERR'];
                    \ctrl_file::append_content(CLI_LOG_PATH . 'CLI_LOG_' . date('Y-m-d', time()) . '.txt', implode(PHP_EOL, $logs) . PHP_EOL . PHP_EOL);
                }
                break;
            //Log all
            case 'all':
                $logs[] = "\t" . 'CMD: ' . self::$cmd;
                $logs[] = "\t" . 'OUT: ' . $data['OUT'];
                $logs[] = "\t" . 'ERR: ' . $data['ERR'];
                \ctrl_file::append_content(CLI_LOG_PATH . 'CLI_LOG_' . date('Y-m-d', time()) . '.txt', implode(PHP_EOL, $logs) . PHP_EOL . PHP_EOL);
                break;
            //No log
            default:
                break;
        }
        unset($data, $logs);
    }

    /**
     * Show debug
     *
     * @param array $data
     */
    private static function cli_debug(array $data)
    {
        echo PHP_EOL . PHP_EOL . date('Y-m-d H:i:s', time()) . PHP_EOL . PHP_EOL;
        switch (self::$debug) {
            //Show cmd
            case 'cmd':
                echo 'CMD: ' . self::$cmd . PHP_EOL . PHP_EOL . PHP_EOL;
                break;
            //Show err
            case 'err':
                if ('' !== $data['ERR']) {
                    echo 'CMD: ' . self::$cmd . PHP_EOL . PHP_EOL;
                    echo 'ERR: ' . $data['ERR'] . PHP_EOL . PHP_EOL . PHP_EOL;
                }
                break;
            //Show all
            case 'all':
                echo 'CMD: ' . self::$cmd . PHP_EOL . PHP_EOL;
                echo 'OUT: ' . $data['OUT'] . PHP_EOL . PHP_EOL;
                echo 'ERR: ' . $data['ERR'] . PHP_EOL . PHP_EOL . PHP_EOL;
                break;
            //No debug
            default:
                break;
        }
        unset($data);
    }

    /**
     * Run CLI
     * @return array
     */
    public static function run_cli(): array
    {
        //Prepare
        self::cli_init();
        self::load_cfg();
        self::get_cmd();
        //Check command
        if ('' !== self::$cmd) {
            //Run process
            $process = proc_open(self::$cmd, self::setting, $pipe, CLI_WORK_PATH);
            //Parse result
            if (is_resource($process)) {
                //Parse details
                $data = ['OUT' => '', 'ERR' => stream_get_contents($pipe[2])];
                if ('' === $data['ERR']) $data['OUT'] = stream_get_contents($pipe[1]);
                //Save executed result
                $result = ['data' => &$data['OUT']];
                //Process debug and log
                if ('' !== self::$log) self::cli_log($data);
                if ('' !== self::$debug) self::cli_debug($data);
                unset($data);
            } else $result = ['data' => 'Process ERROR!'];
            //Close process
            $result['code'] = proc_close($process);
            unset($process, $pipe);
        } else $result = ['data' => 'Command ERROR!', 'code' => -1];
        return $result;
    }

    /**
     * Call API
     * @return array
     */
    public static function call_api(): array
    {
        //Load Data Controlling Module
        load_lib('core', 'data_pool');
        //Pass data to Data Controlling Module
        \data_pool::$cli = self::$var;
        //Start data_pool process
        \data_pool::start();
        //Get raw result
        return \data_pool::$pool;
    }
}
