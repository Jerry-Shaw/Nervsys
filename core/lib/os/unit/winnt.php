<?php

/**
 * winnt handler
 *
 * Copyright 2016-2019 liu <2579186091@qq.com>
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

namespace core\lib\os\unit;

use core\lib\os\unit;

/**
 * Class winnt
 *
 * @package core\lib\os\unit
 */
final class winnt extends unit
{
    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public function get_hw_hash(): string
    {
        $queries = [
            'wmic nic get AdapterType, MACAddress, Manufacturer, Name, PNPDeviceID /format:value',
            'wmic cpu get Caption, CreationClassName, Family, Manufacturer, Name, ProcessorId, ProcessorType, Revision /format:value',
            'wmic baseboard get Manufacturer, Product, SerialNumber, Version /format:value',
            'wmic diskdrive get Model, Size /format:value',
            'wmic memorychip get BankLabel, Capacity /format:value'
        ];

        //Execute command
        exec(implode(' && ', $queries), $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $output = array_filter($output);
        $output = array_unique($output);

        $hash = hash('md5', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hash;
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public function get_php_path(): string
    {
        //Execute command
        exec('wmic process where ProcessId="' . getmypid() . '" get ExecutablePath /format:value', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        //Parse output as ini string
        $output = parse_ini_string(implode($output));

        if (false === $output) {
            throw new \Exception(PHP_OS . ': PHP path NOT found!', E_USER_ERROR);
        }

        $env = &$output['ExecutablePath'];

        unset($output, $status);
        return $env;
    }

    /**
     * Set as background command
     *
     * @return $this
     */
    public function bg(): object
    {
        $this->os_cmd = 'start "" /B ' . $this->os_cmd . ' >nul 2>&1';
        return $this;
    }

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    public function env(): object
    {
        return $this;
    }

    /**
     * Set command for proc_* functions
     *
     * @return $this
     */
    public function proc(): object
    {
        $this->os_cmd = '"' . $this->os_cmd . '"';
        return $this;
    }
}