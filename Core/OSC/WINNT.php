<?php

/**
 * WINNT controller library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2025 秋水之冰 <27206617@qq.com>
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
    public object|null $wmi = null;

    /**
     * Create COM and WMI object if possible
     */
    public function __construct()
    {
        if (class_exists('COM')) {
            $com_object = new \COM('WbemScripting.SWbemLocator');
            $this->wmi  = $com_object->ConnectServer();
        }
    }

    /**
     * @return array
     */
    public function getIPv4(): array
    {
        exec('powershell -Command "Get-NetIPAddress -AddressFamily IPv4 -InterfaceIndex $(Get-NetConnectionProfile | Select-Object -ExpandProperty InterfaceIndex) | Select-Object -ExpandProperty IPAddress"', $output, $status);

        $ip_v4 = 0 === $status ? array_filter($output) : [];

        unset($output, $status);
        return $ip_v4;
    }

    /**
     * @return array
     */
    public function getIPv6(): array
    {
        exec('powershell -Command "Get-NetIPAddress -AddressFamily IPv6 -PrefixOrigin RouterAdvertisement -SuffixOrigin Link | Select-Object -ExpandProperty IPAddress"', $output, $status);

        $ip_v6 = 0 === $status ? array_filter($output) : [];

        unset($output, $status);
        return $ip_v6;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        $hw_info = [];

        if (!is_null($this->wmi)) {
            $query = $this->wmi->ExecQuery('Select * from Win32_ComputerSystem');

            foreach ($query as $object) {
                $hw_info[] = $object->Model;
            }

            $query = $this->wmi->ExecQuery('SELECT * FROM Win32_Processor');

            foreach ($query as $object) {
                $hw_info[] = $object->Name;
                $hw_info[] = $object->Family;
                $hw_info[] = $object->DeviceID;
                $hw_info[] = $object->Manufacturer;
                $hw_info[] = $object->Description;
                $hw_info[] = $object->ProcessorId;
                $hw_info[] = $object->Architecture;
                $hw_info[] = $object->NumberOfCores;
                $hw_info[] = $object->ProcessorType;
            }

            $query = $this->wmi->ExecQuery('SELECT * FROM Win32_BaseBoard');

            foreach ($query as $object) {
                $hw_info[] = $object->Manufacturer;
                $hw_info[] = $object->Product;
                $hw_info[] = $object->SerialNumber;
                $hw_info[] = $object->Version;
            }

            $query = $this->wmi->ExecQuery('SELECT * FROM Win32_NetworkAdapter WHERE PhysicalAdapter = TRUE');

            foreach ($query as $object) {
                $hw_info[] = $object->Name;
                $hw_info[] = $object->MACAddress;
                $hw_info[] = $object->PNPDeviceID;
                $hw_info[] = $object->AdapterType;
            }

            $query = $this->wmi->ExecQuery('SELECT * FROM Win32_BIOS');

            foreach ($query as $object) {
                $hw_info[] = $object->Manufacturer;
                $hw_info[] = $object->SerialNumber;
            }

            $hw_info = array_values(array_filter($hw_info));

            foreach ($hw_info as $key => $value) {
                $hw_info[$key] = trim($value);
            }

            unset($query, $object);
        } else {
            $ps_cmd = 'powershell -Command "';
            $ps_cmd .= 'Get-WMIObject -class Win32_ComputerSystem | select Model | Format-List;';
            $ps_cmd .= 'Get-WMIObject -class Win32_Processor | select Name, Family, DeviceID, Manufacturer, Description, ProcessorId, Architecture, NumberOfCores, ProcessorType | Format-List;';
            $ps_cmd .= 'Get-WMIObject -class Win32_BaseBoard | select Manufacturer, Product, SerialNumber, Version | Format-List;';
            $ps_cmd .= 'Get-NetAdapter -physical | select Name, MACAddress, PNPDeviceID, AdapterType | Format-List;';
            $ps_cmd .= 'Get-WMIObject -class Win32_BIOS | select Manufacturer, SerialNumber | Format-List"';

            exec($ps_cmd, $hw_info, $status);

            if (0 !== $status) {
                throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
            }

            foreach ($hw_info as $key => $value) {
                if (!str_contains($value, ':')) {
                    unset($hw_info[$key]);
                    continue;
                }

                [$k, $v] = explode(':', $value, 2);

                $k = trim($k);
                $v = trim($v);

                if ('' === $v) {
                    unset($hw_info[$key]);
                    continue;
                }

                $hw_info[$key] = $k . ':' . $v;
            }

            $hw_info = array_values($hw_info);

            unset($ps_cmd, $status, $k, $v);
        }

        $hw_hash = hash('md5', trim(implode('/', $hw_info)));

        unset($hw_info, $key, $value);
        return $hw_hash;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        $ps_cmd   = 'powershell -command "(Get-Process -Id ' . getmypid() . ').Path"';
        $php_path = trim(shell_exec($ps_cmd));

        if (!is_string($php_path)) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        if (!is_file($php_path)) {
            throw new \Exception(PHP_OS . ': PHP path ERROR!', E_USER_ERROR);
        }

        unset($ps_cmd);
        return $php_path;
    }

    /**
     * @param int $pid
     *
     * @return void
     */
    public function killProc(int $pid): void
    {
        exec('taskkill -PID ' . $pid . ' -F');
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