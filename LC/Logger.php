<?php

/**
 * Logger library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\LC;

use Nervsys\LC\Lib\App;

class Logger extends Factory
{
    public string $log_path;

    /**
     * Logger constructor
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->log_path = App::new()->log_path;
    }

    /**
     * @param string $err_lv
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function show(string $err_lv, string $message, array $context = []): void
    {
        echo $this->format($err_lv, $message, $context);
        unset($err_lv, $message, $context);
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return void
     */
    public function __call(string $name, array $arguments): void
    {
        $log_key  = date('Ymd') . '-' . $name;
        $log_path = $this->log_path . DIRECTORY_SEPARATOR . $log_key . '.log';

        static $file_handle = [];

        if (!isset($file_handle[$log_key])) {
            $file_handle[$log_key] = fopen($log_path, 'ab+');
            chmod($log_path, 0666);
        }

        fwrite($file_handle[$log_key], $this->format($name, $arguments[0], $arguments[1] ?? []));
        unset($name, $arguments, $log_key, $log_path);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    private function format(string $level, string $message, array $context): string
    {
        $log = date('Y-m-d H:i:s') . PHP_EOL;

        $log .= ucfirst($level) . ': ' . $message . PHP_EOL;
        $log .= !empty($context) ? json_encode($context, JSON_PRETTY) . PHP_EOL . PHP_EOL : PHP_EOL;

        unset($level, $message, $context);
        return $log;
    }
}