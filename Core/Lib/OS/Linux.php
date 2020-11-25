<?php

/**
 * NS Linux controller library
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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
 * Class Linux
 *
 * @package Core\Lib\OS
 */
class Linux
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
            'lscpu | grep -E "Architecture|CPU|Thread|Core|Socket|Vendor|Model|Stepping|BogoMIPS|L1|L2|L3"',
            'cat /proc/cpuinfo | grep -E "processor|vendor|family|model|microcode|MHz|cache|physical|address"',
            'ip link show | grep link/ether', 'dmidecode -t memory'
        ];

        exec(implode(' && ', $queries), $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $output  = array_filter($output);
        $output  = array_unique($output);
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
        exec('readlink -f /proc/' . getmypid() . '/exe', $output, $status);

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $php_path = &$output[0];

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
        $this->os_cmd = 'nohup ' . $this->os_cmd . ' > /dev/null 2>&1 &';
        return $this;
    }

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    public function setEnvPath(): self
    {
        $this->os_cmd = 'source /etc/profile && ' . $this->os_cmd;
        return $this;
    }

    /**
     * Set command for proc_* functions
     *
     * @return $this
     */
    public function setForProc(): self
    {
        return $this;
    }
}