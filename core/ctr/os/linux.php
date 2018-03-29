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
     * Format system output data
     *
     * @param array $data
     */
    private static function format(array &$data): void
    {
        if (empty($data)) return;

        $key = 0;
        $list = [];

        foreach ($data as $line) {
            $line = trim($line);
            if ('' === $line) {
                ++$key;
                continue;
            }

            if (false === strpos($line, ':')) continue;
            list($name, $value) = explode(':', $line, 2);

            $name = trim($name);
            $value = trim($value);

            if (!isset($list[$key][$name]) && '' !== $value) $list[$key][$name] = $value;
        }

        $data = array_values($list);
        unset($key, $list, $line, $name, $value);
    }

    /**
     * Get PHP environment information
     */
    public static function info_env(): void
    {
        //Get pid
        parent::$env['PHP_PID'] = getmypid();

        //Execute system command
        exec('cat /proc/' . parent::$env['PHP_PID'] . '/cmdline | strings -1', $output, $status);
        if (0 !== $status) throw new \Exception('Linux: Access denied!');

        //Get CMD
        parent::$env['PHP_CMD'] = implode(' ', $output);

        //Empty output
        $output = [];

        //Execute system command
        exec('readlink -f /proc/' . getmypid() . '/exe', $output, $status);
        if (0 !== $status) throw new \Exception('Linux: Access denied!');

        //Get executable path
        parent::$env['PHP_EXE'] = '"' . $output[0] . '"';

        unset($output, $status);
    }

    /**
     * Get System information
     */
    public static function info_sys(): void
    {
        $queries = [
            'lscpu | grep -E "Architecture|CPU|Thread|Core|Socket|Vendor|Model|Stepping|BogoMIPS|L1|L2|L3"',
            'cat /proc/cpuinfo | grep -E "processor|vendor|family|model|microcode|MHz|cache|physical|address"',
            'dmidecode -t memory'
        ];

        //Run command
        $output = [];
        foreach ($queries as $query) {
            exec($query, $output, $status);
            if (0 !== $status) throw new \Exception('Linux: Access denied!');
        }

        self::format($output);

        $queries = [
            'mac'  => 'ip link show | grep link/ether',
            'disk' => 'lsblk'
        ];

        //Run command
        foreach ($queries as $key => $query) {
            $value = [];
            exec($query, $value, $status);
            if (0 !== $status) throw new \Exception('Linux: Access denied!');

            $output[$key] = 1 < count($value) ? $value : trim($value[0]);
        }

        parent::$sys = &$output;

        unset($queries, $output, $query, $status, $key, $value);
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