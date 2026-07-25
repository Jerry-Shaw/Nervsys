<?php

/**
 * WINNT controller library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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
     */
    public function getBootInfo(): string
    {
        $info = '';
        $cmd  = 'net statistics workstation';

        exec($cmd, $output, $status);

        if (0 === $status && !empty($output)) {
            $output = array_values(array_filter($output, 'strlen'));
            $info   = $output[1] ?? '';
        }

        unset($cmd, $output, $status);
        return $info;
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
                $hw_info[] = $object->Name
                    . ' ' . $object->Family
                    . ' ' . $object->DeviceID
                    . ' ' . $object->Manufacturer
                    . ' ' . $object->Description
                    . ' ' . $object->ProcessorId
                    . ' ' . $object->Architecture
                    . ' ' . $object->NumberOfCores
                    . ' ' . $object->ProcessorType;
            }

            $query = $this->wmi->ExecQuery('SELECT * FROM Win32_BaseBoard');

            foreach ($query as $object) {
                $hw_info[] = $object->Manufacturer
                    . ' ' . $object->Product
                    . ' ' . $object->SerialNumber
                    . ' ' . $object->Version;
            }

            $query = $this->wmi->ExecQuery('SELECT * FROM Win32_NetworkAdapter WHERE PhysicalAdapter = TRUE');

            foreach ($query as $object) {
                if (!is_null($object->MACAddress) && '' !== $object->MACAddress) {
                    $hw_info[] = $object->Name
                        . ' ' . $object->MACAddress
                        . ' ' . $object->PNPDeviceID
                        . ' ' . $object->AdapterType;
                }
            }

            $query = $this->wmi->ExecQuery('SELECT * FROM Win32_BIOS');

            foreach ($query as $object) {
                $hw_info[] = $object->Manufacturer
                    . ' ' . $object->SerialNumber;
            }

            unset($query, $object);
        }

        if (empty($hw_info)) {
            $ps_cmd = 'powershell -Command "' .
                '$out=@();' .
                'Get-WmiObject -class Win32_ComputerSystem | ForEach-Object { $out += $_.Model };' .
                'Get-WmiObject -class Win32_Processor | ForEach-Object { $out += "$($_.Name) $($_.Family) $($_.DeviceID) $($_.Manufacturer) $($_.Description) $($_.ProcessorId) $($_.Architecture) $($_.NumberOfCores) $($_.ProcessorType)" };' .
                'Get-WmiObject -class Win32_BaseBoard | ForEach-Object { $out += "$($_.Manufacturer) $($_.Product) $($_.SerialNumber) $($_.Version)" };' .
                'Get-NetAdapter -physical | Where-Object { $_.MACAddress } | ForEach-Object { $out += "$($_.Name) $($_.MACAddress) $($_.PNPDeviceID) $($_.AdapterType)" };' .
                'Get-WmiObject -class Win32_BIOS | ForEach-Object { $out += "$($_.Manufacturer) $($_.SerialNumber)" };' .
                '$out -join "`n"' .
                '"';

            exec($ps_cmd, $hw_info, $status);

            if (0 !== $status) {
                throw new \Exception(PHP_OS . ': Access denied!');
            }

            unset($ps_cmd, $status);
        }

        if (empty($hw_info)) {
            throw new \Exception(PHP_OS . ': Failed to get hardware information!');
        }

        $virtual = ['hotspot', 'hosted', 'tunnel', 'ipsec', 'ppp', 'tap', 'tun', 'vpn'];

        foreach ($hw_info as $key => $line) {
            $line = trim($line);

            if ('' === $line) {
                unset($hw_info[$key]);
                continue;
            }

            foreach ($virtual as $keyword) {
                if (false !== stripos($line, $keyword)) {
                    unset($hw_info[$key]);
                    continue 2;
                }
            }
        }

        if (empty($hw_info)) {
            throw new \Exception(PHP_OS . ': No valid hardware information collected!');
        }

        $hw_info = array_values($hw_info);
        $hw_hash = hash('md5', trim(implode('|', $hw_info)));

        unset($hw_info, $virtual, $key, $line, $keyword);
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
            throw new \Exception(PHP_OS . ': Access denied!');
        }

        if (!is_file($php_path)) {
            throw new \Exception(PHP_OS . ': PHP path ERROR!');
        }

        unset($ps_cmd);
        return $php_path;
    }

    /**
     * @param string $program
     *
     * @return array
     */
    public function findPath(string $program): array
    {
        $paths = [];
        $cmd   = 'where ' . escapeshellarg($program);

        exec($cmd, $output, $status);

        if (0 === $status && !empty($output)) {
            $paths = array_values(array_filter(array_map('trim', $output), 'strlen'));
        }

        unset($program, $cmd, $output, $status);
        return $paths;
    }

    /**
     * @param int    $port
     * @param string $state
     *
     * @return array
     */
    public function findPidsByPortState(int $port, string $state): array
    {
        $pids = [];
        $cmd  = 'netstat -ano | findstr :' . $port;

        exec($cmd, $output, $status);

        if (0 === $status && !empty($output)) {
            foreach ($output as $line) {
                if (false !== stripos($line, $state)) {
                    $parts = array_values(array_filter(explode(' ', $line), 'strlen'));
                    $pid   = end($parts);
                    if (is_numeric($pid) && 0 < (int)$pid) {
                        $pids[] = (int)$pid;
                    }
                }
            }
        }

        $pids = array_values(array_unique($pids));

        unset($port, $state, $cmd, $output, $status, $line, $parts, $pid);
        return $pids;
    }

    /**
     * @param int $pid
     *
     * @return void
     */
    public function killPid(int $pid): void
    {
        exec('taskkill -PID ' . $pid . ' -F >nul 2>&1');
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