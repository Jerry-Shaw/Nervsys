<?php

/**
 * Log Extension
 *
 * Copyright 2016-2020 leo <2579186091@qq.com>
 * Copyright 2020 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Factory;
use Core\Lib\App;

/**
 * Class libLog
 *
 * @package Ext
 */
class libLog extends Factory
{
    private App    $app;
    private array  $log_pool = [];
    private string $log_file = 'default.log';

    /**
     * log constructor.
     *
     * @param string $log_name
     */
    public function __construct(string $log_name = 'default')
    {
        $this->app      = App::new();
        $this->log_file = date('Ymd') . '-' . $log_name . '.log';
        unset($log_name);
    }

    /**
     * Add logs
     *
     * @param $logs
     *
     * @return $this
     */
    public function add($logs): self
    {
        $this->log_pool[] = is_array($logs) || is_object($logs) ? json_encode($logs, JSON_PRETTY) : (string)$logs;

        unset($logs);
        return $this;
    }

    /**
     * Save logs
     */
    public function save(): void
    {
        static $file = [];

        $log = $this->app->log_path . DIRECTORY_SEPARATOR . $this->log_file;
        $key = hash('md5', $log);

        if (!isset($file[$key])) {
            $file[$key] = fopen($log, 'ab+');
            chmod($log, 0666);
        }

        fwrite($file[$key], implode(PHP_EOL, $this->log_pool) . PHP_EOL . PHP_EOL);
        $this->log_pool = [];
        unset($key, $log);
    }
}