<?php

/**
 * NS System Error handler
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

namespace core\lib\stc;

use core\lib\std\io;
use core\lib\std\log;
use core\lib\std\pool;

/**
 * Class error
 *
 * @package core\lib\stc
 */
final class error
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
     * @throws \ErrorException
     */
    public static function shutdown_handler(): void
    {
        $error = error_get_last();

        if (is_null($error) || 'error' !== self::LEVEL[$error['type']]) {
            return;
        }

        throw new \ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
    }

    /**
     * Error handler
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     *
     * @throws \ErrorException
     */
    public static function error_handler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline);
    }

    /**
     * Exception handler
     *
     * @param \Throwable $throwable
     * @param bool       $stop_on_error
     * @param bool       $save_to_log
     */
    public static function exception_handler(\Throwable $throwable, bool $stop_on_error = true, bool $save_to_log = true): void
    {
        //Get exception name
        $exception = get_class($throwable);

        //Get error code
        $err_code = $throwable->getCode();

        //Get error level
        if (isset(self::LEVEL[$err_code])) {
            $err_lv = self::LEVEL[$err_code];
        } elseif (false !== stripos($exception, 'error')) {
            $err_lv   = 'error';
            $err_code = E_USER_ERROR;
        } else {
            $err_lv   = 'notice';
            $err_code = E_USER_NOTICE;
        }

        /** @var \core\lib\std\log $unit_log */
        $unit_log = factory::build(log::class);

        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = factory::build(pool::class);

        //Build message
        $message = $exception . ' caught in ' . $throwable->getFile()
            . ' on line ' . $throwable->getLine() . PHP_EOL
            . 'Message: ' . $throwable->getMessage();

        //Build context
        $context = [
            //Memory & Duration
            'Peak'     => round(memory_get_peak_usage() / 1048576, 4) . 'MB',
            'Memory'   => round(memory_get_usage() / 1048576, 4) . 'MB',
            'Duration' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 4) . 'ms',

            //Params & trace
            'Param'    => ['ip' => $unit_pool->ip, 'cmd' => $unit_pool->cmd] + $unit_pool->data,
            'Trace'    => self::get_trace_list($throwable->getTrace())
        ];

        //Save logs
        if ($save_to_log) {
            $unit_log->$err_lv($message, $context);
        }

        //Display logs
        $unit_log->display($err_lv, $message, $context);

        //Output & exit on error
        if ($stop_on_error && 0 < ($err_code & error_reporting())) {
            http_response_code(500);
            factory::build(io::class)->output($unit_pool);
            exit(0);
        }

        unset($throwable, $stop_on_error, $save_to_log, $exception, $err_code, $err_lv, $unit_log, $unit_pool, $message, $context);
    }

    /**
     * Get simple trace list
     *
     * @param array $trace
     *
     * @return array
     */
    private static function get_trace_list(array $trace): array
    {
        $list  = [];
        $trace = array_reverse($trace);

        foreach ($trace as $item) {
            $msg = (isset($item['class']) ? strtr($item['class'], '\\', '/') : '') . ($item['type'] ?? '') . $item['function'] . ' called';

            if (isset($item['file'])) {
                $msg .= ' in ' . strtr($item['file'], '\\', '/');
            }

            if (isset($item['line'])) {
                $msg .= ' on line ' . $item['line'];
            }

            $list[] = $msg;
        }

        unset($trace, $item, $msg);
        return $list;
    }
}