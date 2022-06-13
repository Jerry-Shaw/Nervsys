<?php

/**
 * Linux controller library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\LC\OSC;

class Linux
{
    public string $os_cmd;

    /**
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        $queries = [
            'lscpu | grep -E "Architecture|CPU|Thread|Core|Socket|Vendor|Model|Stepping|BogoMIPS|L1|L2|L3"',
            'ip link show | awk \'{if($0~/^[0-9]+:/) printf("%s",$2); else print $2}\'',
            'lshw -C "memory,cpu,pci,isa,display,ide,bridge"',
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
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        exec('readlink -f /proc/' . getmypid() . '/exe', $output, $status);

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
     * @return $this
     */
    public function setAsBg(): self
    {
        $this->os_cmd = 'nohup ' . $this->os_cmd . ' > /dev/null 2>&1 &';

        return $this;
    }

    /**
     * @return $this
     */
    public function setEnvPath(): self
    {
        $this->os_cmd = 'source /etc/profile && ' . $this->os_cmd;

        return $this;
    }
}