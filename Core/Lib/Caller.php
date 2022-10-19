<?php

/**
 * Caller library
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

namespace Nervsys\Core\Lib;

use Nervsys\Core\Factory;
use Nervsys\Core\Mgr\OSMgr;
use Nervsys\Core\Reflect;

class Caller extends Factory
{
    /**
     * @param array $cmd
     * @param array $args
     *
     * @return array
     * @throws \ReflectionException
     */
    public function runApiFn(array $cmd, array $args): array
    {
        $result   = [];
        $security = Security::new();

        try {
            $args = $security->antiXss($args);

            $api_fn = Security::class !== $cmd[0]
                ? $security->getApiMethod($cmd[0], $cmd[1], $args, \ReflectionMethod::IS_PUBLIC)
                : [$security, $cmd[1]];

            $api_args = parent::buildArgs(Reflect::getCallable($api_fn)->getParameters(), $args);
        } catch (\ReflectionException $reflectionException) {
            $api_fn   = [$security, 'targetInvalid'];
            $api_args = parent::buildArgs(Reflect::getCallable($api_fn)->getParameters(), ['message' => $reflectionException->getMessage()]);

            unset($reflectionException);
        } catch (\Throwable $throwable) {
            $api_fn   = [$security, 'ArgumentInvalid'];
            $api_args = parent::buildArgs(Reflect::getCallable($api_fn)->getParameters(), ['message' => $throwable->getMessage()]);

            unset($throwable);
        }

        $fn_result = call_user_func_array($api_fn, $api_args);

        if (!is_null($fn_result)) {
            $result[$cmd[2] ?? strtr($cmd[0], '\\', '/') . '/' . $cmd[1]] = &$fn_result;
        }

        unset($cmd, $args, $security, $api_fn, $api_args, $fn_result);
        return $result;
    }

    /**
     * @param array  $cmd_pair
     * @param array  $cmd_argv
     * @param string $cwd_path
     * @param bool   $realtime_debug
     *
     * @return array
     */
    public function runProgram(array $cmd_pair, array $cmd_argv = [], string $cwd_path = '', bool $realtime_debug = false): array
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
            return [];
        }

        $write  = $except = [];
        $result = [$cmd_pair[0] => ''];

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

        unset($cmd_pair, $cmd_argv, $cwd_path, $realtime_debug, $proc, $pipes, $write, $except, $read, $pipe, $msg);
        return $result;
    }

    /**
     * @param string $cmd
     *
     * @return void
     * @throws \ReflectionException
     */
    public function runAsync(string $cmd): void
    {
        pclose(popen(OSMgr::new()->inBackground(true)->buildCmd($cmd), 'rb'));
        unset($cmd);
    }
}