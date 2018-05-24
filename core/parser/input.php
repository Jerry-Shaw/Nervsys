<?php

/**
 * Data Parser
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

use core\pool\order as pool_cmd;
use core\pool\config as pool_conf;
use core\pool\unit as pool_data;

class input
{
    /**
     * Prepare data
     */
    public static function prep_data(): void
    {
        if (pool_conf::$IS_CGI) {
            //Read HTTP
            self::read_http();

            //Read raw data
            self::read_raw();
        } else {
            //Read option
            $optind = self::read_opt();

            //Read argument
            self::read_argv($optind);

            unset($optind);
        }

        //Check cmd
        if ('' === pool_cmd::$cmd) {
            $val = self::opt_val(pool_data::$data, ['cmd', 'c']);

            if ($val['get'] && is_string($val['data']) && '' !== $val['data']) {
                pool_cmd::$cmd = &$val['data'];
            } else {
                //todo error (sys): cmd error
                exit;
            }

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
            pool_data::$data += $_FILES;
        }

        //Read POST
        if (!empty($_POST)) {
            pool_data::$data += $_POST;
        }

        //Read GET
        if (!empty($_GET)) {
            pool_data::$data += $_GET;
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
            pool_data::$data += $data;
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

        //Get cmd value
        $val = self::opt_val($opt, ['cmd', 'c']);

        if ($val['get'] && is_string($val['data']) && '' !== $val['data']) {
            pool_data::$data += [$val['key'] => $val['data']];
        }

        //Get cgi data value
        $val = self::opt_val($opt, ['data', 'd']);

        if ($val['get'] && is_string($val['data']) && '' !== $val['data']) {
            pool_data::$data += self::opt_data($val['data']);
        }

        //Get pipe data value
        $val = self::opt_val($opt, ['pipe', 'p']);

        if ($val['get'] && '' !== $val['data']) {
            pool_cmd::$param_cli['pipe'] = &$val['data'];
        }

        //Get pipe timeout value
        $val = self::opt_val($opt, ['time', 't']);

        if ($val['get'] && is_numeric($val['data'])) {
            pool_cmd::$param_cli['time'] = (int)$val['data'];
        }

        //Get return option
        $val = self::opt_val($opt, ['ret', 'r']);

        if ($val['get']) {
            pool_cmd::$param_cli['ret'] = true;
        }

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
        $argument = array_slice($_SERVER['argv'], $optind);

        if (empty($argument)) {
            return;
        }

        //Check cmd
        $value = self::opt_val(pool_data::$data, ['cmd', 'c']);

        !$value['get'] || !is_string($value['data']) || '' === $value['data']
            ? pool_data::$data['cmd'] = array_shift($argument)
            : pool_data::$data[$value['key']] = &$value['data'];

        //Set argument
        if (!empty($argument)) pool_cmd::$param_cli['argv'] = &$argument;

        unset($optind, $argument, $value);
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