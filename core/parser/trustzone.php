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

use core\helper\log;

class trustzone
{
    /**
     * Prepare TrustZone data
     *
     * @param array $trustzone
     *
     * @return array
     */
    public static function prep(array $trustzone): array
    {
        $data = [];

        $data['pre'] = isset($trustzone['pre']) ? self::prep_cmd($trustzone['pre']) : [];
        $data['post'] = isset($trustzone['post']) ? self::prep_cmd($trustzone['post']) : [];
        $data['param'] = isset($trustzone['param']) ? $trustzone['param'] : $trustzone;

        unset($trustzone);
        return $data;
    }

    /**
     * Check TrustZone data
     *
     * @param string $name
     * @param string $method
     * @param array  $data
     * @param array  $param
     *
     * @return bool
     */
    public static function fail(string $name, string $method, array $data, array $param): bool
    {
        //Compare data with TrustZone
        $inter = array_intersect($data, $param);
        $diff = array_diff($param, $inter);
        $failed = !empty($diff);

        //Report TrustZone missing
        if ($failed) {
            log::log('debug', $name . '-' . $method . ': ' . 'TrustZone missing [' . (implode(', ', $diff)) . ']');
        }

        unset($name, $method, $data, $param, $inter, $diff);
        return $failed;
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
        if (!is_array($cmd) || empty($cmd)) {
            return [];
        }

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