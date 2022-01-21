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
        $ps_cmd = 'powershell -Command "';
        $ps_cmd .= 'Get-CimInstance -class Win32_Processor | select Caption, CreationClassName, Family, Manufacturer, Name, ProcessorId, ProcessorType;';
        $ps_cmd .= 'Get-CimInstance -class Win32_NetworkAdapter -Filter "netconnectionid!=NULL" | select macaddress;';
        $ps_cmd .= 'Get-CimInstance -class Win32_BaseBoard | select Manufacturer, Product, SerialNumber, Version;';
        $ps_cmd .= 'Get-CimInstance -class Win32_PhysicalMemory | select Capacity;';
        $ps_cmd .= 'Get-CimInstance -class Win32_BIOS | select SerialNumber';
        $ps_cmd .= '"';

        exec($ps_cmd, $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $hw_hash = hash('md5', json_encode(array_filter($output)));

        unset($ps_cmd, $output, $status);
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
        $ps_cmd = 'powershell -Command "Get-CimInstance -class Win32_process -Filter "ProcessId=' . getmypid() . '" | select ExecutablePath | Format-List"';

        exec($ps_cmd, $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $process_info = current(array_filter($output));

        if (false === $process_info || false === ($mark_pos = strpos($process_info, ':'))) {
            throw new \Exception(PHP_OS . ': PHP path NOT found!', E_USER_ERROR);
        }

        $php_path = trim(substr($process_info, $mark_pos + 2));

        if (!is_file($php_path)) {
            throw new \Exception(PHP_OS . ': PHP path ERROR!', E_USER_ERROR);
        }

        unset($ps_cmd, $output, $status, $process_info, $mark_pos);
        return $php_path;
    }

    /**
     * Set as background command
     *
     * @return $this
     */
    public function setAsBg(): self
    {
        $this->os_cmd = 'start "" / B ' . $this->os_cmd . ' > nul 2 >&1';
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