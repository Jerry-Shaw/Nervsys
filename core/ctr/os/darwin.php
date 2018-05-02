<?php

/**
 * darwin Platform Module
 *
 * Copyright 2018 shawn <csk_shawn@163.com>
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

namespace core\ctr\os;

use core\ctr\os\lib\cmd;

class darwin implements cmd
{
    /**
     * Get PHP path
     *
     * @throws \Exception
     */
    public static function php_env(): string
    {
        //Execute system command
        exec('lsof -p ' . getmypid(), $output, $status);
        if (0 !== $status) throw new \Exception('Darwin: Access denied!');

        $node_name = null;
        foreach ($output as $key => $command) {
            if (substr($command, -4, 4) === '/php') $node_name = $command;
        }

        if (!$node_name) throw new \Exception('Darwin: Not Fount PHP Process!');

        $node_array = explode(' ', $node_name);
        //Get executable path
        $env = (array_pop($node_array));

        unset($output, $status, $node_name, $node_array);
        return $env;
    }

    /**
     * Get system hash
     *
     * @throws \Exception
     */
    public static function sys_hash(): string
    {
        $queries = [
            'sysctl -n machdep.cpu.brand_string',
            'sysctl -n machdep.cpu.core_count',
            'sysctl -n machdep.cpu.thread_count',
            'system_profiler SPDisplaysDataType SPMemoryDataType SPStorageDataType | grep "Graphics/Displays:\|Chipset Model:\|VRAM (Total):\|Resolution:\|Memory Slots:\|Size:\|Speed:\|Storage:\|Media Name:\|Medium Type:"'
        ];

        //Run command
        $output = [];
        foreach ($queries as $query) {
            exec($query, $output, $status);
            if (0 !== $status) throw new \Exception('Darwin: Access denied!');
        }

        $output = array_filter($output);
        $output = array_unique($output);

        $hash = hash('sha256', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hash;
    }

    /**
     * Build command for background process
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function cmd_bg(string $cmd): string
    {
        return 'open "' . $cmd . '" > /dev/null 2>/dev/null &';
    }

    /**
     * Build command for proc_open
     *
     * @param string $cmd
     *
     * @return string
     */
    public static function cmd_proc(string $cmd): string
    {
        return $cmd;
    }
}