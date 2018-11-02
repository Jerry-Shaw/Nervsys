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

        //Fetch TrustZone
        if (isset($class::$tz)) {
            self::$record = $class::$tz;
        } elseif (!method_exists($class, '__construct')) {
            self::$record = parent::obtain($class)->tz ?? [];
        } else {
            //Reflect method
            $reflect = new \ReflectionMethod($class, '__construct');

            //Check visibility
            if (!$reflect->isPublic()) {
                throw new \ReflectionException('TrustZone ERROR: Initialize "' . $class . '" failed!', E_USER_WARNING);
            }

            //Fetch TrustZone
            self::$record = parent::obtain($class, data::build_argv($reflect, parent::$data))->tz ?? [];
            unset($reflect);
        }

        //Parse stringified TrustZone
        if (is_string(self::$record)) {
            //Parse TrustZone
            $items = false !== strpos(self::$record, ',') ? array_filter(explode(',', self::$record)) : [self::$record];

            //Refill TrustZone
            self::$record = [];
            foreach ($items as $item) {
                if ('' !== $item = trim($item)) {
                    self::$record[$item] = [];
                }
            }

            unset($items, $item);
        }

        unset($class);
        return array_keys(self::$record);
    }

    /**
     * Fetch TrustZone pre & post
     *
     * @param string $class
     * @param string $method
     *
     * @return array
     */
    public static function fetch(string $class, string $method): array
    {
        $val  = [];
        $data = self::$record[$method] ?? [];

        $val['pre']  = isset($data['pre']) ? self::prep_cmd($data['pre']) : [];
        $val['post'] = isset($data['post']) ? self::prep_cmd($data['post']) : [];

        unset($class, $method, $data);
        return $val;
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
        $value = self::$record[$method] ?? [];
        $param = isset($value['param']) ? $value['param'] : (isset($value[0]) ? $value : []);

        if (!empty($param) && !empty($diff = array_diff($param, array_intersect(array_keys(parent::$data), $param)))) {
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
     * Prepare TrustZone CMD
     *
     * @param array $cmd
     *
     * @return array
     */
    private static function prep_cmd(array $cmd): array
    {
        $data = [];

        foreach ($cmd as $item) {
            if (false !== strpos($item, '-')) {
                list($order, $method) = explode('-', $item, 2);
                $data[] = ['order' => &$order, 'method' => &$method];
            }
        }

        unset($cmd, $item, $order, $method);
        return $data;
    }
}