<?php

/**
 * NS System Reflection controller
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

namespace core\lib;

use core\ns;

/**
 * Class reflect
 *
 * @package core\lib
 */
final class reflect extends ns
{
    //Reflection pool
    private static $pool = [];

    /**
     * Get class reflection
     *
     * @param string $class
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    public static function get_class(string $class): \ReflectionClass
    {
        if (!isset(self::$pool[$class])) {
            self::$pool[$class] = new \ReflectionClass($class);
        }

        return self::$pool[$class];
    }

    /**
     * Get method reflection
     *
     * @param string $class
     * @param string $method
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    public static function get_method(string $class, string $method): \ReflectionMethod
    {
        if (!isset(self::$pool[$key = $class . ':method:' . $method])) {
            self::$pool[$key] = self::get_class($class)->getMethod($method);
        }

        unset($class, $method);
        return self::$pool[$key];
    }

    /**
     * Get property reflection
     *
     * @param string $class
     * @param string $property
     *
     * @return \ReflectionProperty
     * @throws \ReflectionException
     */
    public static function get_property(string $class, string $property): \ReflectionProperty
    {
        if (!isset(self::$pool[$key = $class . ':property:' . $property])) {
            self::$pool[$key] = self::get_class($class)->getProperty($property);
        }

        unset($class, $method);
        return self::$pool[$key];
    }

    /**
     * Get all params from method
     *
     * @param string $class
     * @param string $method
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function get_params(string $class, string $method): array
    {
        if (!isset(self::$pool[$key = $class . ':params:' . $method])) {
            self::$pool[$key] = self::get_method($class, $method)->getParameters();
        }

        unset($class, $method);
        return self::$pool[$key];
    }


    /**
     * Get method list from class
     *
     * @param string $class
     * @param int    $filter
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function get_method_list(string $class, int $filter): array
    {
        return self::get_class($class)->getMethods($filter);
    }

    /**
     * Get property list from class
     *
     * @param string $class
     * @param int    $filter
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function get_property_list(string $class, int $filter): array
    {
        return self::get_class($class)->getProperties($filter);
    }

    /**
     * Get information of a param
     *
     * @param \ReflectionParameter $parameter
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function get_param_info(\ReflectionParameter $parameter): array
    {
        $info = [];

        //Get name
        $info['name'] = $parameter->getName();

        //Get default value
        $info['has_default'] = $parameter->isDefaultValueAvailable();
        if ($info['has_default']) {
            $info['default'] = $parameter->getDefaultValue();
        }

        //Get param type
        $info['has_type'] = $parameter->hasType();
        if ($info['has_type']) {
            $info['type'] = $parameter->getType()->getName();

            //Get class name
            $param_class       = $parameter->getClass();
            $info['has_class'] = is_object($param_class);

            if ($info['has_class']) {
                $info['class'] = $param_class->getName();
            }

            unset($param_class);
        } else {
            $info['has_class'] = false;
        }

        unset($parameter);
        return $info;
    }
}