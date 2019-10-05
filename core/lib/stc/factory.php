<?php

/**
 * NS System Factory controller
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

namespace core\lib\stc;

/**
 * Class factory
 *
 * @package core\lib
 */
final class factory
{
    //Instance pool
    private static $pool = [];

    /**
     * Build an instance
     *
     * @param string $class
     * @param array  $params
     *
     * @return object
     */
    public static function build(string $class, array $params = []): object
    {
        if (!isset(self::$pool[$key = hash('md5', $class . ':' . json_encode($params))])) {
            self::$pool[$key] = !empty($params) ? new $class(...$params) : new $class();
        }

        unset($class, $params);
        return self::$pool[$key];
    }
}