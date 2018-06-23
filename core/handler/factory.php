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
     * @param string $library
     *
     * @return object
     */
    public static function new(string $library): object
    {
        if (!isset(self::$orig[$library])) {
            self::$orig[$library] = new $library;
        }

        return clone self::$orig[$library];
    }

    /**
     * Get single-instance
     *
     * @param string $library
     *
     * @return object
     */
    public static function get(string $library): object
    {
        if (!isset(self::$obj[$library])) {
            if (!isset(self::$orig[$library])) {
                self::$orig[$library] = new $library;
            }

            self::$obj[$library] = clone self::$orig[$library];
        }

        return self::$obj[$library];
    }
}