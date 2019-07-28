<?php

/**
 * winnt handler
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

class winnt implements os
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
            'wmic nic get AdapterType, MACAddress, Manufacturer, Name, PNPDeviceID /format:value',
            'wmic cpu get Caption, CreationClassName, Family, Manufacturer, Name, ProcessorId, ProcessorType, Revision /format:value',
            'wmic baseboard get Manufacturer, Product, SerialNumber, Version /format:value',
            'wmic diskdrive get Model, Size /format:value',
            'wmic memorychip get BankLabel, Capacity /format:value'
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
        exec('wmic process where ProcessId="' . getmypid() . '" get ExecutablePath /format:value', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        //Parse output as ini string
        $output = parse_ini_string(implode($output));

        if (false === $output) {
            throw new \Exception(PHP_OS . ': Execute failed!', E_USER_ERROR);
        }

        $env = &$output['ExecutablePath'];

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
        return 'start "" /B ' . $cmd . ' >nul 2>&1';
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
        return '"' . $cmd . '"';
    }
}