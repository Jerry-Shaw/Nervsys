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

use core\parser\cmd;
use core\parser\input;

use core\pool\setting;

class system
{
    /**
     * System load
     */
    public static function load(): void
    {
        //Define NervSys version
        define('NS_VER', '6.2.6');

        //Define absolute root path
        define('ROOT', substr(strtr(__DIR__, ['/' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR]), 0, -4));

        //Register autoload function
        spl_autoload_register(
            static function (string $class): void
            {
                if (false !== strpos($class, '\\')) {
                    //Load from namespace path
                    require ROOT . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
                } else {
                    //Load from include path
                    require $class . '.php';
                }

                unset($class);
            }
        );
    }

    /**
     * System start
     */
    public static function start(): void
    {
        //Track error
        error::track();

        //Parse setting
        self::parse();

        //Runtime detection
        self::detect();

        //Call INIT
        if (!empty(setting::$init)) {
            operator::init_load(setting::$init);
        }

        //Read input
        input::read();

        //Prepare CMD
        cmd::prep();

        //Run CLI process
        if (!setting::$is_cgi) {
            operator::run_cli();
        }

        //Run CGI process
        operator::run_cgi();
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
     * Parse settings
     */
    private static function parse(): void
    {
        //Read settings
        if (false === $conf = parse_ini_file(setting::PATH, true)) {
            return;
        }

        //Parse include path
        if (isset($conf['PATH']) && !empty($conf['PATH'])) {
            //Format paths
            $conf['PATH'] = array_map(
                static function (string $path): string
                {
                    $path = strtr($path, '\\', DIRECTORY_SEPARATOR);
                    $path = rtrim($path, DIRECTORY_SEPARATOR);

                    //Set relative/absolute paths
                    $path = 0 !== strpos($path, '/') && false === strpos($path, ':', 1)
                        ? ROOT . $path . DIRECTORY_SEPARATOR
                        : $path . DIRECTORY_SEPARATOR;

                    return $path;
                }, $conf['PATH']
            );

            //Set include path
            set_include_path(implode(PATH_SEPARATOR, $conf['PATH']));
        }

        //Set setting values
        foreach ($conf as $key => $val) {
            $key = strtolower($key);

            if (isset(setting::$$key)) {
                setting::$$key = $val;
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
        setting::$is_cgi = 'cli' !== PHP_SAPI;

        //Detect HTTPS protocol
        setting::$is_https = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        //Detect Cross-origin resource sharing permission
        if (
            empty(setting::$cors)
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (setting::$is_https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
        ) {
            return;
        }

        //CORS access denied
        if (!isset(setting::$cors[$_SERVER['HTTP_ORIGIN']])) {
            operator::stop('Access NOT permitted!');
        }

        //Response Access-Control-Allow-Origin & Access-Control-Allow-Headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . setting::$cors[$_SERVER['HTTP_ORIGIN']]);

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            exit;
        }
    }
}