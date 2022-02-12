<?php

/**
 * Router library
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

use Nervsys\LC\Error;
use Nervsys\LC\Factory;

class Router extends Factory
{
    public App $app;

    public array $cgi_router_stack = [];
    public array $cli_router_stack = [];
    public array $cli_exe_path_map = [];

    /**
     * Router constructor
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->app = App::new();

        $this->cgi_router_stack[] = [$this, 'cgiUnit'];
        $this->cli_router_stack[] = [$this, 'cliUnit'];
    }

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
    public function cgiUnit(string $c): array
    {
        $fn_list  = [];
        $cmd_list = $this->getCmdList($c);

        foreach ($cmd_list as $cmd_val) {
            try {
                $cmd_val = strtr($cmd_val, '\\', '/');

                if (false === strpos($cmd_val, '/', 1)) {
                    throw new \Exception('"' . $cmd_val . '" NOT valid!', E_USER_NOTICE);
                }

                $full_cmd = $this->getFullCgiCmd($cmd_val, $this->app->is_cli);
                $fn_pos   = strrpos($full_cmd, '/');
                $class    = strtr(substr($full_cmd, 0, $fn_pos), '/', '\\');
                $method   = substr($full_cmd, $fn_pos + 1);

                if (!class_exists($class)) {
                    throw new \Exception('"' . substr($cmd_val, 0, strrpos($cmd_val, '/')) . '" NOT found!', E_USER_NOTICE);
                }

                if (!method_exists($class, $method)) {
                    throw new \Exception('"' . $cmd_val . '" NOT found!', E_USER_NOTICE);
                }

                $fn_list[] = [$class, $method, $cmd_val];
            } catch (\Throwable $throwable) {
                Error::new()->exceptionHandler($throwable, false, !$this->app->is_cli);
                unset($throwable);
            }
        }

        unset($c, $cmd_list, $cmd_val, $full_cmd, $fn_pos, $class, $method);
        return $fn_list;
    }

    /**
     * @param string $c
     *
     * @return array
     */
    public function cliUnit(string $c): array
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
        return str_contains($c, '|') ? explode('|', $c) : [$c];
    }

    /**
     * @param string $cmd_val
     * @param bool   $cli_exec
     *
     * @return string
     */
    private function getFullCgiCmd(string $cmd_val, bool $cli_exec = false): string
    {
        $api_dir = $this->app->api_path . '/';
        $cmd_val = strtr($cmd_val, '\\', '/');

        $path_match = !$cli_exec
            ? str_starts_with($cmd_val, $api_dir)
            : str_starts_with($cmd_val, '/') || str_starts_with($cmd_val, $api_dir);

        $cmd_val = trim($cmd_val, '/');

        $cmd = '/' . ($path_match ? $cmd_val : $api_dir . $cmd_val);

        unset($cmd_val, $cli_exec, $api_dir, $path_match);
        return $cmd;
    }
}