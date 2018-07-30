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
    //Cloned objects
    private static $cloned_list = [];

    //Origin objects
    private static $origin_list = [];

    /**
     * Get cloned instance
     *
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    public static function new(string $class, array $param = []): object
    {
        $class = parent::build_name($class);

        //Create and store to cloned list
        if (!isset(self::$cloned_list[$key = hash('md5', $class . json_encode($param))])) {
            self::$cloned_list[$key] = self::create($class, $param);
        }

        unset($class, $param);
        return clone self::$cloned_list[$key];
    }

    /**
     * Get origin instance
     *
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    public static function use(string $class, array $param = []): object
    {
        $class = parent::build_name($class);

        //Create and store to origin list
        if (!isset(self::$origin_list[$key = hash('md5', $class . json_encode($param))])) {
            self::$origin_list[$key] = self::create($class, $param);
        }

        unset($class, $param);
        return self::$origin_list[$key];
    }

    /**
     * Free factory storage
     *
     * @param object $object
     */
    public static function free(object $object): void
    {
        //Drop from cloned list
        if (!empty($keys = array_keys(self::$cloned_list, $object, true))) {
            foreach ($keys as $key) {
                self::$cloned_list[$key] = null;
                unset(self::$cloned_list[$key]);
            }
        }

        //Drop from origin list
        if (!empty($keys = array_keys(self::$origin_list, $object, true))) {
            foreach ($keys as $key) {
                self::$origin_list[$key] = null;
                unset(self::$origin_list[$key]);
            }
        }

        unset($object, $keys, $key);
    }

    /**
     * Create object
     *
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    private static function create(string $class, array $param): object
    {
        $object = !empty($param) ? new $class(...$param) : new $class();

        unset($class, $param);
        return $object;
    }
}