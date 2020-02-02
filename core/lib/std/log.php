<?php

/**
 * NS System Log controller
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core\lib\std;

use core\lib\stc\factory;

/**
 * Class log
 *
 * @package core\lib\std
 */
final class log
{
    /** @var \core\lib\std\pool $unit_pool */
    private $unit_pool;

    //Log save path
    private $log_path = '';

    /**
     * cli constructor.
     */
    public function __construct()
    {
        //Build pool
        $this->unit_pool = factory::build(pool::class);

        //Set log path
        $this->log_path = $this->unit_pool->conf['log']['save_path'];

        //Check log path
        if (!is_dir($this->log_path)) {
            mkdir($this->log_path, 0777, true);
            chmod($this->log_path, 0777);
        }
    }

    /**
     * Log emergency
     *
     * @param string $message
     * @param array  $context
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Log alert
     *
     * @param string $message
     * @param array  $context
     */
    public function alert(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Log critical
     *
     * @param string $message
     * @param array  $context
     */
    public function critical(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Log error
     *
     * @param string $message
     * @param array  $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Log warning
     *
     * @param string $message
     * @param array  $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Log notice
     *
     * @param string $message
     * @param array  $context
     */
    public function notice(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Log info
     *
     * @param string $message
     * @param array  $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Log debug
     *
     * @param string $message
     * @param array  $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->save(__FUNCTION__, $message, $context);
        unset($message, $context);
    }

    /**
     * Display log
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     */
    public function display(string $level, string $message, array $context): void
    {
        if (true === $this->unit_pool->conf['log']['display']) {
            $this->unit_pool->log .= $this->format($level, $message, $context);
        }

        unset($level, $message, $context);
    }

    /**
     * Save log
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     */
    private function save(string $level, string $message, array $context): void
    {
        if (true !== $this->unit_pool->conf['log'][$level]) {
            return;
        }

        $key = date('Ymd') . '-' . $level;
        $log = $this->log_path . DIRECTORY_SEPARATOR . $key . '.log';

        static $file = [];

        if (!isset($file[$key])) {
            $file[$key] = fopen($log, 'ab+');
            chmod($log, 0666);
        }

        fwrite($file[$key], $this->format($level, $message, $context));
        unset($level, $message, $context, $key, $log);
    }

    /**
     * Format log content
     *
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
        $log .= json_encode($context, JSON_PRETTY) . PHP_EOL . PHP_EOL;

        unset($level, $message, $context);
        return $log;
    }
}