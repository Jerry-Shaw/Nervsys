<?php

/**
 * NS Router library
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
 * Class Router
 *
 * @package Core\Lib
 */
class Router extends Factory
{
    public App $app;

    public array $cgi_stack;
    public array $cli_stack;
    public array $cli_mapping = [];

    /**
     * Router constructor.
     */
    public function __construct()
    {
        $this->app = App::new();

        $this->cgi_stack[] = [$this, 'cgiRouter'];
        $this->cli_stack[] = [$this, 'cliRouter'];
    }

    /**
     * Add custom router
     *
     * @param object $router_object
     * @param string $router_method
     * @param string $target_stack
     *
     * @return $this
     */
    public function addStack(object $router_object, string $router_method, string $target_stack = 'cgi'): self
    {
        array_unshift($this->{$target_stack . '_stack'}, [$router_object, $router_method]);

        unset($router_object, $router_method, $target_stack);
        return $this;
    }

    /**
     * Add executable path mapping
     *
     * @param string $name
     * @param string $path
     *
     * @return $this
     */
    public function addMapping(string $name, string $path): self
    {
        $this->cli_mapping[$name] = &$path;

        unset($name, $path);
        return $this;
    }

    /**
     * Parse CMD
     *
     * @param string $c
     *
     * @return array
     */
    public function parse(string $c): array
    {
        $cmd_list = ['cli' => [], 'cgi' => []];

        if ('' === $c) {
            unset($c);
            return $cmd_list;
        }

        //CLI router caller
        if ($this->app->is_cli) {
            foreach ($this->cli_stack as $rt) {
                if (!empty($cmd = $this->callRouter($rt, $c))) {
                    $cmd_list['cli'] = $cmd;
                    break;
                }
            }
        }

        //CGI router caller
        foreach ($this->cgi_stack as $rt) {
            if (!empty($cmd = $this->callRouter($rt, $c))) {
                $cmd_list['cgi'] = $cmd;
                break;
            }
        }

        unset($c, $rt, $cmd);
        return $cmd_list;
    }

    /**
     * Call router
     *
     * @param array  $rt
     * @param string $c
     *
     * @return array
     */
    private function callRouter(array $rt, string $c): array
    {
        $c_list = call_user_func($rt, $c);

        if (!is_array($c_list)) {
            unset($rt, $c, $c_list);
            return [];
        }

        if (!empty($c_list) && count($c_list) === count($c_list, 1)) {
            $c_list = [$c_list];
        }

        unset($rt, $c);
        return $c_list;
    }

    /**
     * CGI router parser
     *
     * @param string $c
     *
     * @return array
     */
    public function cgiRouter(string $c): array
    {
        $fn_list  = [];
        $cmd_list = $this->makeList($c);

        foreach ($cmd_list as $cmd) {
            if (false === strpos($cmd, '/', 1)) {
                continue;
            }

            $cmd_val = $cmd;

            if ('/' !== $cmd[0] && 0 !== strpos($cmd, $this->app->api_path)) {
                $cmd = $this->app->api_path . '/' . $cmd;
            }

            $cmd = trim($cmd, '/');

            $fn_pos    = strrpos($cmd, '/');
            $fn_list[] = [substr($cmd, 0, $fn_pos), substr($cmd, $fn_pos + 1), $cmd_val];
        }

        unset($c, $cmd_list, $cmd, $fn_pos);
        return $fn_list;
    }

    /**
     * CLI router parser
     *
     * @param string $c
     *
     * @return array
     */
    public function cliRouter(string $c): array
    {
        $ex_list  = [];
        $cmd_list = $this->makeList($c);

        foreach ($cmd_list as $cmd) {
            if (!isset($this->cli_mapping[$cmd])) {
                continue;
            }

            $ex_list[] = [$cmd, $this->cli_mapping[$cmd]];
        }

        unset($c, $cmd_list, $cmd);
        return $ex_list;
    }

    /**
     * Get CMD split list
     *
     * @param string $c
     *
     * @return array
     */
    private function makeList(string $c): array
    {
        return false !== strpos($c, '-') ? explode('-', $c) : [$c];
    }
}