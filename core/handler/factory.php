<?php

/**
 * Factory Handler
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

namespace core\handler;

use core\system;

class factory extends system
{
    //Factory storage
    private static $storage = [];

    //Reflection pool
    private static $reflection = [];

    /**
     * Get original object from called class with alias
     * Defined by class created from "as"
     *
     * @param string $alias
     *
     * @return $this
     */
    public static function use(string $alias): object
    {
        if (!isset(self::$storage[$key = self::get_alias(get_called_class(), $alias)])) {
            error::exception_handler(new \Exception('Object "' . get_called_class() . ':' . $alias . '" NOT found!', E_USER_ERROR));
        }

        unset($alias);
        return self::$storage[$key];
    }

    /**
     * Get new cloned object from called class
     * Defined by class and arguments
     *
     * @return $this
     */
    public static function new(): object
    {
        return clone self::get_stock(__FUNCTION__, get_called_class(), func_get_args());
    }

    /**
     * Obtain original object from another class
     * Defined by class and arguments
     *
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    public static function obtain(string $class, array $param = []): object
    {
        return self::get_stock(__FUNCTION__, $class, $param);
    }

    /**
     * Get class reflection
     *
     * @param string $class
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    public static function reflect_class(string $class): \ReflectionClass
    {
        if (!isset(self::$reflection[$class])) {
            self::$reflection[$class] = new \ReflectionClass($class);
        }

        return self::$reflection[$class];
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
    public static function reflect_method(string $class, string $method): \ReflectionMethod
    {
        if (!isset(self::$reflection[$key = $class . '::' . $method])) {
            self::$reflection[$key] = new \ReflectionMethod($class, $method);
        }

        unset($class, $method);
        return self::$reflection[$key];
    }

    /**
     * Get class name based on app_path
     *
     * @param string $class
     *
     * @return string
     */
    public static function get_app_class(string $class): string
    {
        //Get first char
        $char = substr($class, 0, 1);

        //Refill app_path
        if (!in_array($char, ['/', '\\'], true)) {
            $class = parent::$sys['app_path'] . $class;
        }

        //Build class
        $class = '\\' . trim(strtr($class, '/', '\\'), '\\');

        unset($char);
        return $class;
    }

    /**
     * Get relative cmd based on app_path
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function get_app_cmd(string $cmd): string
    {
        //Remove defined "app_path"
        if ('' !== self::$sys['app_path'] && 0 === strpos($cmd, self::$sys['app_path'])) {
            $cmd = substr($cmd, strlen(self::$sys['app_path']));
        }

        return $cmd;
    }

    /**
     * Build dependency list
     *
     * @param array $dep_list
     */
    public static function build_dep(array &$dep_list): void
    {
        foreach ($dep_list as $key => $dep) {
            if (false === strpos($dep, '-')) {
                $order  = $dep;
                $method = '__construct';
            } else {
                list($order, $method) = explode('-', $dep, 2);
            }

            $dep_list[$key] = [$order, self::get_app_class($order), $method];
        }

        unset($key, $dep, $order, $method);
    }

    /**
     * Configure class properties
     *
     * @param array $setting
     *
     * @return $this
     */
    public function config(array $setting): object
    {
        $setting = array_intersect_key($setting, get_object_vars($this));

        foreach ($setting as $key => $val) {
            $this->$key = $val;
        }

        unset($setting, $key, $val);
        return $this;
    }

    /**
     * Copy object as alias (overwrite)
     *
     * @param string $alias
     *
     * @return $this
     */
    public function as(string $alias): object
    {
        if (isset(self::$storage[$key = self::get_alias(get_class($this), $alias)])) {
            self::$storage[$key] = null;
            unset(self::$storage[$key]);
        }

        self::$storage[$key] = &$this;

        unset($alias, $key);
        return $this;
    }

    /**
     * Free from alias storage
     *
     * @param string $alias
     */
    public function free(string $alias = ''): void
    {
        $list = '' === $alias ? array_keys(self::$storage, $this, true) : [self::get_alias(get_class($this), $alias)];

        foreach ($list as $key) {
            self::$storage[$key] = null;
            unset(self::$storage[$key]);
        }

        unset($alias, $list, $key);
    }

    /**
     * Stock controller
     *
     * @param string $type
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    private static function get_stock(string $type, string $class, array $param): object
    {
        if (!isset(self::$storage[$key = hash('md5', $type . ':' . $class . ':' . json_encode($param))])) {
            self::$storage[$key] = !empty($param) ? new $class(...$param) : new $class();
        }

        unset($type, $class, $param);
        return self::$storage[$key];
    }

    /**
     * Get alias key for a class
     *
     * @param string $class
     * @param string $alias
     *
     * @return string
     */
    private static function get_alias(string $class, string $alias): string
    {
        $key = hash('md5', $class . ':' . $alias);

        unset($class, $alias);
        return $key;
    }
}