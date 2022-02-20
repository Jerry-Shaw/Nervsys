<?php

/**
 * Execute module
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

class Caller extends Factory
{
    public Error $error;

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->error = Error::new();
    }

    /**
     * @param array $cmd_data
     * @param array $input_data
     *
     * @return array
     * @throws \ReflectionException
     */
    public function runCgi(array $cmd_data, array $input_data): array
    {
        $result = [];

        try {
            $fn_result = call_user_func_array(
                [
                    !Reflect::getMethod($cmd_data[0], $cmd_data[1])->isStatic()
                        ? self::getObj($cmd_data[0], $input_data)
                        : $cmd_data[0],
                    $cmd_data[1]
                ],
                $input_data
            );

            if (!is_null($fn_result)) {
                $result[$cmd_data[2] ?? strtr($cmd_data[0], '\\', '/') . '/' . $cmd_data[1]] = &$fn_result;
            }

            unset($fn_result);
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable, false);
            unset($throwable);
        }

        unset($cmd_data, $input_data);
        return $result;
    }

    /**
     * @param array  $cmd_pair
     * @param array  $cmd_argv
     * @param string $cwd_path
     * @param bool   $realtime_debug
     *
     * @return array
     * @throws \ReflectionException
     */
    public function runCli(array $cmd_pair, array $cmd_argv = [], string $cwd_path = '', bool $realtime_debug = false): array
    {
        $result = [];

        try {
            array_unshift($cmd_argv, $cmd_pair[1]);

            $proc = proc_open(
                $cmd_argv,
                [
                    ['pipe', 'rb'],
                    ['socket', 'wb'],
                    ['socket', 'wb']
                ],
                $pipes,
                '' !== $cwd_path ? $cwd_path : null
            );

            if (!is_resource($proc)) {
                return [];
            }

            $write = $except = [];

            $result[$cmd_pair[0]] = '';

            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            while (proc_get_status($proc)['running']) {
                $read = [$pipes[1], $pipes[2]];

                if (0 === (int)stream_select($read, $write, $except, 1)) {
                    continue;
                }

                foreach ($read as $pipe) {
                    while (!feof($pipe)) {
                        $msg = fgets($pipe);

                        if (false === $msg) {
                            break;
                        }

                        $msg = trim($msg) . PHP_EOL;

                        if ($realtime_debug) {
                            echo $msg;
                        }

                        $result[$cmd_pair[0]] .= $msg;
                    }
                }
            }

            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            proc_terminate($proc);
            proc_close($proc);
        } catch (\Throwable $throwable) {
            $this->error->exceptionHandler($throwable, false);
            unset($throwable);
        }

        unset($cmd_pair, $cmd_argv, $cwd_path, $realtime_debug, $proc, $pipes, $write, $except, $read, $pipe, $msg);
        return $result;
    }
}