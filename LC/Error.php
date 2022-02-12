<?php

/**
 * Error handler library
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

use Nervsys\Lib\App;
use Nervsys\Lib\IOData;

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
    const ERROR_LEVEL = [
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
     * Error constructor
     */
    public function __construct()
    {
        register_shutdown_function([$this, 'shutdownHandler']);
        set_exception_handler([$this, 'exceptionHandler']);
        set_error_handler([$this, 'errorHandler']);
    }

    /**
     * @param int    $errno
     * @param string $errstr
     * @param string $errfile
     * @param int    $errline
     *
     * @return void
     * @throws \ErrorException
     */
    public function errorHandler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline);
    }

    /**
     * @return void
     * @throws \ErrorException
     */
    public function shutdownHandler(): void
    {
        $error = error_get_last();

        if (!is_null($error)) {
            throw new \ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
        }

        unset($error);
    }

    /**
     * @param \Throwable $throwable
     * @param bool       $stop_on_error
     * @param bool       $display_errors
     *
     * @return void
     * @throws \ReflectionException
     */
    public function exceptionHandler(\Throwable $throwable, bool $stop_on_error = true, bool $display_errors = true): void
    {
        $exception  = get_class($throwable);
        $error_code = $throwable->getCode();

        if (isset(self::ERROR_LEVEL[$error_code])) {
            $error_level = self::ERROR_LEVEL[$error_code];
        } elseif (false !== stripos($exception, 'error')) {
            $error_level = 'error';
            $error_code  = E_USER_ERROR;
        } else {
            $error_level = 'notice';
            $error_code  = E_USER_NOTICE;
        }

        $app    = App::new();
        $IOData = IOData::new();
        $logger = Logger::new();

        $message = $exception . ' caught in ' . $throwable->getFile()
            . ' on line ' . $throwable->getLine() . PHP_EOL
            . 'Message: ' . $throwable->getMessage();


        $context = [
            //Memory & Duration
            'Peak'     => round(memory_get_peak_usage() / 1048576, 4) . 'MB',
            'Memory'   => round(memory_get_usage() / 1048576, 4) . 'MB',
            'Duration' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 4) . 'ms',
            //Params & trace
            'Param'    => ['ip' => $app->client_ip, 'cmd' => $IOData->src_cmd, 'data' => $IOData->src_input, 'argv' => $IOData->src_argv],
            'Trace'    => $this->getTraceLog($throwable->getTrace())
        ];

        $display_errors && $app->core_debug && $logger->show($error_level, $message, $context);
        $logger->$error_level($message, $context);

        if ($stop_on_error) {
            http_response_code(500);

            if ('error' === $error_level) {
                exit(1);
            }
        }

        unset($throwable, $stop_on_error, $display_errors, $exception, $error_code, $error_level, $app, $IOData, $logger, $message, $context);
    }

    /**
     * @param array $trace
     *
     * @return array
     */
    private function getTraceLog(array $trace): array
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