<?php

/**
 * linux handler
 *
 * Copyright 2016-2019 liu <2579186091@qq.com>
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

namespace core\lib\os\unit;

use core\lib\os\unit;

/**
 * Class linux
 *
 * @package core\lib\os\unit
 */
final class linux extends unit
{
    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public function get_hw_hash(): string
    {
        $queries = [
            'lscpu | grep -E "Architecture|CPU|Thread|Core|Socket|Vendor|Model|Stepping|BogoMIPS|L1|L2|L3"',
            'cat /proc/cpuinfo | grep -E "processor|vendor|family|model|microcode|MHz|cache|physical|address"',
            'dmidecode -t memory',
            'mac'  => 'ip link show | grep link/ether',
            'disk' => 'lsblk'
        ];

        //Add to OS command
        $this->os_cmd = implode(' && ', $queries);

        //Execute command
        $status = 0;
        $output = $this->execute($status);

        //Reset command
        $this->os_cmd = '';

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        $output = array_filter($output);
        $output = array_unique($output);

        $hash = hash('md5', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hash;
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public function get_php_path(): string
    {
        //Add to OS command
        $this->os_cmd = 'readlink -f /proc/' . getmypid() . '/exe';

        //Execute command
        $status = 0;
        $output = $this->execute($status);

        //Reset command
        $this->os_cmd = '';

        if (0 !== $status) {
            throw new \Exception(PHP_OS . ': Access denied!', E_USER_ERROR);
        }

        //Get path value
        $env = &$output[0];

        unset($output, $status);
        return $env;
    }

    /**
     * Set as background command
     *
     * @return $this
     */
    public function bg(): object
    {
        $this->os_cmd = 'nohup ' . $this->os_cmd . ' > /dev/null 2>&1 &';
        return $this;
    }

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    public function env(): object
    {
        $this->os_cmd = 'source /etc/profile && ' . $this->os_cmd;
        return $this;
    }

    /**
     * Set command for proc_* functions
     *
     * @return $this
     */
    public function proc(): object
    {
        return $this;
    }
}