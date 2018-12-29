<?php

/**
 * System script
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

namespace core;

use core\handler\error;
use core\handler\operator;
use core\handler\platform;

use core\parser\cmd;
use core\parser\input;
use core\parser\output;

use core\pool\command;

class system extends command
{
    //Setting file path
    const PATH = __DIR__ . DIRECTORY_SEPARATOR . 'system.ini';

    /**
     * System start
     */
    public static function start(): void
    {
        //Track error
        error::track();

        //Parse & detect
        self::parse();
        self::detect();

        //Initialize
        operator::init();

        //Read input
        input::read();

        //Parse CMD
        cmd::parse();

        //Run CGI process
        operator::run_cgi();

        //Run CLI process
        operator::run_cli();

        //Flush output content
        output::flush();
    }

    /**
     * System stop
     */
    public static function stop(): void
    {
        output::flush();
        exit;
    }

    /**
     * Get IP
     *
     * @return string
     */
    public static function get_ip(): string
    {
        //IP check list
        $chk_list = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        //Check ip values
        foreach ($chk_list as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }

            $ip_list = false !== strpos($_SERVER[$key], ',') ? explode(',', $_SERVER[$key]) : [$_SERVER[$key]];

            foreach ($ip_list as $ip) {
                $ip = filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);

                if (false !== $ip) {
                    unset($chk_list, $key, $ip_list);
                    return $ip;
                }
            }
        }

        unset($chk_list, $key, $ip_list, $ip);
        return '0.0.0.0';
    }

    /**
     * Add CGI job
     *
     * @param string $class
     * @param string ...$method
     */
    public static function add_cgi(string $class, string ...$method): void
    {
        parent::$cmd_cgi[] = func_get_args();
        unset($class, $method);
    }

    /**
     * Add CLI job
     *
     * @param string $cmd
     * @param string $argv
     * @param string $pipe
     * @param int    $time
     * @param bool   $ret
     *
     * @throws \Exception
     */
    public static function add_cli(string $cmd, string $argv = '', string $pipe = '', int $time = 0, bool $ret = false): void
    {
        if (!parent::$is_cli) {
            throw new \Exception('Operation NOT permitted!', E_USER_WARNING);
        }

        if ('PHP' === $cmd) {
            parent::$cli['PHP'] = platform::sys_path();
        }

        if (!isset(parent::$cli[$cmd])) {
            throw new \Exception('"' . $cmd . '" NOT defined!', E_USER_WARNING);
        }

        $cmd_cli = [
            'key'  => &$cmd,
            'cmd'  => parent::$cli[$cmd],
            'ret'  => &$ret,
            'time' => &$time
        ];

        if ('' !== $pipe) {
            $cmd_cli['pipe'] = $pipe . PHP_EOL;
        }

        if ('' !== $argv) {
            $cmd_cli['argv'] = ' ' . $argv;
        }

        parent::$cmd_cli[] = &$cmd_cli;
        unset($cmd, $argv, $pipe, $time, $ret, $cmd_cli);
    }

    /**
     * Build dependency list
     *
     * @param array $dep_list
     */
    protected static function build_dep(array &$dep_list): void
    {
        foreach ($dep_list as $key => $dep) {
            //Parse dependency
            if (false === strpos($dep, '-')) {
                $order  = $dep;
                $method = '__construct';
            } else {
                list($order, $method) = explode('-', $dep, 2);
            }

            //Rebuild list
            $dep_list[$key] = [$order, self::build_name($order), $method];
        }

        unset($key, $dep, $order, $method);
    }

    /**
     * Build class name
     *
     * @param string $class
     *
     * @return string
     */
    protected static function build_name(string $class): string
    {
        return '\\' . trim(strtr($class, '/', '\\'), '\\');
    }

    /**
     * Parse settings
     */
    private static function parse(): void
    {
        //Read settings
        if (false === $conf = parse_ini_file(self::PATH, true)) {
            return;
        }

        //Set include path
        if (isset($conf['PATH']) && !empty($conf['PATH'])) {
            $conf['PATH'] = array_map(
                static function (string $path): string
                {
                    $path = rtrim(strtr($path, ['/' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR]), DIRECTORY_SEPARATOR);

                    if (0 !== strpos($path, '/') && 1 !== strpos($path, ':')) {
                        $path = ROOT . $path;
                    }

                    return $path . DIRECTORY_SEPARATOR;
                }, $conf['PATH']
            );

            set_include_path(implode(PATH_SEPARATOR, $conf['PATH']));
        }

        //Set setting values
        foreach ($conf as $key => $val) {
            $key = strtolower($key);

            if (isset(self::$$key)) {
                self::$$key = $val;
            }
        }

        unset($conf, $key, $val);
    }

    /**
     * Runtime value detections
     */
    private static function detect(): void
    {
        //Detect running mode
        self::$is_cli = 'cli' === PHP_SAPI;

        //Detect HTTPS protocol
        self::$is_https = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        //Detect Cross-origin resource sharing authority
        if (empty(self::$cors)
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (self::$is_https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
            return;
        }

        //Exit on no access authority
        if (is_null($allow_headers = self::$cors[$_SERVER['HTTP_ORIGIN']] ?? self::$cors['*'] ?? null)) {
            exit;
        }

        //Response access allowed headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        unset($allow_headers);

        //Exit on OPTION request
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            exit;
        }
    }
}