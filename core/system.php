<?php

/**
 * System script
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

use core\handler\operator;
use core\handler\platform;

use core\parser\cmd;
use core\parser\input;
use core\parser\output;

use core\pool\command;

class system extends command
{
    //Config file path
    const CFG_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'system.ini';

    /**
     * Boot system
     *
     * @param int $state
     *
     * @throws \Exception
     */
    public static function boot(int $state = 0): void
    {
        /**
         * Prepare state (S1)
         * Initialize system
         *
         * Steps:
         * 1. Load "system.ini" and parse settings.
         * 2. Set runtime values, detect CGI/CLI and TLS.
         * 3. Check Cross-Origin Resource Sharing (CORS) permissions.
         * 4. Execute all configured settings in "init" section of "system.ini".
         * 5. Read and parse input data. Save to process pool in non-overwrite mode.
         */

        self::load_cfg();
        self::config_env();
        self::check_cors();

        !empty(self::$init) && operator::run_dep(self::$init, E_USER_ERROR);

        input::read();

        //S1 exit control
        if (1 === $state) {
            return;
        }

        /**
         * Process state (S2)
         * Execute commands and gather results
         *
         * Steps:
         * 1. Prepare commands. Skip when already set.
         * 2. Call script functions order by commands via CGI mode.
         * 3. Call script functions and external commands via CLI mode (available under CLI).
         * 4. Gathering results on calling every function or external command. Save to process result pool.
         */

        '' !== parent::$cmd && cmd::prepare();

        operator::run_cgi();
        operator::run_cli();

        //S2 exit control
        if (2 === $state) {
            return;
        }

        /**
         * Flush state (S3)
         * Output results in preset format
         *
         * Steps:
         * 1. Output MIME-Type header.
         * 2. Reduce array result on single command.
         * 3. Output result content according to preset format.
         */

        output::flush();
    }

    /**
     * Stop system
     */
    public static function stop(): void
    {
        output::flush() && exit;
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
                if (false !== $ip = filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
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
        self::$cmd_cgi[] = func_get_args();
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
        if (!self::$is_CLI) {
            throw new \Exception('Operation NOT permitted!', E_USER_WARNING);
        }

        if ('PHP' === $cmd) {
            self::$cli['PHP'] = platform::sys_path();
        }

        if (!isset(self::$cli[$cmd])) {
            throw new \Exception('"' . $cmd . '" NOT defined!', E_USER_WARNING);
        }

        $cmd_cli = [
            'key'  => &$cmd,
            'cmd'  => self::$cli[$cmd],
            'ret'  => &$ret,
            'time' => &$time
        ];

        if ('' !== $pipe) {
            $cmd_cli['pipe'] = $pipe . PHP_EOL;
        }

        if ('' !== $argv) {
            $cmd_cli['argv'] = ' ' . $argv;
        }

        self::$cmd_cli[] = &$cmd_cli;

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
     * Load configuration settings
     */
    private static function load_cfg(): void
    {
        //Load configuration file
        $conf = parse_ini_file(self::CFG_FILE, true);

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
     * Load environment values
     */
    private static function config_env(): void
    {
        //Set runtime values
        set_time_limit(0);
        ignore_user_abort(true);
        error_reporting(self::$err_lv);
        date_default_timezone_set(self::$sys['timezone']);

        //Detect running mode
        self::$is_CLI = 'cli' === PHP_SAPI;

        //Detect TLS protocol
        self::$is_TLS = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);
    }

    /**
     * Check CORS permissions
     */
    private static function check_cors(): void
    {
        //Check settings and ENV
        if (empty(self::$cors)
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (self::$is_TLS ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
            return;
        }

        //Exit on access NOT permitted
        is_null($allow_headers = self::$cors[$_SERVER['HTTP_ORIGIN']] ?? self::$cors['*'] ?? null) && exit;

        //Response access allowed headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        //Exit on OPTION request
        'OPTIONS' === $_SERVER['REQUEST_METHOD'] && exit;

        unset($allow_headers);
    }
}