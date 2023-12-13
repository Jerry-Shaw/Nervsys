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
     * @param string $profile_name
     *
     * @return void
     */
    public function start(string $profile_name): void
    {
        $this->profiling_data[$profile_name][] = [
            memory_get_usage(),
            microtime(true)
        ];

        unset($profile_name);
    }

    /**
     * @param string $profile_name
     * @param bool   $force_save
     * @param bool   $with_input_data
     * @param string $log_file_name
     *
     * @return void
     * @throws \ReflectionException
     */
    public function end(string $profile_name, bool $force_save = false, bool $with_input_data = false, string $log_file_name = 'profiling'): void
    {
        $profile_data = array_pop($this->profiling_data[$profile_name]);

        if (is_null($profile_data) || (0 > $this->memory_threshold && 0 > $this->timer_threshold)) {
            unset($profile_name, $force_save, $with_input_data, $log_file_name, $profile_data);
            return;
        }

        $mem_usage = memory_get_usage() - $profile_data[0];
        $time_cost = round((microtime(true) - $profile_data[1]) * 1000, 2);

        if ($force_save || $mem_usage > $this->memory_threshold || $time_cost > $this->timer_threshold) {
            $log_file = App::new()->log_path . DIRECTORY_SEPARATOR . ($log_file_name . '-' . date('Ymd')) . '.log';

            $log_data = date('Y-m-d H:i:s') . "\r\n";
            $log_data .= 'Name: ' . $profile_name . "\r\n";
            $log_data .= 'Time: ' . $time_cost . "ms\r\n";
            $log_data .= 'Memory: ' . (round($mem_usage / 1048576, 2)) . "MB\r\n";

            if ($with_input_data) {
                $log_data .= 'Params: ' . json_encode(IOData::new()->src_input, JSON_PRETTY) . "\r\n";
            }

            $handle = fopen($log_file, 'ab+');

            fwrite($handle, $log_data . "\r\n");
            fclose($handle);

            unset($log_file, $log_data, $handle);
        }

        unset($profile_name, $force_save, $with_input_data, $log_file_name, $profile_data, $mem_usage, $time_cost);
    }
}