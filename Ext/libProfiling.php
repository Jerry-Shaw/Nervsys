<?php

/**
 * ProFiling Extension
 *
 * Copyright 2023 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Lib\Profiling;

class libProfiling extends Profiling
{
    /**
     * @param string $profile_name
     * @param string $memory_limit
     * @param string $time_limit
     *
     * @return void
     */
    public function watch(string $profile_name, string $memory_limit = '8M', string $time_limit = '80ms'): void
    {
        $this->setThresholds($this->memoryToBytes($memory_limit), $this->timeToMilliseconds($time_limit));
        $this->start($profile_name);

        unset($profile_name, $memory_limit, $time_limit);
    }

    /**
     * @param string $memory_limit
     *
     * @return int
     */
    private function memoryToBytes(string $memory_limit): int
    {
        $memory_limit = strtolower($memory_limit);
        $memory_limit = str_replace('b', '', $memory_limit);
        $memory_limit = (float)$memory_limit;

        switch (substr($memory_limit, -1, 1)) {
            case 'k':
                $memory_limit *= 1024;
                break;
            case 'm':
                $memory_limit *= 1048576;
                break;
            case 'g':
                $memory_limit *= 1073741824;
                break;
            default:
                break;
        }

        return (int)$memory_limit;
    }

    /**
     * @param string $time_limit
     *
     * @return int
     */
    private function timeToMilliseconds(string $time_limit): int
    {
        $time_in_ms = (float)$time_limit;

        switch (str_replace((string)$time_in_ms, '', $time_limit)) {
            case 's':
                $time_in_ms *= 1000;
                break;
            case 'm':
                $time_in_ms *= 60000;
                break;
            case 'h':
                $time_in_ms *= 3600000;
                break;
            case 'd':
                $time_in_ms *= 86400000;
                break;
            default:
                break;
        }

        unset($time_limit);
        return (int)$time_in_ms;
    }
}