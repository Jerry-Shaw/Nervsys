<?php

/**
 * Error handler
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
     * Shutdown handler
     *
     * @throws \Exception
     */
    public static function shutdown_handler(): void
    {
        $error = error_get_last();

        if (is_null($error) || 'error' !== self::LEVEL[$error['type']]) {
            return;
        }

        self::exception_handler(new \ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']));
        unset($error);
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
        if (0 < (parent::$err_lv & $errno)) {
            $errno = E_USER_ERROR;
        }

        self::exception_handler(new \ErrorException($errstr, $errno, $errno, $errfile, $errline));
        unset($errno, $errstr, $errfile, $errline);
    }

    /**
     * Exception handler
     *
     * @param $throwable
     */
    public static function exception_handler(\Throwable $throwable): void
    {
        //Get exception name
        $exception = get_class($throwable);

        //Get error level
        $level = self::LEVEL[$throwable->getCode()] ?? 'info';

        //Build message
        $message = $exception . ' caught in ' . $throwable->getFile()
            . ' on line ' . $throwable->getLine() . PHP_EOL
            . 'Message: ' . $throwable->getMessage();

        //Build context
        $context = [
            'Peak: ' . round(memory_get_peak_usage(true) / 1048576, 4) . 'MB',
            'Memory: ' . round(memory_get_usage(true) / 1048576, 4) . 'MB',
            'Duration: ' . round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 4) . 'ms' . PHP_EOL,
            //Append param & trace
            'Param: ' . PHP_EOL . json_encode(['cmd' => parent::$cmd] + parent::$data, 4034) . PHP_EOL,
            'Trace: ' . PHP_EOL . $throwable->getTraceAsString() . PHP_EOL
        ];

        //Parse backtrace
        $backtrace = debug_backtrace();
        $backtrace = array_reverse($backtrace);

        //Check last trace
        $last_node = end($backtrace);

        //Remove error handler
        if ($last_node['class'] === __CLASS__) {
            array_pop($backtrace);
        }

        //Simplify backtrace records
        $trace_list = [];
        foreach ($backtrace as $item) {
            $msg = '"' . ($item['class'] ?? '') . ($item['type'] ?? '') . $item['function'] . '" called';

            if (isset($item['file'])) {
                $msg .= ' in "' . $item['file'] . '"';
            }

            if (isset($item['line'])) {
                $msg .= ' on line ' . $item['line'];
            }

            $trace_list[] = $msg;
        }

        //Append backtrace records
        $context[] = 'Backtrace: ' . PHP_EOL . implode(PHP_EOL, $trace_list);
        unset($throwable, $exception, $backtrace, $last_node, $trace_list, $item, $msg);

        //Process logs
        log::$level($message, $context);
        log::display($level, $message, $context);

        //Stop on error
        'error' === $level && parent::stop();
        unset($level, $message, $context);
    }
}