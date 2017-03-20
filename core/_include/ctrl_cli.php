<?php

/**
 * CLI Controlling Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
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
class ctrl_cli
{
    //variables
    public static $var = [];

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
        if (!empty(self::$var) && 1 < count(self::$var)) {
            //Escape the first variable
            $var = array_slice(self::$var, 1);
            //Check specific language in CFG
            if (isset(self::$cfg[$var[0]])) {
                //Change to full executing path
                $var[0] = self::$cfg[$var[0]];
                //Create command
                self::$cmd = implode(' ', $var);
            }
            unset($var);
        }
    }

    /**
     * Save Logs
     * @param array $data
     */
    private static function save_log(array $data)
    {
        $logs = [date('Y-m-d H:i:s', time())];
        switch (CLI_DEBUG_MODE) {
            //Log errors
            case 1:
                if ('' !== $data[2]) {
                    $logs[] = "\t" . 'CMD: ' . self::$cmd;
                    $logs[] = "\t" . 'ERR: ' . $data[2];
                    \ctrl_file::append_content(CLI_LOG_PATH . 'CLI_LOG_' . date('Y-m-d', time()) . '.txt', implode(PHP_EOL, $logs) . PHP_EOL . PHP_EOL);
                }
                break;
            //Log details
            case 2:
                $logs[] = "\t" . 'CMD: ' . self::$cmd;
                $logs[] = "\t" . 'IN:  ' . $data[0];
                $logs[] = "\t" . 'OUT: ' . $data[1];
                $logs[] = "\t" . 'ERR: ' . $data[2];
                \ctrl_file::append_content(CLI_LOG_PATH . 'CLI_LOG_' . date('Y-m-d', time()) . '.txt', implode(PHP_EOL, $logs) . PHP_EOL . PHP_EOL);
                break;
            //No log
            default:
                break;
        }
        unset($logs);
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
            $process = proc_open(self::$cmd, self::setting, $pipe, CLI_WORKING_PATH);
            //Parse result
            if (is_resource($process)) {
                //Get details
                $data = [];
                $data[] = isset($pipe[0]) ? trim(stream_get_contents($pipe[0])) : '';
                $data[] = isset($pipe[1]) ? trim(stream_get_contents($pipe[1])) : '';
                $data[] = isset($pipe[2]) ? trim(stream_get_contents($pipe[2])) : '';
                //Save executed result
                $result = ['data' => $data[1]];
                //Save log
                self::save_log($data);
                unset($data);
            } else $result = ['data' => 'Command ERROR!'];
            //Close process
            $result['code'] = proc_close($process);
            unset($process);
        } else $result = ['data' => 'Command ERROR!', 'code' => -1];
        return $result;
    }
}