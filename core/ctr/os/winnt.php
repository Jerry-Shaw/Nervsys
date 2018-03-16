<?php

/**
 * winnt Platform Module
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

class winnt extends os implements cmd
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

            if (false === strpos($line, '=')) continue;
            list($name, $value) = explode('=', $line, 2);
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
        exec('wmic process where ProcessId="' . getmypid() . '" get ProcessId, CommandLine, ExecutablePath /format:value', $output, $status);
        if (0 !== $status) throw new \Exception('WinNT: Access denied!');

        unset($status);

        self::format($output);

        $output = current($output);

        parent::$env['PHP_PID'] = &$output['ProcessId'];
        parent::$env['PHP_CMD'] = &$output['CommandLine'];
        parent::$env['PHP_EXE'] = '"' . $output['ExecutablePath'] . '"';

        unset($output, $info);
    }

    /**
     * Get System information
     */
    public static function info_sys(): void
    {
        $queries = [
            'wmic nic get AdapterType, MACAddress, Manufacturer, Name, PNPDeviceID /format:value',
            'wmic cpu get Caption, CreationClassName, Family, Manufacturer, Name, ProcessorId, ProcessorType, Revision /format:value',
            'wmic baseboard get Manufacturer, Product, SerialNumber, Version /format:value',
            'wmic diskdrive get Model, Size /format:value',
            'wmic memorychip get BankLabel, Capacity /format:value'
        ];

        //Run command
        $output = [];
        foreach ($queries as $query) {
            exec($query, $output, $status);
            if (0 !== $status) throw new \Exception('WinNT: Access denied!');
        }

        self::format($output);

        foreach ($output as $key => $value) if (1 < count($value)) parent::$sys[] = $value;
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
        return 'start "Process" /B "' . $cmd . '"';
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
        return '"' . $cmd . '"';
    }
}