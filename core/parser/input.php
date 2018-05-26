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

use core\pool\config;
use core\pool\order;
use core\pool\unit;

class input
{
    /**
     * Prepare data
     */
    public static function prep(): void
    {
        if (config::$IS_CGI) {
            //Read HTTP & input
            self::read_http();
            self::read_raw();
        } else {
            //Read option & argument
            $optind = self::read_opt();
            self::read_argv($optind);

            unset($optind);
        }

        //Check CMD
        if ('' === order::$cmd) {
            $val = self::opt_val(unit::$data, ['cmd', 'c']);

            $val['get'] && is_string($val['data']) && '' !== $val['data']
                ? order::$cmd = &$val['data']
                : trigger_error('Command NOT found!', E_USER_ERROR);

            unset($val);
        }
    }

    /**
     * Read HTTP data
     */
    private static function read_http(): void
    {
        //Read FILES
        if (!empty($_FILES)) {
            unit::$data += $_FILES;
        }

        //Read POST
        if (!empty($_POST)) {
            unit::$data += $_POST;
        }

        //Read GET
        if (!empty($_GET)) {
            unit::$data += $_GET;
        }
    }

    /**
     * Read raw data data
     */
    private static function read_raw(): void
    {
        $input = file_get_contents('php://input');

        if (false === $input || '' === $input) {
            return;
        }

        $data = json_decode($input, true);

        if (is_array($data) && !empty($data)) {
            unit::$data += $data;
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
        $opt = getopt('c:d:p:t:r', ['cmd:', 'data:', 'pipe', 'time:', 'ret'], $optind);

        if (empty($opt)) {
            return $optind;
        }

        //Get CMD value
        $val = self::opt_val($opt, ['cmd', 'c']);

        if ($val['get'] && is_string($val['data']) && '' !== $val['data']) {
            unit::$data += [$val['key'] => $val['data']];
        }

        //Get CGI data value
        $val = self::opt_val($opt, ['data', 'd']);

        if ($val['get'] && is_string($val['data']) && '' !== $val['data']) {
            unit::$data += self::opt_data($val['data']);
        }

        //Get pipe data value
        $val = self::opt_val($opt, ['pipe', 'p']);
        order::$param_cli['pipe'] = $val['get'] ? (string)$val['data'] : '';

        //Get pipe timeout value
        $val = self::opt_val($opt, ['time', 't']);
        order::$param_cli['time'] = $val['get'] ? (int)$val['data'] : 0;

        //Get return option
        $val = self::opt_val($opt, ['ret', 'r']);
        order::$param_cli['ret'] = &$val['get'];

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
        order::$param_cli['argv'] = array_slice($_SERVER['argv'], $optind);

        if (empty(order::$param_cli['argv'])) {
            return;
        }

        //Recheck CMD
        $value = self::opt_val(unit::$data, ['cmd', 'c']);

        !$value['get'] || !is_string($value['data']) || '' === $value['data']
            ? unit::$data['cmd'] = array_shift(order::$param_cli['argv'])
            : unit::$data[$value['key']] = &$value['data'];

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
        $result = ['get' => false, 'key' => '', 'data' => ''];

        foreach ($keys as $key) {
            if (isset($opt[$key])) {
                $result['get'] = true;
                $result['key'] = $key;
                $result['data'] = $opt[$key];

                unset($opt[$key]);
                return $result;
            }
        }

        unset($keys, $key);
        return $result;
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
        //Decode data in JSON
        $data = json_decode($value, true);

        //Decode data in HTTP Query
        if (!is_array($data)) {
            parse_str($value, $data);
        }

        unset($value);
        return $data;
    }
}