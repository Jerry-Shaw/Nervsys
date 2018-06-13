<?php

/**
 * Log Helper
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

namespace core\helper;

use core\pool\configure;

class log
{
    //Log show
    public static $show = false;

    //Log path
    public static $path = ROOT . 'temp' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;

    /**
     * Log emergency
     *
     * @param string $message
     * @param array  $context
     */
    public static function emergency(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Log alert
     *
     * @param string $message
     * @param array  $context
     */
    public static function alert(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Log critical
     *
     * @param string $message
     * @param array  $context
     */
    public static function critical(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Log error
     *
     * @param string $message
     * @param array  $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Log warning
     *
     * @param string $message
     * @param array  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Log notice
     *
     * @param string $message
     * @param array  $context
     */
    public static function notice(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Log info
     *
     * @param string $message
     * @param array  $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Log debug
     *
     * @param string $message
     * @param array  $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::save(__FUNCTION__, $message, $context);

        unset($message, $context);
    }

    /**
     * Save logs
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     */
    private static function save(string $level, string $message, array $context): void
    {
        //Check configure
        if (!isset(configure::$log[$level]) || 0 === (int)configure::$log[$level]) {
            return;
        }

        //Check log path
        if (false === realpath(self::$path) && !mkdir(self::$path, 0776, true) && !chmod(self::$path, 0776)) {
            self::show('warning', 'Log path: "' . self::$path . '" NOT exist!', ['Access denied!', 'Please check permissions!']);
            self::show($level, $message, $context);

            return;
        }

        //Add datetime & log message & empty line
        array_unshift($context, date('Y-m-d H:i:s'), ucfirst($level) . ': ' . $message);
        $context[] = '';

        //Generate log file name
        $file = self::$path . $level . '-' . date('Ymd') . '.log';

        //Write log
        foreach ($context as $value) {
            file_put_contents($file, (is_string($value) ? $value : json_encode($value, 4034)) . PHP_EOL, FILE_APPEND);
        }

        unset($level, $message, $context, $file, $value);
    }

    /**
     * Show logs
     *
     * @param string $level
     * @param string $message
     * @param array  $context
     */
    public static function show(string $level, string $message, array $context): void
    {
        if (self::$show) {
            echo ucfirst($level) . ': ' . $message . PHP_EOL . PHP_EOL;

            foreach ($context as $value) {
                echo (is_string($value) ? $value : json_encode($value, 4034)) . PHP_EOL;
            }

            echo PHP_EOL . PHP_EOL;
            unset($value);
        }

        unset($level, $message, $context);
    }
}