<?php

/**
 * Factory Handler
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

use core\system;

class factory extends system
{
    //Factory storage
    private static $storage = [];

    /**
     * Get new cloned object from called class or alias name
     * Defined by both class name and arguments
     *
     * @return $this
     */
    public static function new(): object
    {
        return clone self::stock(__FUNCTION__, get_called_class(), func_get_args());
    }

    /**
     * Get original object from called class or alias name
     * Defined by only class name created last time
     *
     * @return $this
     */
    public static function use(): object
    {
        return self::stock(__FUNCTION__, get_called_class(), func_get_args());
    }

    /**
     * Obtain original object from another class
     * Defined by both class name and arguments
     *
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    public static function obtain(string $class, array $param = []): object
    {
        return self::stock(__FUNCTION__, parent::build_name($class), $param);
    }

    /**
     * Free from factory storage by name/alias
     *
     * @param string $name
     */
    public static function free(string $name = ''): void
    {
        $class = get_called_class();
        $items = '' !== $name ? [$name, $class . '_AS_' . $name] : [$class];

        //Remove from original storage
        foreach ($items as $val) {
            if (isset(self::$storage[$key = hash('md5', $val)])) {
                self::$storage[$key] = null;
                unset(self::$storage[$key]);
            }
        }

        unset($name, $class, $items, $val, $key);
    }

    /**
     * Copy object as alias and remove source
     * Alias is merged with called class and alias name
     * Different names should be using conditionally to avoid conflicts
     *
     * @param string $alias
     *
     * @return $this
     */
    public function as(string $alias): object
    {
        self::free($name = get_class($this));
        self::$storage[$key = hash('md5', $name . '_AS_' . $alias)] = $this;

        unset($alias, $name);
        return self::$storage[$key];
    }

    /**
     * Config class settings
     *
     * @param array $setting
     *
     * @return $this
     */
    public function config(array $setting): object
    {
        foreach ($setting as $key => $val) {
            if (isset($this->$key)) {
                $this->$key = $val;
            }
        }

        unset($setting, $key, $val);
        return $this;
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
    private static function stock(string $type, string $class, array $param): object
    {
        //Check alias calling
        if (1 === count($param)
            && is_string($param[0])
            && isset(self::$storage[$key = hash('md5', $class . '_AS_' . $param[0])])) {
            unset($type, $class, $param);
            return self::$storage[$key];
        }

        //Generate object key
        $key = 'use' === $type
            ? hash('md5', $type . ':' . $class)
            : hash('md5', $type . ':' . $class . ':' . json_encode($param));

        //Create object and save to storage
        if (!isset(self::$storage[$key])) {
            self::$storage[$key] = !empty($param) ? new $class(...$param) : new $class();
        }

        unset($type, $class, $param);
        return self::$storage[$key];
    }
}