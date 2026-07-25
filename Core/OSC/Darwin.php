<?php

/**
 * Darwin controller library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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

class Darwin
{
    /**
     * @return array
     */
    public function getIPv4(): array
    {
        exec("ifconfig | grep 'inet' | grep -v 'inet6' | grep -v '127*' | awk '{print $2}' | awk '{print $1}'", $output, $status);

        $ip_v4 = 0 === $status ? array_filter($output) : [];

        unset($output, $status);
        return $ip_v4;
    }

    /**
     * @return array
     */
    public function getIPv6(): array
    {
        exec("ifconfig | grep 'inet6' | grep -v '::1' | grep -v '%' | awk '{print $2}' | awk '{print $1}'", $output, $status);

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
        $cmd  = 'sysctl -n kern.boottime';

        exec($cmd, $output, $status);

        if (0 === $status && !empty($output)) {
            $info = $output[0];
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
        $cmd =
            'sysctl -n hw.model 2>/dev/null; ' .
            'printf "%s %s\n" "$(sysctl -n machdep.cpu.brand_string 2>/dev/null)" "$(sysctl -n hw.ncpu 2>/dev/null)"; ' .
            'system_profiler SPHardwareDataType 2>/dev/null | grep -E "Boot ROM Version|System Firmware Version" | awk -F: \'{print $2}\' | sed "s/^ *//"; ' .
            'networksetup -listallhardwareports 2>/dev/null | awk \'/Device:/{dev=$2} /Ethernet Address:/{print dev, $3}\'';

        exec($cmd, $hw_info, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Failed to execute hardware detection command!');
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

        unset($cmd, $hw_info, $status, $virtual, $key, $line, $keyword);
        return $hw_hash;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        exec('lsof -p ' . getmypid() . ' -Fn | awk "NR==5{print}" | sed "s/n\//\//"', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!');
        }

        if (empty($output)) {
            throw new \Exception(PHP_OS . ': PHP path NOT found!');
        }

        $php_path = $output[0];

        if (!is_file($php_path)) {
            throw new \Exception(PHP_OS . ': PHP path ERROR!');
        }

        unset($output, $status);
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
        $cmd   = 'which -a ' . escapeshellarg($program);

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
        $cmd  = 'lsof -i :' . $port;

        exec($cmd, $output, $status);

        if (0 === $status && !empty($output)) {
            for ($i = 1; $i < count($output); ++$i) {
                $line = $output[$i];
                if (false !== stripos($line, $state)) {
                    $parts = array_values(array_filter(explode(' ', $line), 'strlen'));
                    if (isset($parts[1]) && is_numeric($parts[1])) {
                        $pids[] = (int)$parts[1];
                    }
                }
            }
        }

        $pids = array_values(array_unique($pids));

        unset($port, $state, $cmd, $output, $status, $i, $line, $parts);
        return $pids;
    }

    /**
     * @param int $pid
     *
     * @return void
     */
    public function killPid(int $pid): void
    {
        exec('kill -9 ' . $pid . ' > /dev/null 2>&1');
    }

    /**
     * @param string $command
     *
     * @return string
     */
    public function buildBackgroundCmd(string $command): string
    {
        return 'screen ' . $command . ' > /dev/null 2>&1 &';
    }

    /**
     * @param string $command
     *
     * @return string
     */
    public function runWithProfile(string $command): string
    {
        return 'source /etc/profile' . "\n" . ' && ' . "\n" . $command;
    }
}