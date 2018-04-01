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
     * Get PHP path
     */
    public static function php_env(): string
    {
        exec('wmic process where ProcessId="' . getmypid() . '" get ExecutablePath /format:value', $output, $status);
        if (0 !== $status) throw new \Exception('WinNT: Access denied!');

        $output = parse_ini_string(implode($output));
        if (false === $output) throw new \Exception('WinNT: Access denied!');

        $env = &$output['ExecutablePath'];

        unset($output, $status);
        return '"' . $env . '"';
    }

    /**
     * Get system hash
     */
    public static function sys_hash(): string
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