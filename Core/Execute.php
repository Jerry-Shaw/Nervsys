<?php

/**
 * NS Execute module
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

namespace Core;

use Core\Lib\App;
use Core\Lib\Hook;
use Core\Lib\IOUnit;

/**
 * Class Execute
 *
 * @package Core
 */
class Execute extends Factory
{
    public App    $app;
    public IOUnit $io_unit;

    public array $cmd_cgi;
    public array $cmd_cli;

    /**
     * Execute constructor.
     */
    public function __construct()
    {
        $this->app     = App::new();
        $this->io_unit = IOUnit::new();
    }

    /**
     * Set commands
     *
     * @param array $cmd_group
     *
     * @return $this
     */
    public function setCmd(array $cmd_group): self
    {
        $this->cmd_cgi = &$cmd_group['cgi'];
        $this->cmd_cli = &$cmd_group['cli'];

        unset($cmd_group);
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

        //Init Hook & Reflect
        $hook    = Hook::new();
        $reflect = Reflect::new();

        //Process CGI command
        while (is_array($cmd_pair = array_shift($this->cmd_cgi))) {
            //Extract CMD contents
            [$cmd_class, $cmd_method] = $cmd_pair;

            //Get CMD input name
            $input_name = $cmd_pair[2] ?? implode('/', $cmd_pair);

            //Run prepend hooks
            if (!$hook->passPrepend($this, $reflect, $input_name)) {
                break;
            }

            try {
                //Run script method
                $result += $this->runScript($reflect, $cmd_class, $cmd_method, $input_name);
            } catch (\Throwable $throwable) {
                $this->app->showDebug($throwable, true);
                unset($throwable);
                continue;
            }

            //Run append hooks
            if (!$hook->passAppend($this, $reflect, $input_name)) {
                break;
            }
        }

        unset($hook, $reflect, $cmd_pair, $cmd_class, $cmd_method, $input_name);
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
        while (is_array($cmd_pair = array_shift($this->cmd_cli))) {
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
     * @param \Core\Reflect $reflect
     * @param string        $cmd_class
     * @param string        $cmd_method
     * @param string        $input_name
     *
     * @return array
     * @throws \ReflectionException
     */
    public function runScript(Reflect $reflect, string $cmd_class, string $cmd_method, string $input_name): array
    {
        $result = [];

        //Get method reflection
        $method_reflect = $reflect->getMethod($cmd_class, $cmd_method);

        //Create class instance
        $class_object = !$method_reflect->isStatic()
            ? (!method_exists($cmd_class, '__construct')
                ? parent::getObj($cmd_class)
                : parent::getObj($cmd_class, $this->fetchParams($reflect, $cmd_class, '__construct', $this->io_unit->src_input, $cmd_class)))
            : $cmd_class;

        //Call method
        $fn_result = call_user_func(
            [$class_object, $cmd_method],
            ...$this->fetchParams($reflect, $cmd_class, $cmd_method, $this->io_unit->src_input, $input_name)
        );

        //Collect result
        if (!is_null($fn_result)) {
            $result[$input_name] = &$fn_result;
        }

        unset($reflect, $cmd_class, $cmd_method, $input_name, $method_reflect, $class_object, $fn_result);
        return $result;
    }

    /**
     * Run external program
     *
     * @param \Core\OSUnit $os_unit
     * @param string       $cmd_name
     * @param string       $exe_path
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

    /**
     * Fetch matched args from inputs
     *
     * @param \Core\Reflect $reflect
     * @param string        $class
     * @param string        $method
     * @param array         $inputs
     * @param string        $name
     *
     * @return array
     * @throws \Exception
     * @throws \ReflectionException
     */
    public function fetchParams(Reflect $reflect, string $class, string $method, array $inputs, string $name = ''): array
    {
        $args = $reflect->buildParams($class, $method, $inputs);

        if (!empty($args['diff'])) {
            $msg = '[' . implode(', ', $args['diff']) . '] in ' . '"' . ('' !== $name ? $name : $class . '\\' . $method) . '"';
            throw new \Exception('Argument error or missing: ' . $msg, E_USER_WARNING);
        }

        unset($reflect, $class, $method, $inputs, $name, $args['diff']);
        return $args['param'];
    }
}