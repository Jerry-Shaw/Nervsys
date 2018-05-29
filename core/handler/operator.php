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

use core\parser\data;
use core\parser\trustzone;

use core\pool\config;
use core\pool\order;
use core\pool\unit;

class operator
{
    //Order list
    private static $order = [];

    /**
     * Call INIT/LOAD commands
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
                log::log('debug', $order . '-' . $method . ': ' . $throwable->getMessage());
                unset($throwable);
            }

            //Check observer status
            if (observer::stop()) {
                return;
            }
        }

        unset($cmd, $item, $order, $method);
    }

    /**
     * Run CGI process
     */
    public static function run_cgi(): void
    {
        //Build order list
        self::build_order();

        //Process orders
        foreach (self::$order as $method) {
            //Get order class
            $order = array_shift($method);
            $class = self::build_class($order);

            //Call LOAD commands
            $load_name = strstr($order, '/', true);

            if (isset(config::$LOAD[$load_name])) {
                self::init_load(is_string(config::$LOAD[$load_name]) ? [config::$LOAD[$load_name]] : config::$LOAD[$load_name]);

                //Check observer status
                if (observer::stop()) {
                    return;
                }
            }

            //Check class
            if (!class_exists($class)) {
                log::log('warning', $class . ': Class NOT found!');
                continue;
            }

            //Check TrustZone
            if (!isset($class::$tz) || !is_array($class::$tz)) {
                log::log('notice', $class . ': TrustZone NOT Open!');
                continue;
            }

            //Call "init" method
            if (method_exists($class, 'init')) {
                self::build_caller($order, $class, 'init');

                //Check observer status
                if (observer::stop()) {
                    return;
                }
            }

            //Check TrustZone permission
            if (empty($class::$tz)) {
                continue;
            }

            //Get TrustZone list & function list & target list
            $tz_list = array_keys($class::$tz);
            $func_list = get_class_methods($class);

            $target_list = !empty($method) ? array_intersect($method, $tz_list, $func_list) : array_intersect($tz_list, $func_list);

            unset($tz_list, $func_list, $method);

            //Handle target list
            foreach ($target_list as $target) {
                //Get TrustZone data
                $tz_data = trustzone::prep($class::$tz[$target]);

                //Run pre functions
                if (!empty($tz_data['pre'])) {
                    foreach ($tz_data['pre'] as $tz_item) {
                        self::build_caller($tz_item['order'], self::build_class($tz_item['order']), $tz_item['method']);

                        //Check observer status
                        if (observer::stop()) {
                            return;
                        }
                    }
                }

                //Check TrustZone
                if (trustzone::fail($order, $target, array_keys(unit::$data), $tz_data['param'])) {
                    continue;
                }

                //Build method caller
                self::build_caller($order, $class, $target);

                //Check observer status
                if (observer::stop()) {
                    return;
                }

                //Run post functions
                if (!empty($tz_data['post'])) {
                    foreach ($tz_data['post'] as $tz_item) {
                        self::build_caller($tz_item['order'], self::build_class($tz_item['order']), $tz_item['method']);

                        //Check observer status
                        if (observer::stop()) {
                            return;
                        }
                    }
                }
            }
        }

        unset($method, $order, $class, $load_name, $target_list, $target, $tz_data, $tz_item);
    }

    /**
     * Run CLI process
     */
    public static function run_cli(): void
    {
        //Process orders
        foreach (order::$cmd_cli as $key => $cmd) {
            //Prepare command
            $command = '"' . $cmd . '"';

            //Append arguments
            if (!empty(order::$param_cli['argv'])) {
                $command .= ' ' . implode(' ', order::$param_cli['argv']);
            }

            //Create process
            $process = proc_open(platform::cmd_proc($command), [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);

            if (!is_resource($process)) {
                log::log('debug', $key . ' -> ' . $cmd . ': Access denied or command ERROR!');
                continue;
            }

            //Send data via pipe
            if ('' !== order::$param_cli['pipe']) {
                fwrite($pipes[0], order::$param_cli['pipe'] . PHP_EOL);
            }

            //Collect result
            if (order::$param_cli['ret']) {
                $data = self::read_pipe([$process, $pipes[1]]);

                if ('' !== $data) {
                    unit::$result[$key] = &$data;
                }

                unset($data);
            }

            //Close pipes (ignore process)
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
        }

        unset($key, $cmd, $command, $process, $pipes, $pipe);
    }

    /**
     * Get root class name
     *
     * @param string $library
     *
     * @return string
     */
    private static function build_class(string $library): string
    {
        return '\\' . ltrim(strtr($library, '/', '\\'), '\\');
    }

    /**
     * Build CGI order list
     */
    private static function build_order(): void
    {
        $key = 0;
        foreach (order::$cmd_cgi as $item) {
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
     */
    private static function build_caller(string $order, string $class, string $method): void
    {
        try {
            //Reflection method
            $reflect = new \ReflectionMethod($class, $method);

            //Check visibility
            if (!$reflect->isPublic()) {
                throw new \Exception('NOT for public!');
            }

            //Build arguments
            $params = data::build_argv($reflect, unit::$data);

            //Create object
            if (!$reflect->isStatic()) {
                $class = unit::$object[$order] ?? unit::$object[$order] = new $class;
            }

            //Call method (with params)
            $result = empty($params) ? forward_static_call([$class, $method]) : forward_static_call_array([$class, $method], $params);

            //Save result (Try mapping keys)
            if (isset($result)) {
                unit::$result[self::build_key($order, $method)] = &$result;
            }
        } catch (\Throwable $throwable) {
            log::log('debug', $order . '-' . $method . ': ' . $throwable->getMessage());
            unset($throwable);
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
        $key = order::$param_cgi[$class . '-' . $method] ?? (order::$param_cgi[$class] ?? $class) . '/' . $method;

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
        $timer = 0;
        $result = '';

        //Keep watching & reading
        while (0 === order::$param_cli['time'] || $timer <= order::$param_cli['time']) {
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