<?php

/**
 * linux Platform Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
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