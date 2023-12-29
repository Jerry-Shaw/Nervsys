<?php

/**
 * Log Extension
 *
 * Copyright 2020-2023 秋水之冰 <27206617@qq.com>
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
    private string $log_file;

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
        $this->log_file = $log_name . '-' . date('Ymd') . '.log';
        unset($log_name);
    }

    /**
     * Add logs
     *
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
     * Save logs
     */
    private function save(string $logs): void
    {
        static $file = [];

        $path = $this->log_path . DIRECTORY_SEPARATOR . $this->log_file;
        $key  = hash('md5', $path);

        if (!isset($file[$key])) {
            $file[$key] = fopen($path, 'ab+');
            chmod($path, 0666);
        }

        fwrite($file[$key], $logs . PHP_EOL);
        unset($logs, $path, $key);
    }
}