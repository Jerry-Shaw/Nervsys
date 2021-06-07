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

    public array $cgi_cmd      = [];
    public array $cgi_stack    = [];
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
     * Get full cmd from input value
     *
     * @param string $cmd_val
     * @param bool   $root_exec
     *
     * @return string
     */
    public function getCmd(string $cmd_val, bool $root_exec = false): string
    {
        $api_dir = $this->app->api_path . '/';
        $cmd_val = strtr($cmd_val, '\\', '/');

        $path_match = !$root_exec
            ? 0 === strpos($cmd_val, $api_dir)
            : 0 === strpos($cmd_val, '/') || 0 === strpos($cmd_val, $api_dir);

        $cmd_val = trim($cmd_val, '/');

        $cmd = '/' . ($path_match ? $cmd_val : $api_dir . $cmd_val);

        unset($cmd_val, $root_exec, $api_dir, $path_match);
        return $cmd;
    }

    /**
     * CGI router parser
     *
     * @param string $c
     *
     * @return array
     */
    public function cgiParser(string $c): array
    {
        $fn_list  = [];
        $cmd_list = $this->getList($c);

        //Init IOUnit & Reflect
        $io_unit = IOUnit::new();
        $reflect = Reflect::new();

        foreach ($cmd_list as $cmd_val) {
            try {
                //Skip invalid CMD
                if (false === strpos(($cmd_val = strtr($cmd_val, '\\', '/')), '/', 1)) {
                    throw new \Exception('"' . $cmd_val . '" invalid!', E_USER_NOTICE);
                }

                //Get full CMD value
                $cmd = $this->getCmd($cmd_val, $this->app->is_cli);

                //Get class & method from CMD
                $fn_pos = strrpos($cmd, '/');
                $class  = strtr(substr($cmd, 0, $fn_pos), '/', '\\');
                $method = substr($cmd, $fn_pos + 1);

                //Skip non-exist class
                if (!class_exists($class)) {
                    throw new \Exception('"' . substr($cmd_val, 0, strrpos($cmd_val, '/')) . '" NOT found!', E_USER_NOTICE);
                }

                //Skip non-exist method
                if (!method_exists($class, $method)) {
                    throw new \Exception('"' . $cmd_val . '" NOT found!', E_USER_NOTICE);
                }

                $fn_list[] = [$class, $method, $cmd_val];
            } catch (\Throwable $throwable) {
                $this->app->showDebug($throwable, false);
                unset($throwable);
            }
        }

        unset($c, $cmd_list, $io_unit, $reflect, $cmd_val, $cmd, $fn_pos, $class, $method);
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