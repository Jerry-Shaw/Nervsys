<?php

/**
 * NS WINNT controller library
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
  * Copyright 2016-2021 take your time <704505144@qq.com>
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
            'wmic baseboard get Manufacturer, Product, SerialNumber, Version /format:value',
            'wmic nic where netconnectionid!=NULL get macaddress /format:value',
            'wmic memorychip get BankLabel, Capacity /format:value',
            'wmic bios get SerialNumber /format:value'
        ];
        $cmd = "powershell -Command \"";
	$queries  = [
		"Get-WmiObject -class Win32_Processor | select Caption, CreationClassName, Family, Manufacturer, Name, ProcessorId, ProcessorType, Revision",
		"Get-WmiObject -class Win32_BaseBoard  | select Manufacturer, Product, SerialNumber, Version",
		"Get-WmiObject -class Win32_NetworkAdapter  -Filter \"netconnectionid!=NULL\" | select macaddress",
		"Get-WmiObject -class Win32_PhysicalMemory   | select BankLabel, Capacity",
		"Get-WmiObject -class Win32_BIOS   | select SerialNumber",			
		];	
	$cmd .= implode(';',$queries) . "\"";
        exec($cmd, $output, $status);
        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $output = array_filter($output);
        $output = array_unique($output);
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
     $str = "powershell -Command \"Get-WmiObject -class Win32_process -Filter \"ProcessId=".getmypid()."\"  |  select ExecutablePath\"";
        exec($str,$output,$status);
	$output = array_filter($output);
	$output = array_unique($output);
        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        if (!isset($output[3]) || '' === $output[3]) {
            throw new \Exception(PHP_OS . ': PHP path NOT found!', E_USER_ERROR);
        }

        $php_path = &$output[3];

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
}