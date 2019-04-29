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

use core\handler\error;
use core\handler\operator;
use core\handler\platform;

use core\parser\cmd;
use core\parser\input;
use core\parser\output;

use core\pool\command;

class system extends command
{
    /**
     * INIT state (S1)
     * Initialize system
     *
     * Steps:
     * 1. Load "system.ini" and parse settings.
     * 2. Set runtime values, detect CGI/CLI and TLS.
     * 3. Check Cross-Origin Resource Sharing (CORS) permissions.
     * 4. Execute all configured settings in "init" section of "system.ini".
     */
    const STATE_INIT = 1;

    /**
     * READ state (S2)
     * Read & parse input data
     *
     * Steps:
     * 1. Read and parse input data (REQUEST + JSON + XML).
     * 2. Save parsed data to process pool in non-overwrite mode.
     */
    const STATE_READ = 2;

    /**
     * EXEC state (S3)
     * Execute input commands
     *
     * Steps:
     * 1. Prepare commands. Skip when already set.
     * 2. Execute script functions order by commands via CGI mode.
     * 3. Execute script functions and external commands via CLI mode (available under CLI).
     * 4. Gathering results on calling every function or external command. Save to process result pool.
     */
    const STATE_EXEC = 3;

    /**
     * FLUSH state (S4, default)
     * Output results in preset format
     *
     * Steps:
     * 1. Output MIME-Type header.
     * 2. Output formatted result content.
     */
    const STATE_FLUSH = 4;

    /**
     * Boot system
     *
     * @param int $state
     */
    public static function boot(int $state = self::STATE_FLUSH): void
    {
        /**
         * INIT state (S1)
         */

        self::load_cfg();
        self::config_env();
        self::check_cors();
        self::initial_sys();

        //S1 state abort
        if ($state === self::STATE_INIT) {
            return;
        }

        /**
         * READ state (S2)
         */

        input::read();

        //S2 state abort
        if ($state === self::STATE_READ) {
            return;
        }

        /**
         * EXEC state (S3)
         */

        '' !== self::$cmd && cmd::prepare();

        operator::exec_cgi();
        operator::exec_cli();

        //S3 state abort
        if ($state === self::STATE_EXEC) {
            return;
        }

        /**
         * FLUSH state (S4, default)
         */

        output::flush();
        unset($state);
    }

    /**
     * Stop system
     */
    public static function stop(): void
    {
        //Flush result & exit
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
        //Get remote IP
        $remote_ip = $_SERVER['REMOTE_ADDR'];

        //Check forwarded IP
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            //Get forwarded IP list
            $forward_ip = array_map(
                static function (string $ip): string
                {
                    return trim($ip);
                }, false !== strpos($_SERVER['HTTP_X_FORWARDED_FOR'], ',')
                ? explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])
                : [$_SERVER['HTTP_X_FORWARDED_FOR']]
            );

            //Check remote IP with last proxy IP
            if ($remote_ip !== array_pop($forward_ip) || empty($forward_ip)) {
                //High anonymity proxy detected
                return '';
            }

            //Copy remote IP
            $remote_ip = array_shift($forward_ip);
            unset($forward_ip);
        }

        //Validate IP address (IPV4 & IPV6)
        return (string)filter_var($remote_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);
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
            self::$cli['PHP'] = platform::php_path();
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
        $conf = parse_ini_file(parent::CFG_FILE, true);

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

        //Validate app_path
        self::$sys['app_path'] = isset(self::$sys['app_path']) && '' !== self::$sys['app_path']
            ? trim(self::$sys['app_path'], " /\\\t\n\r\0\x0B")
            : '';

        //Refill app_path
        if ('' !== self::$sys['app_path']) {
            self::$sys['app_path'] .= '/';
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

    /**
     * Initialize system
     */
    private static function initial_sys(): void
    {
        if (empty(self::$init)) {
            return;
        }

        $list = [];
        foreach (self::$init as $item) {
            is_array($item) ? array_push($list, ...$item) : $list[] = $item;
        }

        try {
            //Execute "init" settings
            operator::exec_dep($list);
        } catch (\Throwable $throwable) {
            //Redirect exception code to error
            error::exception_handler(new \Exception($throwable->getMessage(), E_USER_ERROR));
            unset($throwable);
        }

        unset($list, $item);
    }
}