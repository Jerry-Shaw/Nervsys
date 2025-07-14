<?php

/**
 * Log Extension
 *
 * Copyright 2020-2025 秋水之冰 <27206617@qq.com>
 * Copyright 2020 leo <2579186091@qq.com>
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

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\App;

class libLog extends Factory
{
    private string $log_path;
    private string $log_name;

    /**
     * libLog constructor
     *
     * @param string $log_name
     *
     * @throws \ReflectionException
     */
    public function __construct(string $log_name = 'default')
    {
        $this->log_path = App::new()->log_path;
        $this->log_name = $log_name;

        unset($log_name);
    }

    /**
     * @return $this
     */
    public function add(): self
    {
        $logs   = '';
        $params = func_get_args();

        foreach ($params as $param) {
            $logs .= (is_array($param) || is_object($param))
                ? json_encode($param, JSON_PRETTY) . PHP_EOL
                : (string)$param . PHP_EOL;
        }

        $this->save($logs);

        unset($logs, $params, $param);
        return $this;
    }

    /**
     * @param string $logs
     *
     * @return void
     */
    private function save(string $logs): void
    {
        static $handle = [];

        $date = date('Ymd');

        if (!isset($handle[$this->log_name][$date])) {
            if (isset($handle[$this->log_name]) && !empty($handle[$this->log_name])) {
                foreach ($handle[$this->log_name] as $log_handler) {
                    fclose($log_handler);
                }

                unset($log_handler);
            }

            $log_path = $this->log_path . DIRECTORY_SEPARATOR . $this->log_name . '-' . $date . '.log';

            $handle[$this->log_name] = [$date => fopen($log_path, 'ab')];
        }

        fwrite($handle[$this->log_name][$date], $logs . "\r\n");

        unset($logs, $date, $log_path);
    }
}