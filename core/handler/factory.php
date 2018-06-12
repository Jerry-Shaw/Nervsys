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

class factory
{
    //Object list
    private static $obj = [];

    //Original list
    private static $orig = [];

    /**
     * Get cloned-instance
     *
     * @param string $lib
     *
     * @return object
     */
    public static function new(string $lib): object
    {
        if (!isset(self::$orig[$lib])) {
            self::$orig[$lib] = new $lib;
        }

        return clone self::$orig[$lib];
    }

    /**
     * Get single-instance
     *
     * @param string $lib
     *
     * @return object
     */
    public static function get(string $lib): object
    {
        if (!isset(self::$obj[$lib])) {
            if (!isset(self::$orig[$lib])) {
                self::$orig[$lib] = new $lib;
            }

            self::$obj[$lib] = clone self::$orig[$lib];
        }

        return self::$obj[$lib];
    }
}