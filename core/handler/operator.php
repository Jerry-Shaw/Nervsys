<?php

/**
 * Operator Handler
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

namespace core\handler;

use core\helper\log;

use core\parser\cmd;
use core\parser\data;
use core\parser\input;
use core\parser\output;
use core\parser\trustzone;

use core\pool\command;
use core\pool\process;
use core\pool\configure;

class operator extends process
{
    //Order list
    private static $order = [];

    /**
     * Start operator
     */
    public static function start(): void
    {
        //Check CORS
        self::chk_cors();

        //Call INIT
        if (!empty(configure::$init)) {
            self::init_load(configure::$init);
        }

        //Read input
        input::read();

        //Prepare CMD
        cmd::prep();

        //Run CGI process
        self::run_cgi();

        //Run CLI process
        if (!configure::$is_cgi) {
            self::run_cli();
        }
    }

    /**
     * Stop operator
     *
     * @param int    $errno
     * @param string $message
     */
    public static function stop(int $errno = 0, string $message = ''): void
    {
        output::$error['err'] = 0 < $errno ? $errno : 1;
        output::$error['msg'] = '' !== $message ? $message : 'Process terminated!';

        output::json();

        exit;
    }

    /**
     * Call INIT/LOAD
     *
     * @param array $cmd
     */
    public static function init_load(array $cmd): void
    {
        foreach ($cmd as $item) {
            //Get order & method
            list($order, $method) = explode('-', $item, 2);

            try {
                forward_static_call([self::build_class($order), $method]);
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable);
                unset($throwable);
            }
        }

        unset($cmd, $item, $order, $method);
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
        return 'unknown';
    }

    /**
     * Check Cross-origin resource sharing permission
     */
    private static function chk_cors(): void
    {
        if (
            empty(configure::$cors)
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (configure::$is_https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
        ) {
            return;
        }

        if (!isset(configure::$cors[$_SERVER['HTTP_ORIGIN']])) {
            log::info('CORS denied for ' . $_SERVER['HTTP_ORIGIN'] . ' from ' . self::get_ip());
            self::stop(1, 'Access denied!');
        }

        //Response Access-Control-Allow-Origin & Access-Control-Allow-Headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . configure::$cors[$_SERVER['HTTP_ORIGIN']]);

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            exit;
        }
    }

    /**
     * Run CGI process
     */
    private static function run_cgi(): void
    {
        //Build order list
        self::build_order();

        //Process orders
        foreach (self::$order as $method) {
            //Get order class
            $order = array_shift($method);
            $class = self::build_class($order);

            //Call LOAD commands
            if (isset(configure::$load[$load_name = strstr($order, '/', true)])) {
                self::init_load(is_string(configure::$load[$load_name]) ? [configure::$load[$load_name]] : configure::$load[$load_name]);
            }

            //Check class
            if (!class_exists($class)) {
                log::warning($class . ': Class NOT found!');
                continue;
            }

            //Check TrustZone
            if (!isset($class::$tz) || !is_array($class::$tz)) {
                log::notice($class . ': TrustZone NOT Open!');
                continue;
            }

            //Call "init" method
            if (method_exists($class, 'init')) {
                self::build_caller($order, $class, 'init');
            }

            //Check TrustZone permission
            if (empty($class::$tz)) {
                continue;
            }

            //Get TrustZone list & function list
            $tz_list   = array_keys($class::$tz);
            $func_list = get_class_methods($class);

            //Get target list
            $target_list = !empty($method)
                ? array_intersect($method, $tz_list, $func_list)
                : array_intersect($tz_list, $func_list);

            unset($tz_list, $func_list, $method);

            //Handle target list
            foreach ($target_list as $target) {
                try {
                    //Get TrustZone data
                    $tz_data = trustzone::load($class::$tz[$target]);

                    //Run pre functions
                    if (!empty($tz_data['pre'])) {
                        foreach ($tz_data['pre'] as $tz_item) {
                            self::build_caller($tz_item['order'], self::build_class($tz_item['order']), $tz_item['method']);
                        }
                    }

                    //Check TrustZone
                    trustzone::verify(array_keys(self::$data), $tz_data['param']);

                    //Build method caller
                    self::build_caller($order, $class, $target);

                    //Run post functions
                    if (!empty($tz_data['post'])) {
                        foreach ($tz_data['post'] as $tz_item) {
                            self::build_caller($tz_item['order'], self::build_class($tz_item['order']), $tz_item['method']);
                        }
                    }
                } catch (\Throwable $throwable) {
                    error::exception_handler($throwable);
                    unset($throwable);
                }
            }
        }

        unset($method, $order, $class, $load_name, $target_list, $target, $tz_data, $tz_item);
    }

    /**
     * Run CLI process
     */
    private static function run_cli(): void
    {
        //Process orders
        foreach (command::$cmd_cli as $key => $cmd) {
            try {
                //Prepare command
                $command = '"' . $cmd . '"';

                //Append arguments
                if (!empty(command::$param_cli['argv'])) {
                    $command .= ' ' . implode(' ', command::$param_cli['argv']);
                }

                //Create process
                $process = proc_open(platform::cmd_proc($command), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

                if (!is_resource($process)) {
                    throw new \Exception($key . ' => ' . $cmd . ': Access denied or command ERROR!');
                }

                //Send data via pipe
                if ('' !== command::$param_cli['pipe']) {
                    fwrite($pipes[0], command::$param_cli['pipe'] . PHP_EOL);
                }

                //Collect result
                if (command::$param_cli['ret']) {
                    $data = self::read_pipe([$process, $pipes[1]]);

                    if ('' !== $data) {
                        self::$result[$key] = &$data;
                    }

                    unset($data);
                }

                //Close pipes (ignore process)
                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable);
                unset($throwable);
            }
        }

        unset($key, $cmd, $command, $process, $pipes, $pipe);
    }

    /**
     * Get root class name
     *
     * @param string $lib
     *
     * @return string
     */
    private static function build_class(string $lib): string
    {
        return '\\' . ltrim(strtr($lib, '/', '\\'), '\\');
    }

    /**
     * Build CGI order list
     */
    private static function build_order(): void
    {
        $key = 0;
        foreach (command::$cmd_cgi as $item) {
            if (false !== strpos($item, '/') && isset(self::$order[$key])) {
                ++$key;
            }

            self::$order[$key][] = $item;
        }

        unset($key, $item);
    }

    /**
     * Build method caller
     *
     * @param string $order
     * @param string $class
     * @param string $method
     *
     * @throws \ReflectionException
     */
    private static function build_caller(string $order, string $class, string $method): void
    {
        //Reflection method
        $reflect = new \ReflectionMethod($class, $method);

        //Check visibility
        if (!$reflect->isPublic()) {
            throw new \Exception($order . ' => ' . $method . ': NOT for public!');
        }

        //Build arguments
        $params = data::build_argv($reflect, self::$data);

        //Create object
        if (!$reflect->isStatic()) {
            $class = factory::new($class);
        }

        //Call method (with params)
        $result = empty($params) ? forward_static_call([$class, $method]) : forward_static_call_array([$class, $method], $params);

        //Save result (Try mapping keys)
        if (isset($result)) {
            self::$result[self::build_key($order, $method)] = &$result;
        }

        unset($order, $class, $method, $reflect, $params, $result);
    }

    /**
     * Build mapped key
     *
     * @param string $class
     * @param string $method
     *
     * @return string
     */
    private static function build_key(string $class, string $method): string
    {
        $key = command::$param_cgi[$class . '-' . $method] ?? (command::$param_cgi[$class] ?? $class) . '/' . $method;

        unset($class, $method);
        return $key;
    }

    /**
     * Get stream content
     *
     * @param array $process
     *
     * @return string
     */
    private static function read_pipe(array $process): string
    {
        $timer  = 0;
        $result = '';

        //Keep watching & reading
        while (0 === command::$param_cli['time'] || $timer <= command::$param_cli['time']) {
            if (proc_get_status($process[0])['running']) {
                usleep(1000);
                $timer += 1000;
            } else {
                $result = trim(stream_get_contents($process[1]));
                break;
            }
        }

        unset($process, $timer);
        return $result;
    }
}