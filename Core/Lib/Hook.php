<?php

/**
 * NS Hook library
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

use Core\Execute;
use Core\Factory;

/**
 * Class Hook
 *
 * @package Core\Lib
 */
class Hook extends Factory
{
    public App    $app;
    public Router $router;

    public array $before = [];
    public array $after  = [];

    /**
     * Hook constructor.
     */
    public function __construct()
    {
        $this->app    = App::new();
        $this->router = Router::new();
    }

    /**
     * Add hook fn before input_c
     *
     * @param string $input_c
     * @param string $hook_class
     * @param string $hook_method
     *
     * @return $this
     */
    public function addBefore(string $input_c, string $hook_class, string $hook_method): self
    {
        $this->before[$this->router->getCmd($input_c, true)][] = [$hook_class, $hook_method];

        unset($input_c, $hook_class, $hook_method);
        return $this;
    }

    /**
     * Add hook fn after input_c
     *
     * @param string $input_c
     * @param string $hook_class
     * @param string $hook_method
     *
     * @return $this
     */
    public function addAfter(string $input_c, string $hook_class, string $hook_method): self
    {
        $this->after[$this->router->getCmd($input_c, true)][] = [$hook_class, $hook_method];

        unset($input_c, $hook_class, $hook_method);
        return $this;
    }

    /**
     * Check hook pass status
     *
     * @param Execute $execute
     * @param string  $input_c
     * @param array   $hook_list
     *
     * @return bool
     */
    public function checkPass(Execute $execute, string $input_c, array $hook_list): bool
    {
        $fn_list = $this->getHook($input_c, $hook_list);

        foreach ($fn_list as $hook_fn) {
            if (!$this->callHook($execute, $hook_fn)) {
                unset($execute, $input_c, $hook_list, $fn_list, $hook_fn);
                return false;
            }
        }

        unset($execute, $input_c, $hook_list, $fn_list, $hook_fn);
        return true;
    }

    /**
     * Get hook list for input_c
     *
     * @param string $input_c
     * @param array  $h_list
     *
     * @return array
     */
    private function getHook(string $input_c, array $h_list): array
    {
        $fn_list = [];

        ksort($h_list);

        foreach ($h_list as $c_path => $c_hooks) {
            if (0 === strpos($input_c, $c_path)) {
                $fn_list = array_merge($fn_list, $c_hooks);
            }
        }

        unset($input_c, $h_list, $c_path, $c_hooks);
        return $fn_list;
    }

    /**
     * Call hook function
     *
     * @param Execute $execute
     * @param array   $hook_fn
     *
     * @return bool
     */
    private function callHook(Execute $execute, array $hook_fn): bool
    {
        try {
            $result = $execute->runScript($hook_fn[0], $hook_fn[1], implode('/', $hook_fn));

            unset($execute, $hook_fn);
            return (empty($result) || true === current($result));
        } catch (\Throwable $throwable) {
            $this->app->showDebug($throwable, true);
            unset($execute, $hook_fn, $throwable);
            return false;
        }
    }
}