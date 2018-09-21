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
    private static $cloned = [];

    //Original objects
    private static $origin = [];

    /**
     * Get new cloned object from called class
     * Defined by both class name and arguments
     *
     * @return $this
     */
    protected static function new(): object
    {
        $param = func_get_args();

        //Create and store to cloned list
        if (!isset(self::$cloned[$key = hash('md5', get_called_class() . json_encode($param))])) {
            self::$cloned[$key] = !empty($param) ? new static(...$param) : new static();
        }

        unset($param);
        return clone self::$cloned[$key];
    }

    /**
     * Get original object from called class
     * Defined by only class name created last time
     * Free factory storage before reuse if necessary
     *
     * @return $this
     */
    protected static function use(): object
    {
        $param = func_get_args();

        //Create and store to original list
        if (!isset(self::$origin[$key = hash('md5', get_called_class())])) {
            self::$origin[$key] = !empty($param) ? new static(...$param) : new static();
        }

        unset($param);
        return self::$origin[$key];
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
    protected static function obtain(string $class, array $param = []): object
    {
        $class = parent::build_name($class);

        //Create and store to original list
        if (!isset(self::$origin[$key = hash('md5', $class . json_encode($param))])) {
            self::$origin[$key] = !empty($param) ? new $class(...$param) : new $class();
        }

        unset($class, $param);
        return self::$origin[$key];
    }

    /**
     * Free factory storage
     *
     * @param object $object
     */
    protected static function free(object $object = null): void
    {
        if (is_null($object)) {
            //Drop self from original list
            unset(self::$origin[get_called_class()]);
        } else {
            //Drop object from original list
            if (!empty($keys = array_keys(self::$origin, $object, true))) {
                foreach ($keys as $key) {
                    self::$origin[$key] = null;
                    unset(self::$origin[$key]);
                }
            }

            //Drop object from cloned list
            if (!empty($keys = array_keys(self::$cloned, $object, true))) {
                foreach ($keys as $key) {
                    self::$cloned[$key] = null;
                    unset(self::$cloned[$key]);
                }
            }
        }

        unset($object, $keys, $key);
    }
}