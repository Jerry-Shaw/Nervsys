<?php

/**
 * Nervsys main class
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

namespace Nervsys;

use Nervsys\LC\Factory;
use Nervsys\LC\Reflect;
use Nervsys\LC\System;

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    exit('Nervsys 8.1+ needs PHP 8.1.0 or higher!');
}

class NS
{
    public System $system;

    /**
     * NS constructor
     *
     * @throws \Exception
     */
    public function __construct()
    {
        set_time_limit(0);
        ignore_user_abort(true);

        define('NS_VER', '8.1.0');
        define('NS_ROOT', __DIR__);
        define('NS_NAMESPACE', __NAMESPACE__);

        define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
        define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

        define('HOSTNAME', gethostname() ?: 'localhost');

        spl_autoload_register(
            static function (string $class): void
            {
                $file_path = __DIR__ . DIRECTORY_SEPARATOR . strtr(strstr($class, '\\'), '\\', DIRECTORY_SEPARATOR) . '.php';

                if (is_file($file_path)) {
                    require $file_path;
                }

                unset($class, $file_path);
            },
            true,
            true
        );

        $this->system = System::new();
        $this->system->addAutoloadPath($this->system->app->root_path, true);
    }

    /**
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function go(): void
    {
        date_default_timezone_set($this->system->app->timezone);

        if (!$this->system->app->is_cli) {
            $this->system->CORS->checkPermission($this->system->app->is_tls);
            $this->system->IOData->readCgi();
        } else {
            $this->system->IOData->readCli();
        }

        if ($this->system->app->is_cli) {
            $cli_cmd = $this->system->router->parseCli($this->system->IOData->src_cmd);

            if (!empty($cli_cmd)) {
                while (is_array($cmd_data = array_shift($cli_cmd))) {
                    try {
                        $this->system->IOData->src_output += $this->system->caller->runProgram(
                            $cmd_data,
                            $this->system->IOData->src_argv,
                            $this->system->IOData->cwd_path,
                            $this->system->app->core_debug
                        );
                    } catch (\Throwable $throwable) {
                        $this->system->error->exceptionHandler($throwable, false, $this->system->app->core_debug);
                        unset($throwable);
                    }
                }
            }
        }

        $cgi_cmd = $this->system->router->parseCgi($this->system->IOData->src_cmd);

        if (!empty($cgi_cmd)) {
            while (is_array($cmd_data = array_shift($cgi_cmd))) {
                try {
                    $full_cmd = strtr($cmd_data[0] . '/' . $cmd_data[1], '\\', '/');

                    if (!$this->system->hook->runBefore($full_cmd)) {
                        continue;
                    }

                    try {
                        $method_args = Factory::buildArgs(
                            Reflect::getMethod($cmd_data[0], $cmd_data[1])->getParameters(),
                            $this->system->IOData->src_input
                        );

                        $class_args = method_exists($cmd_data[0], '__construct')
                            ? Factory::buildArgs(Reflect::getMethod($cmd_data[0], '__construct')->getParameters(), $this->system->IOData->src_input)
                            : [];
                    } catch (\Throwable $throwable) {
                        if ($this->system->app->core_debug) {
                            http_response_code(500);
                            $this->system->IODataAddMsgData('ArgumentError', $throwable->getMessage());
                        }

                        unset($throwable);
                        continue;
                    }

                    $this->system->IOData->src_output += $this->system->caller->runMethod($cmd_data, $method_args, $class_args);

                    if (!$this->system->hook->runAfter($full_cmd)) {
                        break;
                    }
                } catch (\Throwable $throwable) {
                    $this->system->error->exceptionHandler($throwable, false, $this->system->app->core_debug);
                    unset($throwable);
                }
            }
        }

        $this->system->IOData->output();
    }
}