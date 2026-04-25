<?php

/**
 * Nervsys Module Manager
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
use Nervsys\Core\Mgr\ProcMgr;

class go extends Factory
{
    public App     $app;
    public ProcMgr $procMgr;

    public array $local_env = [];

    public string $config_file = '';
    public string $module_root = '';

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $git_ver   = null;
        $this->app = App::new();

        $this->config_file = __DIR__ . DIRECTORY_SEPARATOR . 'local.json';
        $this->module_root = $this->app->root_path . DIRECTORY_SEPARATOR . $this->app->api_dir . DIRECTORY_SEPARATOR;
        $this->procMgr     = ProcMgr::new()->setWorkDir($this->module_root);

        $this->procMgr->command(['git', '-v'])->run();

        $exit_code = $this->procMgr->awaitProc(
            function (string $output) use (&$git_ver): void
            {
                if (str_starts_with($output, 'git version')) {
                    $git_ver = substr($output, strlen('git version') + 1);
                }

                unset($output);
            }
        );

        if (0 !== $exit_code || is_null($git_ver)) {
            throw new \Exception('Git not found. Please install Git and add it to your PATH environment variable.', E_USER_WARNING);
        }

        $this->output('Git version: ' . $git_ver, true);

        $env_data = json_decode(file_get_contents($this->config_file), true);

        if (is_array($env_data)) {
            if (!isset($env_data['git_source'])) {
                $env_data['git_source'] = 'github.com';
            }

            $this->local_env = $env_data;
        }

        unset($git_ver, $exit_code, $env_data);
    }

    /**
     * @param string $source
     *
     * @return self
     * @throws \Exception
     */
    public function setSource(string $source): self
    {
        if (!str_contains($source, '://')) {
            $source = 'https://' . $source;
        }

        $source_host  = parse_url($source, PHP_URL_HOST);
        $support_host = array_keys($this->local_env['git_platforms']);

        if (!in_array($source_host, $support_host, true)) {
            throw new \Exception('Source url "' . $source . '" is not supported. Supported hosts are: ' . implode(', ', $support_host), E_USER_WARNING);
        }

        $this->local_env['git_source'] = $source_host;
        $this->saveEnv();

        unset($source, $source_host, $support_host);
    }

    /**
     * @param string $user_repo
     * @param string $tag
     * @param string $root
     *
     * @return void
     * @throws \ReflectionException
     */
    public function install(string $user_repo, string $tag = '', string $root = ''): void
    {
        if ($this->app->is_cli && '' !== $root) {
            $this->module_root = rtrim($root, '\\/') . DIRECTORY_SEPARATOR;
            $this->procMgr->setWorkDir($this->module_root);
        }

        [$user, $repo] = explode('/', $user_repo);
        $metadata = $this->getModuleMeta($repo);

        if (empty($metadata)) {
            $this->output('Installing ' . $repo . '...');

            $git_url = $this->local_env['git_platforms'][$this->local_env['git_source']]['git_url'];
            $git_url = str_replace('{user}', $user, $git_url);
            $git_url = str_replace('{repo}', $repo, $git_url);

            $this->installUrl($repo, $git_url, $tag);
            unset($git_url);
        } else {
            $this->output($repo . ' already exists. Checking dependencies...');

            if (isset($metadata['dependencies']) && !empty($metadata['dependencies'])) {
                $this->installDependencies($metadata['dependencies']);
            }
        }

        unset($user_repo, $tag, $user, $repo, $metadata);
    }

    /**
     * @param string $repo
     * @param string $url
     * @param string $tag
     *
     * @return void
     * @throws \ReflectionException
     */
    public function installUrl(string $repo, string $url, string $tag = ''): void
    {
        $metadata = $this->getModuleMeta($repo);

        if (empty($metadata)) {
            $command = ['git', 'clone'];

            if ('' !== $tag) {
                $command[] = '-b';
                $command[] = $tag;
            }

            $command[] = $url;
            $command[] = $this->module_root . $repo;

            $this->procMgr
                ->command($command)
                ->run();

            $exit_code = $this->procMgr->awaitProc([$this, 'output'], [$this, 'output']);

            if (0 !== $exit_code) {
                $this->output('Failed to install ' . $repo);
                return;
            }

            $metadata = $this->getModuleMeta($repo);
            unset($command, $exit_code);
        }

        if (isset($metadata['dependencies']) && !empty($metadata['dependencies'])) {
            $this->installDependencies($metadata['dependencies']);
        }

        unset($repo, $url, $tag, $metadata);
    }

    /**
     * @param array $repo_dependencies
     *
     * @return void
     * @throws \ReflectionException
     */
    public function installDependencies(array $repo_dependencies): void
    {
        foreach ($repo_dependencies as $repo => $dependency) {
            [$url, $tag] = str_contains($dependency, '#')
                ? explode('#', $dependency)
                : [$dependency, ''];

            $this->installUrl($repo, $url, $tag);
        }

        unset($repo_dependencies, $repo, $dependency, $url, $tag);
    }

    /**
     * @param string $message
     * @param bool   $empty_line
     *
     * @return void
     */
    public function output(string $message, bool $empty_line = false): void
    {
        echo $message . PHP_EOL;

        if ($empty_line) {
            echo PHP_EOL;
        }

        unset($message, $empty_line);
    }

    /**
     * @param string $repo
     *
     * @return array
     */
    private function getModuleMeta(string $repo): array
    {
        $module_path = $this->module_root . $repo;
        $meta_file   = $module_path . DIRECTORY_SEPARATOR . 'module.json';

        if (!is_file($meta_file)) {
            return [];
        }

        $metadata = json_decode(file_get_contents($meta_file), true);

        if (!isset($metadata['name']) || $metadata['name'] !== $repo) {
            return [];
        }

        if (!isset($metadata['entry']) || !is_file($module_path . DIRECTORY_SEPARATOR . $metadata['entry'])) {
            return [];
        }

        unset($repo, $module_path, $meta_file);
        return $metadata;
    }

    /**
     * @return void
     */
    private function saveEnv(): void
    {
        file_put_contents($this->config_file, json_encode($this->local_env, JSON_FORMAT));
    }
}