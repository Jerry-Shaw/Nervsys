<?php

/**
 * Caller library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2025 秋水之冰 <27206617@qq.com>
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
use Nervsys\Core\Mgr\OSMgr;
use Nervsys\Core\Reflect;

class Caller extends Factory
{
    /**
     * @param array $cmd
     * @param array $args
     * @param bool  $anti_xss
     *
     * @return mixed
     * @throws \ReflectionException
     */
    public function runApiFn(array $cmd, array $args, bool $anti_xss): mixed
    {
        $security = Security::new();

        try {
            if ($anti_xss) {
                $args = $security->antiXss($args);
            }

            $api_fn = Security::class !== $cmd[0]
                ? $security->getApiMethod($cmd[0], $cmd[1], $args, \ReflectionMethod::IS_PUBLIC)
                : [$security, $cmd[1]];

            $api_args = parent::buildArgs(Reflect::getCallable($api_fn)->getParameters(), $args);
        } catch (\ReflectionException $reflectionException) {
            $api_fn   = current($security->fn_target_invalid);
            $api_args = parent::buildArgs(Reflect::getCallable($api_fn)->getParameters(), ['message' => $reflectionException->getMessage()]);

            unset($reflectionException);
        } catch (\Throwable $throwable) {
            Error::new()->exceptionHandler($throwable, false, false);

            $api_fn   = current($security->fn_argument_invalid);
            $api_args = parent::buildArgs(Reflect::getCallable($api_fn)->getParameters(), ['message' => $throwable->getMessage()]);

            unset($throwable);
        }

        $fn_result = call_user_func($api_fn, ...$api_args);

        unset($cmd, $args, $anti_xss, $security, $api_fn, $api_args);
        return $fn_result;
    }

    /**
     * @param array  $cmd_pair
     * @param array  $cmd_argv
     * @param string $cwd_path
     * @param bool   $realtime_debug
     *
     * @return string
     */
    public function runProgram(array $cmd_pair, array $cmd_argv = [], string $cwd_path = '', bool $realtime_debug = false): string
    {
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
            unset($cmd_pair, $cmd_argv, $cwd_path, $realtime_debug, $proc, $pipes);
            return '';
        }

        $result = '';
        $write  = $except = [];

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        while (proc_get_status($proc)['running']) {
            $read = [$pipes[1], $pipes[2]];

            if (0 === (int)stream_select($read, $write, $except, 0, 500000)) {
                continue;
            }

            foreach ($read as $pipe) {
                while (!feof($pipe)) {
                    $msg = fgets($pipe);

                    if (false === $msg) {
                        break;
                    }

                    if ($realtime_debug) {
                        echo $msg;
                    }

                    $result .= $msg;
                }
            }
        }

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        proc_terminate($proc);
        proc_close($proc);

        unset($cmd_pair, $cmd_argv, $cwd_path, $realtime_debug, $proc, $pipes, $write, $except, $read, $pipe, $msg);
        return $result;
    }

    /**
     * @param string $cmd
     * @param bool   $in_background
     *
     * @return void
     * @throws \ReflectionException
     */
    public function runAsync(string $cmd, bool $in_background = true): void
    {
        pclose(popen(OSMgr::new()->inBackground($in_background)->buildCmd($cmd), 'rb'));
        unset($cmd, $in_background);
    }
}