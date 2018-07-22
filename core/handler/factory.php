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
    /**
     * Get new cloned-instance
     *
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    public static function new(string $class, array $param = []): object
    {
        static $list = [];

        $class = parent::build_name($class);

        if (!isset($list[$key = self::hash_key($class, $param)])) {
            $list[$key] = self::create($class, $param);
        }

        unset($class, $param);
        return clone $list[$key];
    }

    /**
     * Get origin single-instance
     *
     * @param string $class
     * @param array  $param
     *
     * @return object
     */
    public static function use(string $class, array $param = []): object
    {
        static $list = [];

        $class = parent::build_name($class);

        if (!isset($list[$key = self::hash_key($class, $param)])) {
            $list[$key] = self::create($class, $param);
        }

        unset($class, $param);
        return $list[$key];
    }

    /**
     * Get HASH key
     *
     * @param string $class
     * @param array  $param
     *
     * @return string
     */
    private static function hash_key(string $class, array $param): string
    {
        $key = hash('md5', $class . json_encode($param));

        unset($class, $param);
        return $key;
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