<?php

/**
 * TrustZone Parser
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

namespace core\parser;

use core\handler\factory;

class trustzone extends factory
{
    //TrustZone record
    private static $record = [];

    /**
     * Initialize TrustZone records
     *
     * @param string $class
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function init(string $class): array
    {
        //Reset TrustZone
        self::$record = [];

        if (isset($class::$tz)) {
            //Fetch via static calling
            $record = $class::$tz;
        } elseif (!method_exists($class, '__construct')) {
            //Fetch via object property
            $record = parent::obtain($class)->tz ?? [];
        } else {
            //Reflect constructor
            $reflect = new \ReflectionMethod($class, '__construct');

            //Check constructor visibility
            if (!$reflect->isPublic()) {
                throw new \ReflectionException('TrustZone ERROR: Initialize "' . $class . '" failed!', E_USER_WARNING);
            }

            //Fetch via constructor property
            $record = parent::obtain($class, data::build_argv($reflect, parent::$data))->tz ?? [];
            unset($reflect);
        }

        //Copy TrustZone
        self::$record = is_string($record) ? self::fill_key($record) : $record;

        unset($class, $record);
        return array_keys(self::$record);
    }

    /**
     * Get method dependency
     *
     * @param string $method
     *
     * @return array
     */
    public static function get_dep(string $method): array
    {
        return [
            'pre'  => isset(self::$record[$method]['pre']) ? self::fill_val(self::$record[$method]['pre']) : [],
            'post' => isset(self::$record[$method]['post']) ? self::fill_val(self::$record[$method]['post']) : []
        ];
    }

    /**
     * Verify TrustZone params
     *
     * @param string $class
     * @param string $method
     *
     * @throws \Exception
     */
    public static function verify(string $class, string $method): void
    {
        //Get param values
        $value = is_string(self::$record[$method])
            ? self::$record[$method]
            : (self::$record[$method]['param'] ?? '');

        //Skip param check
        if ('' === $value) {
            return;
        }

        $param = self::fill_val($value);
        $diff  = array_diff($param, array_intersect(array_keys(parent::$data), $param));

        if (!empty($param) && !empty($diff)) {
            //Report TrustZone missing
            throw new \Exception(
                $class . '::' . $method
                . ': TrustZone mismatch [' . (implode(', ', $diff)) . ']',
                E_USER_WARNING
            );
        }

        unset($class, $method, $value, $param, $diff);
    }


    /**
     * Fill TrustZone using keys
     *
     * @param string $value
     *
     * @return array
     */
    private static function fill_key(string $value): array
    {
        $data  = [];
        $items = false !== strpos($value, ',') ? array_filter(explode(',', $value)) : [$value];

        foreach ($items as $item) {
            if ('' !== $item = trim($item)) {
                $data[$item] = '';
            }
        }

        unset($value, $items, $item);
        return $data;
    }

    /**
     * Fill TrustZone using values
     *
     * @param string $value
     *
     * @return array
     */
    private static function fill_val(string $value): array
    {
        $data  = [];
        $items = false !== strpos($value, ',') ? array_filter(explode(',', $value)) : [$value];

        foreach ($items as $item) {
            if ('' !== $item = trim($item)) {
                $data[] = $item;
            }
        }

        unset($value, $items, $item);
        return $data;
    }
}