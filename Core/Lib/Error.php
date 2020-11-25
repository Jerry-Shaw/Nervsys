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

    public array $error_handler;
    public array $shutdown_handler;
    public array $exception_handler;

    /**
     * Error constructor.
     */
    public function __construct()
    {
        $this->error_handler     = [$this, 'errorHandler'];
        $this->shutdown_handler  = [$this, 'shutdownHandler'];
        $this->exception_handler = [$this, 'exceptionHandler'];
    }

    /**
     * Set custom ErrorHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setErrorHandler(object $handler_object, string $handler_method): self
    {
        $this->error_handler = [$handler_object, $handler_method];

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom ShutdownHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setShutdownHandler(object $handler_object, string $handler_method): self
    {
        $this->shutdown_handler = [$handler_object, $handler_method];

        unset($handler_object, $handler_method);
        return $this;
    }

    /**
     * Set custom ExceptionHandler
     *
     * @param object $handler_object
     * @param string $handler_method
     *
     * @return $this
     */
    public function setExceptionHandler(object $handler_object, string $handler_method): self
    {
        $this->exception_handler = [$handler_object, $handler_method];

        unset($handler_object, $handler_method);
        return $this;
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
    public function errorHandler(int $errno, string $errstr, string $errfile, int $errline): void
    {
        throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline);
    }

    /**
     * Shutdown handler
     *
     * @throws \ErrorException
     */
    public function shutdownHandler(): void
    {
        $error = error_get_last();

        if (is_null($error) || 'error' !== self::LEVEL[$error['type']]) {
            return;
        }

        throw new \ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
    }

    /**
     * Exception handler
     *
     * @param \Throwable $throwable
     * @param bool       $stop_on_error
     */
    public function exceptionHandler(\Throwable $throwable, bool $stop_on_error = true): void
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

        //Init App, IOUnit, Logger
        $app     = App::new();
        $io_unit = IOUnit::new();
        $logger  = Logger::new();

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
            'Param'    => ['ip' => $app->client_ip, 'cmd' => $io_unit->src_cmd, 'data' => $io_unit->src_input, 'argv' => $io_unit->src_argv],
            'Trace'    => $this->getTraceHist($throwable->getTrace())
        ];

        //Show & Save logs
        $logger->show($err_lv, $message, $context);
        $logger->$err_lv($message, $context);

        //Exit on error
        if ($stop_on_error && 'error' === $err_lv) {
            http_response_code(500);
            exit();
        }

        unset($throwable, $stop_on_error, $exception, $err_code, $err_lv, $app, $io_unit, $logger, $message, $context);
    }

    /**
     * Get simple trace history
     *
     * @param array $trace
     *
     * @return array
     */
    private function getTraceHist(array $trace): array
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