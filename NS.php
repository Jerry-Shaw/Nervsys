<?php

/**
 * Nervsys Entry Script
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

declare(strict_types = 1);

namespace Nervsys;

use Nervsys\Core\System;

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    exit('Nervsys 8.1+ needs PHP 8.1.0 or higher!');
}

set_time_limit(0);
ignore_user_abort(true);

define('NS_VER', '8.2.6');
define('NS_NAME', 'Blueberry');
define('NS_ROOT', __DIR__);
define('NS_NAMESPACE', __NAMESPACE__);
define('NS_HOSTNAME', gethostname() ?: 'localhost');

define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

spl_autoload_register(
    static function (string $class): void
    {
        $file_path = str_contains($class, '\\') ?
            __DIR__ . DIRECTORY_SEPARATOR . strtr(strstr($class, '\\'), '\\', DIRECTORY_SEPARATOR) . '.php'
            : $class . '.php';

        if (is_file($file_path)) {
            require $file_path;
        }

        unset($class, $file_path);
    },
    true,
    true
);

class NS
{
    use System;

    /**
     * NS constructor
     *
     * @throws \Exception
     */
    public function __construct(string $root_path = '', string $api_dir = '')
    {
        $this->init();

        if ('' !== $root_path) {
            $this->app->setRoot($root_path);
        }

        if ('' !== $api_dir) {
            $this->app->setApiDir($api_dir);
        }

        $this->app->initIOEnv()->initAppEnv();

        $this->initApp();
        $this->addAutoloadPath($this->app->root_path, true);

        unset($root_path, $api_dir);
    }

    /**
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function go(): void
    {
        date_default_timezone_set($this->app->timezone);

        $this->profiler->start('NS_DATA_READER');

        if (!$this->app->is_cli) {
            $this->CORS->checkPermission($this->app->is_tls);
            $this->IOData->readCgi();
        } else {
            $this->IOData->readCli();
        }

        $this->profiler->end('NS_DATA_READER');

        if ($this->app->is_cli) {
            $this->profiler->start('NS_CLI_ROUTER');
            $cli_cmd = $this->router->parseCli($this->IOData->src_cmd);
            $this->profiler->end('NS_CLI_ROUTER');

            if (!empty($cli_cmd)) {
                try {
                    $this->caller->runProgram(
                        $cli_cmd,
                        $this->IOData->src_argv,
                        $this->IOData->cwd_path,
                        $this->app->debug_mode
                    );
                } catch (\Throwable $throwable) {
                    $this->error->exceptionHandler($throwable);
                    unset($throwable);
                }
            }
        }

        $this->profiler->start('NS_CGI_ROUTER');
        $cgi_cmd = $this->router->parseCgi($this->IOData->src_cmd);
        $this->profiler->end('NS_CGI_ROUTER');

        if (!empty($cgi_cmd)) {
            try {
                $full_cmd = strtr($cgi_cmd[0] . '/' . $cgi_cmd[1], '\\', '/');

                $profiler_name = 'NS_HOOK_BEFORE@' . $full_cmd;
                $this->profiler->start($profiler_name);
                $pass_hook = $this->hook->run($full_cmd);
                $this->profiler->end($profiler_name);

                if ($pass_hook) {
                    $profiler_name = 'NS_API_CALLER@' . $full_cmd;
                    $this->profiler->start($profiler_name);
                    $this->IOData->src_output = $this->caller->runApiFn($cgi_cmd, $this->IOData->src_input, true);
                    $this->profiler->end($profiler_name);
                }
            } catch (\Throwable $throwable) {
                $this->error->exceptionHandler($throwable);
                unset($throwable);
            }
        }

        $this->profiler->start('NS_DATA_OUTPUT');
        $this->IOData->output();
        $this->profiler->end('NS_DATA_OUTPUT');
    }
}