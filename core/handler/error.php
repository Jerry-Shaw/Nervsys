<?php

/**
 * Error Handler
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

class error
{
    /*
     * Error report level
     *
     * 0: Disable
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
    public static $level = 0;

    //Error levels
    const levels = [
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
     * Start error handler
     */
    public static function start(): void
    {
        set_error_handler([__CLASS__, 'error_handler'], E_ALL);
    }

    /**
     * Error handler
     *
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     *
     * @return bool
     */
    public static function error_handler(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        $level = self::levels[$errno] ?? 'notice';
        $error = 'error' !== $level && self::$level < $errno;

        logger::log($level, $errstr, ['Caught in ' . $errfile . ' on line ' . $errline]);

        unset($errno, $errstr, $errfile, $errline, $level);
        return $error;
    }
}