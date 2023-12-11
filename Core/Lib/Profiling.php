<?php

/**
 * Profiling library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
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

namespace Nervsys\Core\Lib;

use Nervsys\Core\Factory;

class Profiling extends Factory
{
    public int $timer_threshold  = -1;
    public int $memory_threshold = -1;

    public array $profiling_data = [];

    /**
     * @param int $memory_bytes
     * @param int $time_milliseconds
     *
     * @return $this
     */
    public function setThresholds(int $memory_bytes, int $time_milliseconds): self
    {
        $this->memory_threshold = &$memory_bytes;
        $this->timer_threshold  = &$time_milliseconds;

        unset($memory_bytes, $time_milliseconds);
        return $this;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function start(string $name): void
    {
        $this->profiling_data[$name][] = [
            (int)(microtime(true) * 1000),
            memory_get_usage()
        ];

        unset($name);
    }

    /**
     * @param string $name
     *
     * @return void
     * @throws \ReflectionException
     */
    public function end(string $name): void
    {
        $data = array_pop($this->profiling_data[$name]);

        if (0 > $this->memory_threshold && 0 > $this->timer_threshold) {
            unset($name, $data);
            return;
        }

        if (is_null($data)) {
            unset($name, $data);
            return;
        }

        $mem_usage = memory_get_usage() - $data[1];
        $time_cost = (int)(microtime(true) * 1000) - $data[0];

        if ($mem_usage > $this->memory_threshold || $time_cost > $this->timer_threshold) {
            $log_file = App::new()->log_path . DIRECTORY_SEPARATOR . ('profiling-' . date('Ymd')) . '.log';

            $log_data = date('Y-m-d H:i:s') . "\r\n";
            $log_data .= 'Name: ' . $name . "\r\n";
            $log_data .= 'Time: ' . $time_cost . "ms\r\n";
            $log_data .= 'Memory: ' . ($mem_usage / 1024 / 1024) . "MB\r\n\r\n";

            $handle = fopen($log_file, 'ab+');

            fwrite($handle, $log_data);
            fclose($handle);

            unset($log_file, $log_data, $handle);
        }

        unset($name, $data, $mem_usage, $time_cost);
    }
}