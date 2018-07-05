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

use core\parser\data;
use core\parser\trustzone;

use core\pool\command;
use core\pool\process;
use core\pool\setting;

class operator extends process
{
    /**
     * Call INIT/LOAD
     *
     * @param array $cmd
     */
    public static function init_load(array $cmd): void
    {
        foreach ($cmd as $item) {
            list($order, $method) = explode('-', $item, 2);

            try {
                forward_static_call([self::get_class($order), $method]);
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable);
                unset($throwable);
            }
        }

        unset($cmd, $item, $order, $method);
    }

    /**
     * Run CLI process
     */
    public static function run_cli(): void
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
     * Run CGI process
     */
    public static function run_cgi(): void
    {
        //Build order list
        $order = self::build_order();

        //Process orders
        foreach ($order as $method) {
            //Get name & class
            $class = self::get_class($name = array_shift($method));

            //Check & load class
            if (!class_exists($class, false) && !self::load_class($class)) {
                continue;
            }

            //Check TrustZone
            if (!isset($class::$tz) || !is_array($class::$tz)) {
                continue;
            }

            //Call LOAD commands
            if (isset(setting::$load[$module = strstr($name, '/', true)])) {
                self::init_load(is_string(setting::$load[$module]) ? [setting::$load[$module]] : setting::$load[$module]);
            }

            //Call "init" method
            if (method_exists($class, 'init')) {
                self::build_caller($name, $class, 'init');
            }

            //Check TrustZone permission
            if (empty($class::$tz)) {
                continue;
            }

            //Get TrustZone list & function list
            $tz_list   = array_keys($class::$tz);
            $func_list = get_class_methods($class);

            //Get target list
            $target_list = !empty($method) ? array_intersect($method, $tz_list, $func_list) : array_intersect($tz_list, $func_list);

            unset($module, $tz_list, $func_list, $method);

            //Handle target list
            foreach ($target_list as $target) {
                try {
                    //Get TrustZone data
                    $tz_data = trustzone::load($class::$tz[$target]);

                    //Run pre functions
                    if (!empty($tz_data['pre'])) {
                        foreach ($tz_data['pre'] as $tz_item) {
                            self::build_caller($tz_item['order'], self::get_class($tz_item['order']), $tz_item['method']);
                        }
                    }

                    //Check TrustZone
                    trustzone::verify(array_keys(self::$data), $tz_data['param']);

                    //Build method caller
                    self::build_caller($name, $class, $target);

                    //Run post functions
                    if (!empty($tz_data['post'])) {
                        foreach ($tz_data['post'] as $tz_item) {
                            self::build_caller($tz_item['order'], self::get_class($tz_item['order']), $tz_item['method']);
                        }
                    }
                } catch (\Throwable $throwable) {
                    error::exception_handler($throwable);
                    unset($throwable);
                }
            }
        }

        unset($order, $method, $name, $class, $target_list, $target, $tz_data, $tz_item);
    }

    /**
     * Get class name
     *
     * @param string $class
     *
     * @return string
     */
    private static function get_class(string $class): string
    {
        return '\\' . trim(strtr($class, '/', '\\'), '\\');
    }

    /**
     * Load class file
     *
     * @param string $class
     *
     * @return bool
     */
    private static function load_class(string $class): bool
    {
        $load = false;
        $file = trim(strtr($class, '\\', DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR) . '.php';
        $list = false !== strpos($file, DIRECTORY_SEPARATOR) ? [ROOT] : setting::$path;

        foreach ($list as $path) {
            if (is_string($path = realpath($path . $file))) {
                //Load class
                require $path;
                //Check load status
                $load = class_exists($class, false);

                break;
            }
        }

        unset($class, $file, $list, $path);
        return $load;
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
     * Build CGI order list
     */
    private static function build_order(): array
    {
        $key  = 0;
        $list = [];

        foreach (command::$cmd_cgi as $item) {
            if (false !== strpos($item, '/') && isset($list[$key])) {
                ++$key;
            }

            $list[$key][] = $item;
        }

        unset($key, $item);
        return $list;
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

        //Get factory object
        if (!$reflect->isStatic()) {
            $class = factory::use($class);
        }

        //Build arguments
        $params = data::build_argv($reflect, self::$data);

        //Call method (with params)
        $result = empty($params) ? forward_static_call([$class, $method]) : forward_static_call_array([$class, $method], $params);

        //Save result (Try mapping keys)
        if (isset($result)) {
            self::$result[self::build_key($order, $method)] = &$result;
        }

        unset($order, $class, $method, $reflect, $params, $result);
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