<?php

/**
 * Hook library
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

namespace Nervsys\Lib;

use Nervsys\LC\Factory;
use Nervsys\LC\Reflect;

class Hook extends Factory
{
    public App    $app;
    public IOData $IOData;

    public array $stack_before = [];
    public array $stack_after  = [];

    /**
     * Hook constructor
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->app    = App::new();
        $this->IOData = IOData::new();
    }

    /**
     * @param string $full_cmd
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function runBefore(string $full_cmd): bool
    {
        $result  = true;
        $hook_fn = $this->findHook($full_cmd, $this->stack_before);

        foreach ($hook_fn as $fn) {
            if (!$this->passFn($fn)) {
                $result = false;
                break;
            }
        }

        unset($full_cmd, $hook_fn, $fn);
        return $result;
    }

    /**
     * @param string $full_cmd
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function runAfter(string $full_cmd): bool
    {
        $result  = true;
        $hook_fn = $this->findHook($full_cmd, $this->stack_after);

        foreach ($hook_fn as $fn) {
            if (!$this->passFn($fn)) {
                $result = false;
                break;
            }
        }

        unset($full_cmd, $hook_fn, $fn);
        return $result;
    }

    /**
     * @param string $full_cmd
     * @param array  $hook_list
     *
     * @return array
     */
    private function findHook(string $full_cmd, array $hook_list): array
    {
        $fn_list  = [];
        $full_cmd = strtr($full_cmd, '\\', '/');

        ksort($hook_list);

        foreach ($hook_list as $path => $hook) {
            if (str_starts_with($full_cmd, $path)) {
                $fn_list = array_merge($fn_list, $hook);
            }
        }

        unset($full_cmd, $hook_list, $path, $hook);
        return $fn_list;
    }

    /**
     * @param callable $fn
     *
     * @return bool
     * @throws \ReflectionException
     */
    private function passFn(callable $fn): bool
    {
        $params = self::buildArgs(
            Reflect::getCallable($fn)->getParameters(),
            $this->IOData->src_input
        );

        $fn_result = call_user_func_array($fn, $params);

        unset($fn, $params);
        return is_null($fn_result) || true === $fn_result;
    }
}