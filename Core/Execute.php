<?php

/**
 * NS Execute module
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

namespace Core;

use Core\Lib\App;
use Core\Lib\Hook;
use Core\Lib\IOUnit;
use Core\Lib\Router;

/**
 * Class Execute
 *
 * @package Core
 */
class Execute extends Factory
{
    public App     $app;
    public IOUnit  $io_unit;
    public Reflect $reflect;

    public array $cgi_cmd  = [];
    public array $cli_cmd  = [];
    public array $arg_pool = [];

    /**
     * Execute constructor.
     */
    public function __construct()
    {
        $this->app     = App::new();
        $this->io_unit = IOUnit::new();
        $this->reflect = Reflect::new();
    }

    /**
     * Add matched args for a method
     *
     * @param string $class
     * @param string $method
     * @param array  $args
     *
     * @return $this
     */
    public function addArgs(string $class, string $method, array $args): self
    {
        $this->arg_pool[$class . ':' . $method] = &$args;

        unset($class, $method, $args);
        return $this;
    }

    /**
     * Build matched args for target method
     *
     * @param string $class
     * @param string $method
     * @param array  $inputs
     *
     * @return array
     * @throws \Exception
     * @throws \ReflectionException
     */
    public function buildArgs(string $class, string $method, array $inputs): array
    {
        $args = $this->reflect->buildParams($class, $method, $inputs);

        if (!empty($args['diff'])) {
            $msg = '[' . implode(', ', $args['diff']) . '] in ' . '"' . $class . '::' . $method . '"';
            throw new \Exception('Argument error or missing: ' . $msg, E_USER_NOTICE);
        }

        unset($class, $method, $inputs, $args['diff']);
        return $args['param'];
    }

    /**
     * Fetch args from pool
     *
     * @param string $class
     * @param string $method
     * @param array  $inputs
     *
     * @return array
     * @throws \ReflectionException
     */
    public function fetchArgs(string $class, string $method, array $inputs): array
    {
        $key = $class . ':' . $method;

        if (!isset($this->arg_pool[$key])) {
            $this->arg_pool[$key] = $this->buildArgs($class, $method, $inputs);
        }

        unset($class, $method, $inputs);
        return $this->arg_pool[$key];
    }

    /**
     * Fetch initialed class object
     *
     * @param string $class
     * @param array  $inputs
     *
     * @return object
     * @throws \ReflectionException
     */
    public function fetchObj(string $class, array $inputs = []): object
    {
        $object = !method_exists($class, '__construct')
            ? parent::getObj($class)
            : parent::getObj($class, $this->fetchArgs($class, '__construct', $inputs));

        unset($class, $inputs);
        return $object;
    }

    /**
     * Copy cmd from router stack
     *
     * @param Router $router
     *
     * @return $this
     */
    public function copyCmd(Router $router): self
    {
        $this->cgi_cmd = $router->cgi_cmd;
        $this->cli_cmd = $router->cli_cmd;

        unset($router);
        return $this;
    }

    /**
     * Call function script
     *
     * @return array
     */
    public function callCgi(): array
    {
        $result = [];

        //Init Hook
        $hook = Hook::new();

        //Process CGI command
        while (is_array($cmd_pair = array_shift($this->cgi_cmd))) {
            //Extract CMD contents
            [$cmd_class, $cmd_method] = $cmd_pair;

            //Get target CMD path
            $cmd_path = strtr(trim($cmd_class, '\\'), '\\', '/') . '/' . $cmd_method;

            //Check hooks before CMD
            if (!$hook->checkPass($this, $cmd_path, $hook->before)) {
                break;
            }

            try {
                //Run script method
                $result += $this->runScript($cmd_class, $cmd_method, $cmd_pair[2] ?? $cmd_path);
            } catch (\Throwable $throwable) {
                $this->app->showDebug($throwable, true);
                unset($throwable);
                continue;
            }

            //Check hooks after CMD
            if (!$hook->checkPass($this, $cmd_path, $hook->after)) {
                break;
            }
        }

        unset($hook, $cmd_pair, $cmd_class, $cmd_method, $cmd_path);
        return $result;
    }

    /**
     * Call external program
     *
     * @return array
     */
    public function callCli(): array
    {
        $result = [];

        //Init OSUnit
        $os_unit = OSUnit::new();

        //Process CLI command
        while (is_array($cmd_pair = array_shift($this->cli_cmd))) {
            //Extract CMD contents
            [$cmd_name, $exe_path] = $cmd_pair;

            //Skip empty command
            if ('' === $exe_path = trim($exe_path)) {
                $this->app->showDebug(new \Exception('"' . $cmd_name . '" NOT defined!', E_USER_NOTICE), true);
                continue;
            }

            try {
                //Run external program
                $result += $this->runProgram($os_unit, $cmd_name, $exe_path);
            } catch (\Throwable $throwable) {
                $this->app->showDebug($throwable, true);
                unset($throwable);
            }
        }

        unset($os_unit, $cmd_pair, $cmd_name, $exe_path);
        return $result;
    }

    /**
     * Run script method
     *
     * @param string $cmd_class
     * @param string $cmd_method
     * @param string $input_name
     *
     * @return array
     * @throws \ReflectionException
     */
    public function runScript(string $cmd_class, string $cmd_method, string $input_name): array
    {
        $result = [];

        //Call method
        $fn_result = call_user_func(
            [
                !$this->reflect->getMethod($cmd_class, $cmd_method)->isStatic()
                    ? $this->fetchObj($cmd_class, $this->io_unit->src_input)
                    : $cmd_class,
                $cmd_method
            ],
            ...$this->fetchArgs($cmd_class, $cmd_method, $this->io_unit->src_input)
        );

        //Collect result
        if (!is_null($fn_result)) {
            $result[$input_name] = &$fn_result;
        }

        unset($cmd_class, $cmd_method, $input_name, $fn_result);
        return $result;
    }

    /**
     * Run external program
     *
     * @param OSUnit $os_unit
     * @param string $cmd_name
     * @param string $exe_path
     *
     * @return array
     * @throws \Exception
     */
    public function runProgram(OSUnit $os_unit, string $cmd_name, string $exe_path): array
    {
        $result = [];

        //Build CLI command
        $os_unit->setCmd('"' . $exe_path . '" ' . $this->io_unit->src_argv);

        //Check for BG command
        if ('none' === $this->io_unit->cli_data_type) {
            $os_unit->setAsBg();
        }

        //Create process
        $process = proc_open(
            $os_unit->setEnvPath()->fetchCmd(),
            [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['file', $this->app->log_path . DIRECTORY_SEPARATOR . date('Ymd') . '-CLI' . '.log', 'ab+']
            ],
            $pipes
        );

        //Create process failed
        if (!is_resource($process)) {
            throw new \Exception($cmd_name . ': Access denied or command ERROR!', E_USER_WARNING);
        }

        //Collect result
        if ('none' !== $this->io_unit->cli_data_type) {
            $data = '';

            //Read from pipe
            while (!feof($pipes[1])) {
                $data .= fread($pipes[1], 8192);
            }

            $result[$cmd_name] = &$data;
            unset($data);
        }

        //Close pipes
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        //Close process
        proc_close($process);

        unset($os_unit, $cmd_name, $exe_path, $process, $pipes, $pipe);
        return $result;
    }
}