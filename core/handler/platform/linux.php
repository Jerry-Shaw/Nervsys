<?php

/**
 * linux handler
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

namespace core\handler\platform;

use core\handler\platform\lib\os;

class linux implements os
{
    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public static function hw_hash(): string
    {
        $queries = [
            'lscpu | grep -E "Architecture|CPU|Thread|Core|Socket|Vendor|Model|Stepping|BogoMIPS|L1|L2|L3"',
            'cat /proc/cpuinfo | grep -E "processor|vendor|family|model|microcode|MHz|cache|physical|address"',
            'dmidecode -t memory',
            'mac'  => 'ip link show | grep link/ether',
            'disk' => 'lsblk'
        ];

        $output = [];
        foreach ($queries as $query) {
            exec($query, $output, $status);

            if (0 !== $status) {
                throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
            }
        }

        $output = array_filter($output);
        $output = array_unique($output);

        $hash = hash('sha256', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hash;
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public static function php_path(): string
    {
        exec('readlink -f /proc/' . getmypid() . '/exe', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $env = &$output[0];

        unset($output, $status);
        return $env;
    }

    /**
     * Build background command
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function cmd_bg(string $cmd): string
    {
        return 'nohup "' . $cmd . '" >/dev/null 2>&1 &';
    }

    /**
     * Build proc_open command
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function cmd_proc(string $cmd): string
    {
        return $cmd;
    }
}