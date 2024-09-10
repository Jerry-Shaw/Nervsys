<?php

/**
 * Router library
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

class Router extends Factory
{
    public array $cgi_router_stack = [];
    public array $cli_router_stack = [];
    public array $cli_exe_path_map = [];

    /**
     * @param string $c
     *
     * @return array
     */
    public function parseCgi(string $c): array
    {
        $cmd_list = [];

        foreach ($this->cgi_router_stack as $rt) {
            $cmd_list = $this->process($rt, $c);

            if (!empty($cmd_list)) {
                break;
            }
        }

        unset($c, $rt);
        return $cmd_list;
    }

    /**
     * @param string $c
     *
     * @return array
     */
    public function parseCli(string $c): array
    {
        $cmd_list = [];

        foreach ($this->cli_router_stack as $rt) {
            $cmd_list = $this->process($rt, $c);

            if (!empty($cmd_list)) {
                break;
            }
        }

        unset($c, $rt);
        return $cmd_list;
    }

    /**
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getCgiUnit(string $c): array
    {
        $app = App::new();

        $fn_list  = [];
        $cmd_list = $this->getCmdList($c);

        foreach ($cmd_list as $cmd_raw) {
            $cmd_val = strtr($cmd_raw, '\\', '/');

            if (false === strpos($cmd_val, '/', 1)) {
                $fn_list[] = [$cmd_val, 'null', $cmd_raw];
                continue;
            }

            $full_cmd = $this->getFullCgiCmd($app->api_dir, $cmd_val, $app->is_cli);
            $fn_pos   = strrpos($full_cmd, '/');
            $class    = strtr(substr($full_cmd, 0, $fn_pos), '/', '\\');
            $method   = substr($full_cmd, $fn_pos + 1);

            $fn_list[] = [$class, $method, $cmd_raw];
        }

        unset($c, $app, $cmd_list, $cmd_raw, $cmd_val, $full_cmd, $fn_pos, $class, $method);
        return $fn_list;
    }

    /**
     * @param string $c
     *
     * @return array
     */
    public function getCliUnit(string $c): array
    {
        $exe_list = [];
        $cmd_list = $this->getCmdList($c);

        foreach ($cmd_list as $exe_name) {
            if (isset($this->cli_exe_path_map[$exe_name])) {
                $exe_list[] = [$exe_name, $this->cli_exe_path_map[$exe_name]];
            }
        }

        unset($c, $cmd_list, $exe_name);
        return $exe_list;
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
     * @param callable $rt
     * @param string   $c
     *
     * @return array
     */
    private function process(callable $rt, string $c): array
    {
        $c_list = call_user_func($rt, $c);

        if (!is_array($c_list)) {
            unset($rt, $c, $c_list);
            return [];
        }

        if (!empty($c_list) && count($c_list) === count($c_list, COUNT_RECURSIVE)) {
            $c_list = [$c_list];
        }

        unset($rt, $c);
        return $c_list;
    }

    /**
     * @param string $c
     *
     * @return string[]
     */
    private function getCmdList(string $c): array
    {
        return array_filter(str_contains($c, '|') ? explode('|', $c) : [$c]);
    }
}