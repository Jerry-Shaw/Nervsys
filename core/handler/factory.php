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
     * Free original storage before reuse if necessary
     *
     * @return $this
     */
    protected static function use(): object
    {
        $name  = get_called_class();
        $param = func_get_args();

        //Check alias calling
        if (1 === func_num_args()
            && is_string($param[0])
            && isset(self::$origin[$key = hash('md5', $name . '_AS_' . $param[0])])) {
            return self::$origin[$key];
        }

        //Create and store to original list
        if (!isset(self::$origin[$key = hash('md5', $name)])) {
            self::$origin[$key] = !empty($param) ? new static(...$param) : new static();
        }

        unset($name, $param);
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
     * Free from original storage by name/alias
     *
     * @param string $name
     */
    protected static function free(string $name = ''): void
    {
        $class = get_called_class();
        $items = '' !== $name ? [$name, $class . '_AS_' . $name] : [$class];

        foreach ($items as $val) {
            if (isset(self::$origin[$key = hash('md5', $val)])) {
                self::$origin[$key] = null;
                unset(self::$origin[$key]);
            }
        }

        unset($name, $class, $items, $val, $key);
    }

    /**
     * Copy object as alias and remove source
     * Alias is merged with called class and alias name
     *
     * @param string $alias
     *
     * @return $this
     */
    protected function as(string $alias): object
    {
        self::free($name = get_class($this));
        self::$origin[$key = hash('md5', $name . '_AS_' . $alias)] = $this;

        unset($alias, $name);
        return self::$origin[$key];
    }
}