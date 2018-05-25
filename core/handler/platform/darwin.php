<?php

/**
 * darwin handler
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

namespace core\handler\platform;

use core\handler\platform\lib\os;

class darwin implements os
{
    /**
     * Get PHP system path
     *
     * @throws \Exception
     */
    public static function sys_path(): string
    {
        exec('lsof -p ' . getmypid(), $output, $status);

        if (0 !== $status) {
            trigger_error('Darwin: Access denied!', E_USER_ERROR);
        }

        $node_name = null;
        foreach ($output as $item) {
            if ('/php' === strrchr($item, '/')) {
                $node_name = $item;
            }
        }

        if (is_null($node_name)) {
            trigger_error('Darwin: Process NOT fount!', E_USER_ERROR);
        }

        $node_array = explode(' ', $node_name);
        $env = array_pop($node_array);

        unset($output, $status, $node_name, $item, $node_array);
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

        $output = [];
        foreach ($queries as $query) {
            exec($query, $output, $status);

            if (0 !== $status) {
                trigger_error('Darwin: Access denied!', E_USER_ERROR);
            }
        }

        $output = array_filter($output);
        $output = array_unique($output);

        $hash = hash('sha256', json_encode($output));

        unset($queries, $output, $query, $status);
        return $hash;
    }

    /**
     * Build background command
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
     * Build proc_open command
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