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

class trustzone
{
    /**
     * Get TrustZone keys
     *
     * @param string $class
     *
     * @return array
     */
    public static function key(string $class): array
    {
        return isset($class::$tz) && is_array($class::$tz) ? array_keys($class::$tz) : [];
    }

    /**
     * Get TrustZone values
     *
     * @param string $class
     * @param string $method
     *
     * @return array
     */
    public static function value(string $class, string $method): array
    {
        $value = [];
        $data  = &($class::$tz)[$method];

        $value['pre']  = isset($data['pre']) ? self::prep_cmd($data['pre']) : [];
        $value['post'] = isset($data['post']) ? self::prep_cmd($data['post']) : [];

        unset($class, $method, $data);
        return $value;
    }

    /**
     * Verify TrustZone param
     *
     * @param array  $keys
     * @param string $class
     * @param string $method
     *
     * @throws \Exception
     */
    public static function verify(array $keys, string $class, string $method): void
    {
        $data  = &($class::$tz)[$method];
        $param = isset($data['param']) ? $data['param'] : (isset($data[0]) ? $data : []);

        //Compare data with TrustZone
        if (!empty($param) && !empty($diff = array_diff($param, array_intersect($keys, $param)))) {
            //Report TrustZone missing
            throw new \Exception(
                ltrim($class, '\\')
                . ' => '
                . $method
                . ': TrustZone mismatch [' . (implode(', ', $diff)) . ']'
            );
        }

        unset($keys, $class, $method, $data, $param, $diff);
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