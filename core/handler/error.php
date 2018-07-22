<?php

/**
 * Error handler
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

namespace core\handler;

use core\system;

use core\helper\log;

class error extends system
{
    /*
     * 1: E_ERROR
     * 2: E_WARNING
     * 4: E_PARSE
     * 8: E_NOTICE
     * 16: E_CORE_ERROR
     * 32: E_CORE_WARNING
     * 64: E_COMPILE_ERROR
     * 128: E_COMPILE_WARNING
     * 256: E_USER_ERROR
     * 512: E_USER_WARNING
     * 1024: E_USER_NOTICE
     * 2048: E_STRICT
     * 4096: E_RECOVERABLE_ERROR
     * 8192: E_DEPRECATED
     * 16384: E_USER_DEPRECATED
     * 32767: E_ALL
     */
    const LEVEL = [
        //Error level
        E_ERROR             => 'error',
        E_PARSE             => 'error',
        E_CORE_ERROR        => 'error',
        E_COMPILE_ERROR     => 'error',
        E_USER_ERROR        => 'error',

        //Warning level
        E_WARNING           => 'warning',
        E_CORE_WARNING      => 'warning',
        E_COMPILE_WARNING   => 'warning',
        E_USER_WARNING      => 'warning',
        E_RECOVERABLE_ERROR => 'warning',

        //Notice level
        E_NOTICE            => 'notice',
        E_USER_NOTICE       => 'notice',
        E_STRICT            => 'notice',
        E_DEPRECATED        => 'notice',
        E_USER_DEPRECATED   => 'notice'
    ];

    /**
     * Track error & exception
     */
    public static function track(): void
    {
        register_shutdown_function([__CLASS__, 'shutdown_handler']);
        set_exception_handler([__CLASS__, 'exception_handler']);
        set_error_handler([__CLASS__, 'error_handler']);
    }

    /**
     * Error handler
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     *
     * @throws \Exception
     */
    public static function error_handler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        self::exception_handler(new \ErrorException($errstr, $errno, $errno, $errfile, $errline));
        unset($errno, $errstr, $errfile, $errline);
    }

    /**
     * Shutdown handler
     *
     * @throws \Exception
     */
    public static function shutdown_handler(): void
    {
        if (!is_null($error = error_get_last()) && 'error' === self::LEVEL[$error['type']]) {
            self::exception_handler(new \ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']));
            unset($error);
        }
    }

    /**
     * Exception handler
     *
     * @param $throwable
     */
    public static function exception_handler(\Throwable $throwable): void
    {
        $level = self::LEVEL[$throwable->getCode()] ?? (false !== stripos(get_class($throwable), 'error') ? 'error' : 'debug');

        $message = ucfirst($level) . ' caught in ' . $throwable->getFile()
            . ' on line ' . $throwable->getLine() . PHP_EOL
            . 'Message: ' . $throwable->getMessage();

        $context = [
            'Peak: ' . round(memory_get_peak_usage(true) / 1048576, 4) . 'MB',
            'Memory: ' . round(memory_get_usage(true) / 1048576, 4) . 'MB',
            'Duration: ' . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 4) . 'ms',
            'Trace: ' . PHP_EOL . $throwable->getTraceAsString()
        ];

        log::$level($message, $context);
        log::show($level, $message, $context);

        unset($throwable, $message, $context);

        if ('error' === $level) {
            parent::stop();
        }

        unset($level);
    }
}