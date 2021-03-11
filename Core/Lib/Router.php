<?php

/**
 * NS Router library
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

use Core\Factory;
use Core\Reflect;

/**
 * Class Router
 *
 * @package Core\Lib
 */
class Router extends Factory
{
    public App $app;

    public array $cgi_cmd   = [];
    public array $cgi_stack = [];

    public array $cli_cmd      = [];
    public array $cli_stack    = [];
    public array $cli_path_map = [];

    /**
     * Router constructor.
     */
    public function __construct()
    {
        $this->app = App::new();

        $this->cgi_stack[] = [$this, 'cgiParser'];
        $this->cli_stack[] = [$this, 'cliParser'];
    }

    /**
     * Add custom router to CGI stack
     *
     * @param object $router_object
     * @param string $router_method
     *
     * @return $this
     */
    public function addCgiStack(object $router_object, string $router_method): self
    {
        array_unshift($this->cgi_stack, [$router_object, $router_method]);

        unset($router_object, $router_method);
        return $this;
    }

    /**
     * Add custom router to CLI stack
     *
     * @param object $router_object
     * @param string $router_method
     *
     * @return $this
     */
    public function addCliStack(object $router_object, string $router_method): self
    {
        array_unshift($this->cli_stack, [$router_object, $router_method]);

        unset($router_object, $router_method);
        return $this;
    }

    /**
     * Add CLI path map
     *
     * @param string $name
     * @param string $path
     *
     * @return $this
     */
    public function addCliMap(string $name, string $path): self
    {
        $this->cli_path_map[$name] = &$path;

        unset($name, $path);
        return $this;
    }

    /**
     * Parse CMD
     *
     * @param string $c
     *
     * @return $this
     */
    public function parse(string $c): self
    {
        foreach ($this->cgi_stack as $rt) {
            if (!empty($cmd = $this->callParser($rt, $c))) {
                $this->cgi_cmd = $cmd;
                break;
            }
        }

        if (!$this->app->is_cli) {
            unset($c, $rt, $cmd);
            return $this;
        }

        foreach ($this->cli_stack as $rt) {
            if (!empty($cmd = $this->callParser($rt, $c))) {
                $this->cli_cmd = $cmd;
                break;
            }
        }

        unset($c, $rt, $cmd);
        return $this;
    }

    /**
     * CGI router parser
     *
     * @param string $c
     *
     * @return array
     * @throws \ReflectionException
     */
    public function cgiParser(string $c): array
    {
        $fn_list  = [];
        $cmd_list = $this->getList($c);

        //Init IOUnit & Reflect
        $io_unit = IOUnit::new();
        $reflect = Reflect::new();

        foreach ($cmd_list as $cmd) {
            //Skip invalid CMD
            if (false === strpos($cmd, '/', 1)) {
                continue;
            }

            //Validate & redirect CMD
            $cmd_val = !$this->app->is_cli
                ? (0 !== strpos($cmd, $this->app->api_path . '/') ? $this->app->api_path . '/' . ltrim($cmd, '/') : $cmd)
                : ('/' !== $cmd[0] && 0 !== strpos($cmd, $this->app->api_path . '/') ? $this->app->api_path . '/' . ltrim($cmd, '/') : $cmd);

            //Get class & method from CMD
            $cmd_val = trim($cmd_val, '/');
            $fn_pos  = strrpos($cmd_val, '/');
            $class   = '\\' . strtr(substr($cmd_val, 0, $fn_pos), '/', '\\');
            $method  = substr($cmd_val, $fn_pos + 1);

            //Skip non-exist class
            if (!class_exists($class)) {
                $this->app->showDebug(new \Exception('"' . substr($cmd, 0, strrpos($cmd, '/')) . '" NOT found!', E_USER_NOTICE), false);
                continue;
            }

            //Skip non-exist method
            if (!method_exists($class, $method)) {
                $this->app->showDebug(new \Exception('"' . $cmd . '" NOT found!', E_USER_NOTICE), false);
                continue;
            }

            //Save method return type
            if ('' !== ($return_type = $reflect->getReturnType($class, $method))) {
                $io_unit->return_type[$cmd] = $return_type;
            }

            $fn_list[] = [$class, $method, $cmd];
        }

        unset($c, $cmd_list, $io_unit, $reflect, $cmd, $cmd_val, $fn_pos, $class, $method, $return_type);
        return $fn_list;
    }

    /**
     * CLI router parser
     *
     * @param string $c
     *
     * @return array
     */
    public function cliParser(string $c): array
    {
        $ex_list  = [];
        $cmd_list = $this->getList($c);

        foreach ($cmd_list as $cmd) {
            if (isset($this->cli_path_map[$cmd])) {
                $ex_list[] = [$cmd, $this->cli_path_map[$cmd]];
            }
        }

        unset($c, $cmd_list, $cmd);
        return $ex_list;
    }

    /**
     * Call router parser
     *
     * @param array  $rt
     * @param string $c
     *
     * @return array
     */
    private function callParser(array $rt, string $c): array
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
     * Get CMD split list
     *
     * @param string $c
     *
     * @return array
     */
    private function getList(string $c): array
    {
        return false !== strpos($c, '-') ? explode('-', $c) : [$c];
    }
}