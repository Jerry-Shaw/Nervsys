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

use core\pool\order;
use core\pool\config;
use core\pool\unit;

class operator
{
    //Order list
    private static $order = [];

    /**
     * Call INIT functions
     */
    public static function run_init(): void
    {
        if (empty(config::$INIT)) {
            return;
        }

        foreach (config::$INIT as $key => $item) {
            $class = self::class_name($key);
            $method = is_string($item) ? [$item] : $item;

            foreach ($method as $function) {
                forward_static_call([$class, $function]);
            }
        }

        unset($key, $item, $class, $method, $function);
    }


    public static function run_cgi(): void
    {
        $position = 0;
        foreach (order::$cmd_cgi as $cmd) {
            //Move position
            if (false !== strpos($cmd, '/') && isset(self::$order[$position])) {
                ++$position;
            }

            self::$order[$position][] = $cmd;
        }

        //Run cmd
        foreach (self::$order as $method) {
            //Get class name
            $name = array_shift($method);
            $class = self::class_name($name);

            //Check class
            if (!class_exists($class)) {
                logger::log('warning', $class . ': Class NOT found!');
                continue;
            }

            //Check TrustZone
            if (!isset($class::$tz) || !is_array($class::$tz)) {
                logger::log('notice', $class . ': TrustZone NOT Open!');
                continue;
            }

            //Call "init" method
            if (method_exists($class, 'init')) {
                try {
                    self::call_method($name, $class, 'init');
                } catch (\Throwable $throwable) {
                    logger::log('debug', $class . ': ' . $throwable->getMessage());
                    unset($throwable);
                }
            }

            //Check API TrustZone permission
            if (empty($class::$tz)) {
                continue;
            }




            //Get API TrustZone list & method list
            $tz_list = array_keys($class::$tz);
            $func_list = get_class_methods($class);

            //Get request list from API TrustZone list
            $method_list = !empty(self::$method) ? array_intersect(self::$method, $tz_list, $func_list) : array_intersect($tz_list, $func_list);

            //Remove "init" method from request list when exists
            if (in_array('init', $method_list, true)) {
                unset($method_list[array_search('init', $method_list, true)]);
            }

            //Process method list
            foreach ($method_list as $method) {
                try {
                    //Check signal
                    //if (0 !== unit::$signal) throw new \Exception(parent::get_signal());

                    //Compare data structure with method TrustZone
                    $inter = array_intersect(array_keys(unit::$data), $class::$tz[$method]);
                    $diff = array_diff($class::$tz[$method], $inter);

                    //Report missing TrustZone data
                    if (!empty($diff)) {
                        throw new \Exception('TrustZone missing [' . (implode(', ', $diff)) . ']!');
                    }

                    //Call method
                    self::call_method($name, $class, $method);
                } catch (\Throwable $throwable) {
                    logger::log('debug', $class . ': ' . $throwable->getMessage());
                    unset($throwable);
                }
            }
            //var_dump($class, $method);


        }


    }


    public static function run_cli(): void
    {


    }


    /**
     * Get root class name
     *
     * @param string $library
     *
     * @return string
     */
    private static function class_name(string $library): string
    {
        return '\\' . ltrim(strtr($library, '/', '\\'), '\\');
    }

    /**
     * Build mapped data
     *
     * @param $reflect
     *
     * @return array
     * @throws \Exception
     */
    private static function map_data($reflect): array
    {
        //Get method params
        $params = $reflect->getParameters();
        if (empty($params)) {
            return [];
        }

        //Process data
        $data = $diff = [];
        foreach ($params as $param) {
            //Get param name
            $name = $param->getName();

            //Check param data
            if (isset(unit::$data[$name])) {
                switch ($param->getType()) {
                    case 'int':
                        $data[$name] = (int)unit::$data[$name];
                        break;
                    case 'bool':
                        $data[$name] = (bool)unit::$data[$name];
                        break;
                    case 'float':
                        $data[$name] = (float)unit::$data[$name];
                        break;
                    case 'array':
                        $data[$name] = (array)unit::$data[$name];
                        break;
                    case 'string':
                        $data[$name] = (string)unit::$data[$name];
                        break;
                    case 'object':
                        $data[$name] = (object)unit::$data[$name];
                        break;
                    default:
                        $data[$name] = unit::$data[$name];
                        break;
                }
            } else {
                $param->isDefaultValueAvailable() ? $data[$name] = $param->getDefaultValue() : $diff[] = $name;
            }
        }

        //Report missing argument data
        if (!empty($diff)) {
            throw new \Exception('Argument missing [' . (implode(', ', $diff)) . ']!');
        }

        unset($reflect, $params, $diff, $param, $name);
        return $data;
    }

    /**
     * Build mapped key
     *
     * @param string $class
     * @param string $method
     *
     * @return string
     */
    private static function map_key(string $class, string $method = ''): string
    {
        $key = '' !== $method
            ? (order::$param_cgi[$class . '-' . $method] ?? (order::$param_cgi[$class] ?? $class) . '/' . $method)
            : (order::$param_cgi[$class] ?? $class);

        unset($class, $method);
        return $key;
    }


    /**
     * Method Caller
     *
     * @param string $name
     * @param string $class
     * @param string $method
     *
     * @throws \Exception
     * @throws \ReflectionException
     */
    private static function call_method(string $name, string $class, string $method): void
    {
        //Get method reflection object
        $reflect = new \ReflectionMethod($class, $method);

        //Check visibility
        if (!$reflect->isPublic()) {
            return;
        }

        //Mapping data
        $data = self::map_data($reflect);

        //Create object
        if (!$reflect->isStatic()) {
            $class = unit::$object[$name] ?? unit::$object[$name] = new $class;
        }

        //Call method (with params)
        $result = empty($data) ? forward_static_call([$class, $method]) : forward_static_call_array([$class, $method], $data);

        //Save result (Try mapping keys)
        if (isset($result)) {
            unit::$result[self::map_key($name, $method)] = &$result;
        }

        unset($name, $class, $method, $reflect, $data, $result);
    }
}