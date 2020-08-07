<?php

/**
 * NS Error library
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

namespace Core\Lib;

use Core\Factory;

/**
 * Class Error
 *
 * @package Core\Lib
 */
class Error extends Factory
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