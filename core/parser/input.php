<?php

/**
 * Input Parser
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

namespace core\parser;

use core\pool\process;
use core\pool\command;
use core\pool\setting;

class input extends process
{
    /**
     * Read input
     */
    public static function read(): void
    {
        if (setting::$is_cli) {
            //Read option & argument
            self::read_argv(self::read_opt());
        } else {
            //Read HTTP & input
            self::read_http();
            self::read_raw();
        }

        //Check command
        if ('' === command::$cmd
            && !empty($val = self::opt_val(self::$data, ['cmd', 'c']))
            && is_string($val['data'])
        ) {
            //Copy command
            command::$cmd = &$val['data'];
        }

        //Check format
        if (!empty($val = self::opt_val(self::$data, ['format', 'f'])) && is_string($val['data'])) {
            output::$method = &$val['data'];
        }

        unset($val);
    }

    /**
     * Read HTTP data
     */
    private static function read_http(): void
    {
        //Read FILES
        if (!empty($_FILES)) {
            self::$data += $_FILES;
        }

        //Read POST
        if (!empty($_POST)) {
            self::$data += $_POST;
        }

        //Read GET
        if (!empty($_GET)) {
            self::$data += $_GET;
        }
    }

    /**
     * Read raw data
     */
    private static function read_raw(): void
    {
        //Read data
        if (empty($input = file_get_contents('php://input'))) {
            return;
        }

        //Decode dara in JSON
        if (is_array($data = json_decode($input, true))) {
            self::$data += $data;
        }

        unset($input, $data);
    }

    /**
     * Read OPTION data
     *
     * @return int
     */
    private static function read_opt(): int
    {
        /**
         * CLI options
         *
         * c/cmd: commands (separated by "-" when multiple)
         * d/data: CGI data content
         * p/pipe: CLI pipe data content
         * t/time: read timeout (in microseconds; default "0" means read till done)
         * r/ret: process return option (Available in CLI executable mode only)
         */
        //Get options
        if (empty($opt = getopt('c:d:p:t:r', ['cmd:', 'data:', 'pipe', 'time:', 'ret'], $optind))) {
            return $optind;
        }

        //Get CMD value
        if (!empty($val = self::opt_val($opt, ['cmd', 'c'])) && is_string($val['data'])) {
            self::$data += [$val['key'] => data::decode($val['data'])];
        }

        //Get CGI data value
        if (!empty($val = self::opt_val($opt, ['data', 'd'])) && is_string($val['data'])) {
            self::$data += self::opt_data($val['data']);
        }

        //Get pipe data value
        if (!empty($val = self::opt_val($opt, ['pipe', 'p'])) && is_string($val['data'])) {
            command::$param_cli['pipe'] = data::decode($val['data']);
        }

        //Get pipe timeout value
        if (!empty($val = self::opt_val($opt, ['time', 't']))) {
            command::$param_cli['time'] = (int)$val['data'];
        }

        //Get return option
        command::$param_cli['ret'] = !empty(self::opt_val($opt, ['ret', 'r']));

        unset($opt, $val);
        return $optind;
    }

    /**
     * Read argument data
     *
     * @param int $optind
     */
    private static function read_argv(int $optind): void
    {
        //Extract arguments
        if (empty(command::$param_cli['argv'] = array_slice($_SERVER['argv'], $optind))) {
            return;
        }

        //Recheck CMD
        empty($value = self::opt_val(self::$data, ['cmd', 'c'])) || !is_string($value['data'])
            ? self::$data['cmd'] = data::decode(array_shift(command::$param_cli['argv']))
            : self::$data[$value['key']] = &$value['data'];

        unset($optind, $value);
    }

    /**
     * Extract values from options
     *
     * @param array $opt
     * @param array $keys
     *
     * @return array
     */
    private static function opt_val(array &$opt, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($opt[$key])) {
                $data = ['key' => &$key, 'data' => &$opt[$key]];

                unset($opt[$key], $keys, $key);
                return $data;
            }
        }

        unset($keys, $key);
        return [];
    }

    /**
     * Extract data from option
     *
     * @param string $value
     *
     * @return array
     */
    private static function opt_data(string $value): array
    {
        //Decode data in JSON/QUERY
        if (!is_array($data = json_decode(data::decode($value), true))) {
            parse_str($value, $data);
        }

        unset($value);
        return $data;
    }
}