<?php

/**
 * Nervsys Module Manager - CLI Wrapper
 *
 * This class provides the same external interface as the original go class,
 * but internally delegates to the refactored core logic with real-time output.
 *
 * Copyright 2026 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\modules\manager;

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\App;
use Nervsys\modules\manager\lib\core;

class go extends Factory
{
    private App  $app;
    private core $core;

    /**
     * Constructor – initializes the CLI wrapper and checks Git.
     *
     * @param string $dirname Optional subdirectory under root; passed to core.
     *
     * @throws \Exception
     */
    public function __construct(string $dirname = '')
    {
        $this->app  = App::new();
        $this->core = new core($dirname);

        $git_result = $this->core->checkGit();

        if (!$git_result['success']) {
            $this->output('Error: ' . $git_result['message'], true, true);
        } else {
            $this->output('Git version: ' . $git_result['version'], true);
        }
    }

    /**
     * Set the remote Git source.
     *
     * @param string $repo Source host or URL
     *
     * @return void
     * @throws \Exception
     */
    public function set_remote(string $repo): void
    {
        $result = $this->core->setRemote($repo);
        $this->handleResult($result);
    }

    /**
     * Initialize a new module skeleton.
     *
     * @param string $repo Module name
     *
     * @return void
     * @throws \Exception
     */
    public function init(string $repo): void
    {
        $result = $this->core->init($repo);
        $this->handleResult($result);
    }

    /**
     * Install or update a module and its dependencies with real-time output.
     *
     * @param string $repo Module specifier
     *
     * @return void
     * @throws \Exception
     */
    public function install(string $repo): void
    {
        $output_callback = function (string $msg, bool $error = false)
        {
            $this->output($msg, false);
        };

        $result = $this->core->install($repo, 0, $output_callback);
        $this->handleResult($result);
    }

    /**
     * Handle the result array from the core and output accordingly.
     *
     * @param array $result
     *
     * @return void
     * @throws \Exception
     */
    private function handleResult(array $result): void
    {
        if ($result['success']) {
            $this->output($result['message'], true);

            if (isset($result['data'])) {
                $data = $result['data'];

                if (isset($data['path'])) {
                    $this->output('Path: ' . $data['path']);
                }

                if (isset($data['module'])) {
                    $this->output('Module: ' . $data['module']);
                }

                if (isset($data['source'])) {
                    $this->output('Source: ' . $data['source']);
                }

                if (isset($data['git_version']) && $data['git_version'] !== '') {
                    $this->output('Git version: ' . $data['git_version']);
                }

                if (isset($data['ssh_version']) && $data['ssh_version'] !== '') {
                    $this->output('SSH version: ' . $data['ssh_version']);
                }
            }
        } else {
            $this->output('Error: ' . $result['message'], true, true);
        }
    }

    /**
     * Output a message (CLI only) and optionally throw an exception.
     *
     * This method mimics the original go::output() behavior.
     *
     * @param string $message
     * @param bool   $empty_line
     * @param bool   $throw_exception
     *
     * @return void
     * @throws \Exception
     */
    public function output(string $message, bool $empty_line = false, bool $throw_exception = false): void
    {
        if ($this->app->is_cli) {
            echo $message . PHP_EOL;

            if ($empty_line) {
                echo PHP_EOL;
            }
        }

        if ($throw_exception) {
            throw new \Exception($message, E_USER_ERROR);
        }
    }
}