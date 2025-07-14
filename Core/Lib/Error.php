<?php

/**
 * Error handler library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core\Lib;

use Nervsys\Core\Factory;

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
        E_DEPRECATED        => 'notice',
        E_USER_DEPRECATED   => 'notice'
    ];

    public array $custom_handler = [];

    /**
     * @param App    $app
     * @param string $log_file
     * @param string $log_content
     *
     * @return void
     */
    public function saveLog(App $app, string $log_file, string $log_content): void
    {
        $log_handle = fopen($app->log_path . DIRECTORY_SEPARATOR . $log_file, 'ab');

        fwrite($log_handle, $log_content);
        fclose($log_handle);

        unset($app, $log_file, $log_content, $log_handle);
    }

    /**
     * @param string $err_lv
     * @param string $message
     * @param array  $context
     *
     * @return string
     */
    public function formatLog(string $err_lv, string $message, array $context = []): string
    {
        $log = date('Y-m-d H:i:s') . "\r\n";

        $log .= ucfirst($err_lv) . ': ' . $message . "\r\n";
        $log .= !empty($context) ? json_encode($context, JSON_PRETTY) . "\r\n\r\n" : "\r\n";

        unset($err_lv, $message, $context);
        return $log;
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
     * @param bool       $report_error
     *
     * @return void
     * @throws \ReflectionException
     */
    public function exceptionHandler(\Throwable $throwable, bool $report_error = true, bool $stop_on_error = true): void
    {
        $app    = App::new();
        $IOData = IOData::new();

        $exception  = get_class($throwable);
        $error_code = $throwable->getCode();

        if (isset(self::ERROR_LEVEL[$error_code])) {
            $err_lv = self::ERROR_LEVEL[$error_code];
        } elseif (false !== stripos($exception, 'error')) {
            $err_lv     = 'error';
            $error_code = E_USER_ERROR;
        } else {
            $err_lv     = 'notice';
            $error_code = E_USER_NOTICE;
        }

        $message = $exception . ' caught in ' . $throwable->getFile()
            . ' on line ' . $throwable->getLine() . PHP_EOL
            . 'Message: ' . $throwable->getMessage();

        $context = [
            //Memory & Duration
            'Peak'     => round(memory_get_peak_usage() / 1048576, 2) . 'MB',
            'Memory'   => round(memory_get_usage() / 1048576, 2) . 'MB',
            'Duration' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms',
            //Params & trace
            'Params'   => [
                'ip'   => $app->client_ip,
                'ua'   => $app->user_agent,
                'cmd'  => $IOData->src_cmd,
                'lang' => $app->user_lang,
                'data' => $IOData->src_input,
                'argv' => $IOData->src_argv
            ],
            'Trace'    => $this->getTraceLog($throwable->getTrace())
        ];

        $this->saveLog($app, 'error-' . date('Ymd') . '.log', $this->formatLog($err_lv, $message, $context));

        foreach ($this->custom_handler as $handler) {
            if (is_callable($handler)) {
                call_user_func($handler, $app, $IOData, $throwable, $report_error);
            }
        }

        if ($report_error) {
            !headers_sent() && http_response_code(500);

            if ($app->debug_mode) {
                $this->showLog($err_lv, $message, $context);
            }
        }

        if ($stop_on_error) {
            exit(1);
        }

        unset($throwable, $report_error, $stop_on_error, $app, $IOData, $exception, $error_code, $err_lv, $message, $context);
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

    /**
     * @param string $err_lv
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    private function showLog(string $err_lv, string $message, array $context = []): void
    {
        echo $this->formatLog($err_lv, $message, $context);
        unset($err_lv, $message, $context);
    }
}