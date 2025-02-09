<?php

/**
 * Router library
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

class Router extends Factory
{
    public array $cgi_router_stack = [];
    public array $cli_router_stack = [];
    public array $exe_path_mapping = [];

    /**
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function parseCgi(string $c): array
    {
        $cmd_data = [];

        foreach ($this->cgi_router_stack as $router) {
            $cmd_data = $this->process($router, $c);

            if (!empty($cmd_data)) {
                break;
            }
        }

        unset($c, $router);
        return $cmd_data;
    }

    /**
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function parseCli(string $c): array
    {
        $cmd_data = [];

        foreach ($this->cli_router_stack as $router) {
            $cmd_data = $this->process($router, $c);

            if (!empty($cmd_data)) {
                break;
            }
        }

        unset($c, $router);
        return $cmd_data;
    }

    /**
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getCgiUnit(string $c): array
    {
        $fn  = [];
        $app = App::new();
        $cmd = strtr($c, '\\', '/');

        if (false !== strpos($cmd, '/', 1)) {
            $full_cmd = $this->getFullCgiCmd($app->api_dir, $cmd, $app->is_cli);
            $fn_pos   = strrpos($full_cmd, '/');
            $class    = strtr(substr($full_cmd, 0, $fn_pos), '/', '\\');
            $method   = substr($full_cmd, $fn_pos + 1);

            $fn = [$class, $method];
        }

        unset($c, $app, $cmd, $full_cmd, $fn_pos, $class, $method);
        return $fn;
    }

    /**
     * @param string $c
     *
     * @return array
     */
    public function getCliUnit(string $c): array
    {
        $exe = [];

        if (isset($this->exe_path_mapping[$c])) {
            $exe = [$c, $this->exe_path_mapping[$c]];
        }

        unset($c);
        return $exe;
    }

    /**
     * @param string $api_path
     * @param string $cmd_val
     * @param bool   $cli_exec
     *
     * @return string
     */
    public function getFullCgiCmd(string $api_path, string $cmd_val, bool $cli_exec = false): string
    {
        $api_dir = trim($api_path, '/') . '/';
        $cmd_val = strtr($cmd_val, '\\', '/');

        $path_match = !$cli_exec
            ? str_starts_with($cmd_val, $api_dir)
            : str_starts_with($cmd_val, '/') || str_starts_with($cmd_val, $api_dir);

        $cmd_val = trim($cmd_val, '/');
        $cmd     = '/' . ($path_match ? $cmd_val : $api_dir . $cmd_val);

        unset($api_path, $cmd_val, $cli_exec, $api_dir, $path_match);
        return $cmd;
    }

    /**
     * @param callable $router
     * @param string   $cmd
     *
     * @return array
     * @throws \ReflectionException
     */
    private function process(callable $router, string $cmd): array
    {
        try {
            $cmd_data = call_user_func($router, $cmd);

            if (is_array($cmd_data)) {
                unset($router, $cmd);
                return $cmd_data;
            }
        } catch (\Throwable $throwable) {
            Error::new()->exceptionHandler($throwable, false, false);
            unset($throwable);
        }

        unset($router, $cmd, $cmd_data);
        return [];
    }
}