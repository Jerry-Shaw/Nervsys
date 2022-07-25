<?php

/**
 * WINNT controller library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
 * Copyright 2021 take your time <704505144@qq.com>
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

namespace Nervsys\Core\OSC;

class WINNT
{
    /**
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        $ps_cmd = 'powershell -Command "';
        $ps_cmd .= 'Get-WMIObject -class Win32_Processor | select Caption, CreationClassName, Family, Manufacturer, Name, ProcessorId, ProcessorType | Format-List;';
        $ps_cmd .= 'Get-WMIObject -class Win32_BaseBoard | select Manufacturer, Product, SerialNumber, Version | Format-List;';
        $ps_cmd .= 'Get-NetAdapter -physical | select InterfaceDescription, MacAddress | Format-List;';
        $ps_cmd .= 'Get-WMIObject -class Win32_PhysicalMemory | select Capacity | Format-List;';
        $ps_cmd .= 'Get-WMIObject -class Win32_BIOS | select SerialNumber | Format-List"';

        exec($ps_cmd, $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $hw_info = '';

        foreach ($output as $value) {
            if (!str_contains($value, ':')) {
                continue;
            }

            [$k, $v] = explode(':', $value, 2);
            $hw_info .= trim($k) . ':' . trim($v) . PHP_EOL;
        }

        $hw_hash = hash('md5', trim($hw_info));

        unset($ps_cmd, $output, $status, $hw_info, $value, $k, $v);
        return $hw_hash;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        $ps_cmd = 'powershell -Command "Get-WMIObject -class Win32_process -Filter "ProcessId=' . getmypid() . '" | select ExecutablePath | Format-List"';

        exec($ps_cmd, $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $php_path = '';
        $output   = array_filter($output);

        foreach ($output as $value) {
            if (0 < ($mark_pos = strpos($value, ':'))) {
                $php_path = trim(substr($value, $mark_pos + 2));
                break;
            }
        }

        if ('' === $php_path) {
            throw new \Exception(PHP_OS . ': PHP path NOT found!', E_USER_ERROR);
        }

        if (!is_file($php_path)) {
            throw new \Exception(PHP_OS . ': PHP path ERROR!', E_USER_ERROR);
        }

        unset($ps_cmd, $output, $status, $value, $mark_pos);
        return $php_path;
    }

    /**
     * @param string $command
     *
     * @return string
     */
    public function buildBackgroundCmd(string $command): string
    {
        return 'start "" /B ' . $command . ' > nul 2>&1';
    }

    /**
     * @param string $command
     *
     * @return string
     */
    public function runWithProfile(string $command): string
    {
        return $command;
    }
}