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
    public static function parse(): void
    {
        if ('cli' === data::$mode) {
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

        if ($val['get'] && '' !== $val['data']) {
            data::$cmd['cmd'] = &$val['data'];
        }

        unset($val);
    }



    public static function prep_cmd(): void
    {
        //Check cmd value
        if (!isset(data::$cmd['cmd']) || '' === data::$cmd['cmd']){
            return;
        }

        //Extract cmd
        $cmd = false !== strpos(data::$cmd['cmd'], '-') ? explode('-', data::$cmd['cmd']) : [data::$cmd['cmd']];

        //Prepare CGI cmd
        self::prep_cgi($cmd);


        exit;



        //Explode command
        $data = false !== strpos(self::$cmd, '-') ? explode('-', self::$cmd) : ('' !== self::$cmd ? [self::$cmd] : []);
        foreach ($data as $item) if (isset(self::$conf_cli[$item])) self::$cli_cmd[] = $item;
        self::$cli_cmd = array_unique(self::$cli_cmd);

        unset($data, $item);
    }


    /**
     * Prepare cgi cmd
     *
     * @param array $cmd
     */
    private static function prep_cgi(array $cmd): void
    {
        //Map CGI config
        if (isset(data::$conf['CGI']) && !empty(data::$conf['CGI'])) {
            foreach (data::$conf['CGI'] as $name => $item) {
                $key = array_search($name, $cmd, true);

                if (false !== $key) {
                    $cmd[$key] = $item;
                    data::$cgi[$item] = $name;
                } else {
                    foreach ($cmd as $key => $val) {
                        if (0 !== strpos($val, $name)) {
                            continue;
                        }

                        $cmd = substr_replace($val, $item, 0, strlen($name));

                        $cmd[$key] = $cmd;
                        data::$cgi[$cmd] = $val;
                    }
                }
            }
        }

        //Add LOAD config
        if (isset(data::$conf['LOAD']) && !empty(data::$conf['LOAD'])) {


            var_dump(data::$conf['LOAD']);


        }






        //Copy cmd
        //data::$cmd['cgi'] = array_unique($cmd);
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