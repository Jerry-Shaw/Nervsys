<?php

/**
 * winnt Platform Module
 *
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
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