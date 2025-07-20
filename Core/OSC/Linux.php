<?php

/**
 * Linux controller library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        $queries = [
            'lscpu | grep -E "Architecture|CPU|Thread|Core|Socket|Vendor|Model|Stepping|BogoMIPS|L1|L2|L3"',
            'ip link show | awk \'{if($0~/^[0-9]+:/) printf("%s",$2); else print $2}\''
        ];

        exec(implode(' && ', $queries), $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $hw_info = '';

        foreach ($output as $value) {
            if (str_contains($value, '*-') || !str_contains($value, ':')) {
                continue;
            }

            [$k, $v] = explode(':', $value, 2);
            $hw_info .= trim($k) . ':' . trim($v) . PHP_EOL;
        }

        $hw_hash = hash('md5', trim($hw_info));

        unset($queries, $output, $status, $hw_info, $value, $k, $v);
        return $hw_hash;
    }

    /**
     * @param int $pid
     *
     * @return void
     */
    public function killProc(int $pid): void
    {
        exec('kill -9 ' . $pid);
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