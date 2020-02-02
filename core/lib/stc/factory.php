<?php

/**
 * NS System Factory handler
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

use core\lib\std\reflect;
use core\lib\std\router;

/**
 * Class factory
 *
 * @package core\lib\stc
 */
final class factory
{
    //Instance pool
    private static $pool = [];

    /**
     * Create class instance
     *
     * @param string $class
     * @param array  $params
     *
     * @return object
     * @throws \ReflectionException
     */
    public static function create(string $class, array $params): object
    {
        //Create class instance without '__construct'
        if (!method_exists($class, '__construct')) {
            $class_object = self::build($class);
        } else {
            /** @var \core\lib\std\reflect $unit_reflect */
            $unit_reflect = self::build(reflect::class);

            //Get matched param list
            $matched_params = $unit_reflect->build_params($class, '__construct', $params);

            if (!empty($matched_params['diff'])) {
                throw new \Exception(self::build(router::class)->get_key_name($class, '__construct') . ' => Missing params: [' . implode(', ', $matched_params['diff']) . ']', E_USER_NOTICE);
            }

            //Get class object with '__construct'
            $class_object = self::build($class, $matched_params['param']);

            unset($unit_reflect, $matched_params);
        }

        unset($class, $params);
        return $class_object;
    }

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

    /**
     * Move an object associated with alias
     *
     * @param object $object
     * @param string $alias
     *
     * @return object
     */
    public static function move(object $object, string $alias): object
    {
        //Delete original object
        self::del($object);

        //Save object as alias
        self::$pool[$alias] = &$object;

        unset($alias);
        return $object;
    }

    /**
     * Find an object via alias
     *
     * @param string $alias
     *
     * @return object
     * @throws \Exception
     */
    public static function find(string $alias): object
    {
        if (isset(self::$pool[$alias])) {
            return self::$pool[$alias];
        }

        //Alias NOT found
        throw new \Exception('Object named [' . $alias . '] NOT found in factory!', E_USER_ERROR);
    }

    /**
     * Delete an object from pool
     *
     * @param object $object
     */
    public static function del(object $object): void
    {
        if (!empty($keys = array_keys(self::$pool, $object, true))) {
            foreach ($keys as $key) {
                unset(self::$pool[$key]);
            }
        }

        unset($object, $keys, $key);
    }
}