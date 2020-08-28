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
    public function setCMD(array $cmd_group): self
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
    public function callCGI(): array
    {
        $result = [];

        if (empty($this->cmd_cgi)) {
            return $result;
        }

        //Init Reflect
        $Reflect = Reflect::new();

        //Process CGI command
        while (is_array($cmd_pair = array_shift($this->cmd_cgi))) {
            $result += $this->runScript($Reflect, $cmd_pair);
        }

        unset($Reflect, $cmd_pair);
        return $result;
    }

    /**
     * Call external program
     *
     * @return array
     */
    public function callCLI(): array
    {
        $result = [];

        if (empty($this->cmd_cli)) {
            return $result;
        }

        //Init OSUnit
        $OSUnit = OSUnit::new();

        //Process CLI command
        while (is_array($cmd_pair = array_shift($this->cmd_cli))) {
            $result += $this->runProgram($OSUnit, $cmd_pair);
        }

        unset($OSUnit, $cmd_pair);
        return $result;
    }

    /**
     * Run script method
     *
     * @param \Core\Reflect $reflect
     * @param array         $cmd_pair
     *
     * @return array
     */
    public function runScript(Reflect $reflect, array $cmd_pair): array
    {
        $result = [];

        try {
            //Extract CMD contents
            [$cmd_class, $cmd_method] = $cmd_pair;

            //Get CMD value
            $cmd_value = $cmd_pair[2] ?? implode('/', $cmd_pair);

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
                ...$this->fetchParams($reflect, $cmd_class, $cmd_method, $this->io_unit->src_input, $cmd_value)
            );

            //Merge result
            if (!is_null($fn_result)) {
                $result += [$cmd_value => &$fn_result];
            }

            unset($cmd_class, $cmd_method, $cmd_value, $method_reflect, $class_object, $fn_result);
        } catch (\Throwable $throwable) {
            $this->app->showDebug($throwable, true);
            unset($throwable);
        }

        unset($reflect, $cmd_pair);
        return $result;
    }

    /**
     * Run external program
     *
     * @param \Core\OSUnit $os_unit
     * @param array        $cmd_pair
     *
     * @return array
     */
    public function runProgram(OSUnit $os_unit, array $cmd_pair): array
    {
        $result = [];

        try {
            //Extract CMD contents
            [$cmd_name, $exe_path] = $cmd_pair;

            //Skip empty command
            if ('' === $exe_path = trim($exe_path)) {
                return [];
            }

            //Build CLI command
            $os_unit->setCmd('"' . $exe_path . '" ' . $this->io_unit->src_argv);

            //Check for BG command
            if ('none' === $this->io_unit->cli_data_type) {
                $os_unit->setAsBg();
            }

            //Create process
            $process = proc_open(
                $os_unit->setEnvPath()->setForProc()->fetchCmd(),
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

                $result += [$cmd_name => $data];
                unset($data);
            }

            //Close pipes
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            //Close process
            proc_close($process);

            unset($cmd_name, $exe_path, $process, $pipes, $pipe);
        } catch (\Throwable $throwable) {
            $this->app->showDebug($throwable, true);
            unset($throwable);
        }

        unset($os_unit, $cmd_pair);
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