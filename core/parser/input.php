<?php

/**
 * Input Data Parser
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

use core\module\data;

class input
{
    /**
     * Parse input data
     */
    public static function prep_data(): void
    {
        if ('cli' === data::$mode['sapi']) {
            //Read option
            $optind = self::read_opt();

            //Read argument
            self::read_argv($optind);

            unset($optind);
        } else {
            //Read HTTP
            self::read_http();

            //Read raw input
            self::read_raw();
        }

        //Extract cmd value
        $val = self::opt_val(data::$data, ['cmd', 'c']);

        if (!$val['get'] || '' === $val['data']) {
            return;
        }

        //Extract cmd
        data::$cmd['cgi'] = data::$cmd['cli'] = false !== strpos($val['data'], '-') ? explode('-', $val['data']) : [$val['data']];

        //Prepare CGI cmd
        self::prep_cgi();

        //Prepare CLI cmd
        self::prep_cli();

        unset($val);
    }

    /**
     * Prepare cgi cmd
     */
    private static function prep_cgi(): void
    {
        if (empty(data::$conf['CGI'])) {
            return;
        }

        //Mapping CGI config
        foreach (data::$conf['CGI'] as $name => $item) {
            $key = array_search($name, data::$cmd['cgi'], true);

            if (false !== $key) {
                data::$cmd['cgi'][$key] = $item;
                data::$cgi[$item] = $name;
            } else {
                foreach (data::$cmd['cgi'] as $key => $val) {
                    if (0 !== strpos($val, $name)) {
                        continue;
                    }

                    $cmd = substr_replace($val, $item, 0, strlen($name));

                    data::$cmd['cgi'][$key] = $cmd;
                    data::$cgi[$cmd] = $val;
                }
            }
        }

        unset($name, $item, $key, $val, $cmd);
    }

    /**
     * Prepare cli cmd
     */
    public static function prep_cli(): void
    {
        if (empty(data::$conf['CLI'])) {
            data::$cmd['cli'] = [];
            return;
        }

        //Check CLI config
        foreach (data::$cmd['cli'] as $key => $item) {
            if (!isset(data::$conf['CLI'][$item])) {
                unset(data::$cmd['cli'][$key]);
            }
        }

        unset($key, $item);
    }

    /**
     * Read HTTP data
     */
    private static function read_http(): void
    {
        //Read FILES
        if (!empty($_FILES)) {
            data::$data += $_FILES;
        }

        //Read POST
        if (!empty($_POST)) {
            data::$data += $_POST;
        }

        //Read GET
        if (!empty($_GET)) {
            data::$data += $_GET;
        }
    }

    /**
     * Read raw input data
     */
    private static function read_raw(): void
    {
        $input = file_get_contents('php://input');

        if (false === $input || '' === $input) {
            return;
        }

        $data = json_decode($input, true);

        if (is_array($data) && !empty($data)) {
            data::$data += $data;
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

        if ($val['get'] && '' !== $val['data']) {
            data::$data['cmd'] = &$val['data'];
        }

        //Get cgi data value
        $val = self::opt_val($opt, ['data', 'd']);

        if ($val['get'] && '' !== $val['data']) {
            data::$data += self::opt_data($val['data']);
        }

        //Get pipe data value
        $val = self::opt_val($opt, ['pipe', 'p']);

        if ($val['get'] && '' !== $val['data']) {
            data::$cli['pipe'] = &$val['data'];
        }

        //Get pipe timeout value
        $val = self::opt_val($opt, ['time', 't']);

        if ($val['get'] && is_numeric($val['data'])) {
            data::$cli['time'] = (int)$val['data'];
        }

        //Get return option
        $val = self::opt_val($opt, ['ret', 'r']);

        if ($val['get']) {
            data::$cli['ret'] = true;
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

        //Check cmd value
        $value = self::opt_val(data::$data, ['cmd', 'c']);
        data::$data['cmd'] = $value['get'] ? $value['data'] : array_shift($argument);

        //Set arguments
        if (!empty($argument)) data::$cli['argv'] = &$argument;

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
        $result = ['get' => false];

        foreach ($keys as $key) {
            if (!isset($opt[$key])) continue;

            $result['get'] = true;
            $result['data'] = $opt[$key];

            unset($opt[$key]);
            return $result;
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