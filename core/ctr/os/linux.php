<?php

/**
 * linux Platform Module
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2018 秋水之冰 <27206617@qq.com>
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

namespace core\ctr\os;

use core\ctr\os, core\ctr\os\lib\cmd;

class linux extends os implements cmd
{
    /**
     * Get PHP path
     */
    public static function php_env(): string
    {
        //Execute system command
        exec('readlink -f /proc/' . getmypid() . '/exe', $output, $status);
        if (0 !== $status) throw new \Exception('Linux: Access denied!');

        //Get executable path
        $env = &$output[0];

        unset($output, $status);
        return '"' . $env . '"';
    }

    /**
     * Get system hash
     */
    public static function sys_hash(): string
    {
        $queries = [
            'lscpu | grep -E "Architecture|CPU|Thread|Core|Socket|Vendor|Model|Stepping|BogoMIPS|L1|L2|L3"',
            'cat /proc/cpuinfo | grep -E "processor|vendor|family|model|microcode|MHz|cache|physical|address"',
            'dmidecode -t memory',
            'mac'  => 'ip link show | grep link/ether',
            'disk' => 'lsblk'
        ];

        //Run command
        $output = [];
        foreach ($queries as $query) {
            exec($query, $output, $status);
            if (0 !== $status) throw new \Exception('Linux: Access denied!');
        }

        $output = array_filter($output);
        $output = array_unique($output);

        $hash = hash('sha256', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hash;
    }

    /**
     * Build command for background process
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function cmd_bg(string $cmd): string
    {
        return '"' . $cmd . '" > /dev/null 2>/dev/null &';
    }

    /**
     * Build command for proc_open
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