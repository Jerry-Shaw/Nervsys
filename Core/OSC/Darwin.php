<?php

/**
 * Darwin controller library
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
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        exec('system_profiler SPHardwareDataType SPMemoryDataType SPPCIDataType', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $hw_info = '';

        foreach ($output as $value) {
            $value = str_replace(' ', '', $value);
            $value = trim($value);

            if ('' !== $value) {
                $hw_info .= $value;
            }
        }

        $hw_hash = hash('md5', $hw_info);

        unset($output, $status, $hw_info, $value);
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
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        if (empty($output)) {
            throw new \Exception(PHP_OS . ': PHP path NOT found!', E_USER_ERROR);
        }

        $php_path = &$output[0];

        if (!is_file($php_path)) {
            throw new \Exception(PHP_OS . ': PHP path ERROR!', E_USER_ERROR);
        }

        unset($output, $status);
        return $php_path;
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