<?php

/**
 * Linux controller library
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

class Linux
{
    /**
     * @return array
     */
    public function getIPv4(): array
    {
        exec("ip a | grep 'inet' | grep -v 'inet6' | grep -v '127*' | awk '{print $2}' | awk -F '/' '{print $1}'", $output, $status);

        $ip_v4 = 0 === $status ? array_filter($output) : [];

        unset($output, $status);
        return $ip_v4;
    }

    /**
     * @return array
     */
    public function getIPv6(): array
    {
        exec("ip a | grep 'inet6' | grep -v '::1' | awk '{print $2}' | awk -F '/' '{print $1}'", $output, $status);

        $ip_v6 = 0 === $status ? array_filter($output) : [];

        unset($output, $status);
        return $ip_v6;
    }

    /**
     * @return string
     */
    public function getBootInfo(): string
    {
        return file_get_contents('/proc/sys/kernel/random/boot_id') ?: '';
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        $cmd =
            'cat /sys/class/dmi/id/product_name 2>/dev/null; ' .
            'lscpu 2>/dev/null | awk -F: \'/Architecture|CPU\\(s\\)|Thread\\(s\\) per core|Core\\(s\\) per socket|Socket\\(s\\)|Vendor ID|Model name|Stepping|BogoMIPS|L1d cache|L1i cache|L2 cache|L3 cache/ {gsub(/^[ \t]+/,"",$2); printf "%s ",$2} END {print ""}\'; ' .
            'printf "%s %s %s %s\n" "$(cat /sys/class/dmi/id/board_vendor 2>/dev/null)" "$(cat /sys/class/dmi/id/board_name 2>/dev/null)" "$(cat /sys/class/dmi/id/board_serial 2>/dev/null)" "$(cat /sys/class/dmi/id/board_version 2>/dev/null)"; ' .
            'printf "%s %s\n" "$(cat /sys/class/dmi/id/bios_vendor 2>/dev/null)" "$(cat /sys/class/dmi/id/bios_version 2>/dev/null)"; ' .
            'for iface in /sys/class/net/*; do [ -e "$iface/address" ] || continue; mac=$(cat "$iface/address" 2>/dev/null); [ -n "$mac" ] && [ "$mac" != "00:00:00:00:00:00" ] || continue; name=$(basename "$iface"); type=$(cat "$iface/type" 2>/dev/null); pci=$(readlink -f "$iface/device" 2>/dev/null | sed "s/.*\\///"); echo "$name $mac $type $pci"; done';

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

        if ([] === $hw_info) {
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
        exec('readlink -f /proc/' . getmypid() . '/exe', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!');
        }

        if ([] === $output) {
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

        if (0 === $status && [] !== $output) {
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
        $cmd  = 'ss -tulpn | grep ":' . $port . ' "';

        exec($cmd, $output, $status);

        if (0 === $status && [] !== $output) {
            foreach ($output as $line) {
                if (false !== stripos($line, $state)) {
                    if (preg_match_all('/pid=(\d+)/', $line, $matches)) {
                        foreach ($matches[1] as $pid) {
                            $pids[] = (int)$pid;
                        }
                    }
                }
            }
        }

        $pids = array_values(array_unique($pids));

        unset($port, $state, $cmd, $output, $status, $line, $matches, $pid);
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
        return 'nohup ' . $command . ' > /dev/null 2>&1 &';
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