<?php

/**
 * NS WINNT controller library
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

namespace Core\Lib\OS;

/**
 * Class WINNT
 *
 * @package Core\Lib\OS
 */
class WINNT
{
    public string $os_cmd;

    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        $queries = [
            'wmic cpu get Caption, CreationClassName, Family, Manufacturer, Name, ProcessorId, ProcessorType, Revision /format:value',
            'wmic nic get AdapterType, MACAddress, Manufacturer, Name, PNPDeviceID /format:value',
            'wmic baseboard get Manufacturer, Product, SerialNumber, Version /format:value',
            'wmic memorychip get BankLabel, Capacity /format:value'
        ];

        exec(implode(' && ', $queries), $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $output  = array_filter($output);
        $output  = array_unique($output);
        $hw_hash = hash('md5', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hw_hash;
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        exec('wmic process where ProcessId="' . getmypid() . '" get ExecutablePath /format:value', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        //Parse result as ini
        if (false === ($output = parse_ini_string(implode($output)))) {
            throw new \Exception(PHP_OS . ': PHP path NOT found!', E_USER_ERROR);
        }

        $php_path = &$output['ExecutablePath'];

        unset($output, $status);
        return $php_path;
    }

    /**
     * Set as background command
     *
     * @return $this
     */
    public function setAsBg(): self
    {
        $this->os_cmd = 'start "" /B ' . $this->os_cmd . ' >nul 2>&1';
        return $this;
    }

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    public function setEnvPath(): self
    {
        return $this;
    }

    /**
     * Set command for proc_* functions
     *
     * @return $this
     */
    public function setForProc(): self
    {
        $this->os_cmd = '"' . $this->os_cmd . '"';
        return $this;
    }
}