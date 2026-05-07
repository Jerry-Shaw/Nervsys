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
use Nervsys\Ext\libFileIO;

class go extends Factory
{
    const TAG_SEPARATOR  = '@';
    const TYPE_SEPARATOR = '#';

    public App     $app;
    public ProcMgr $procMgr;

    public array $local_env = [];

    public string $ssk_key = '';

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

        try {
            mkdir($this->module_root, 0777, true);
        } catch (\Exception) {
            // Module path exists
        }

        $this->procMgr = ProcMgr::new()->setWorkDir($this->module_root);

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
            $this->output('Git not found. Please install Git and add it to your PATH environment variable.', true, true);
            return;
        }

        $this->output('Git version: ' . $git_ver, true);

        $env_data = json_decode(file_get_contents($this->config_file), true);

        if (is_array($env_data)) {
            if (!isset($env_data['git_source'])) {
                $env_data['git_source'] = 'github.com';
            }

            $this->local_env = $env_data;
        }

        $this->ssk_key = $this->findSshKey();

        unset($git_ver, $exit_code, $env_data);
    }

    /**
     * @param string $repo
     *
     * @return self
     * @throws \Exception
     */
    public function setRemote(string $repo): self
    {
        if (!str_contains($repo, '://')) {
            $repo = 'https://' . $repo;
        }

        $source_host  = parse_url($repo, PHP_URL_HOST);
        $support_host = array_keys($this->local_env['git_platforms']);

        if (!in_array($source_host, $support_host, true)) {
            $this->output('Source url "' . $repo . '" is not supported. Supported hosts are: ' . implode(', ', $support_host), true, true);
            return $this;
        }

        $this->local_env['git_source'] = $source_host;
        $this->saveEnv();

        unset($repo, $source_host, $support_host);
        return $this;
    }

    /**
     * @param string $repo
     * @param string $root
     *
     * @return void
     * @throws \ReflectionException
     */
    public function init(string $repo, string $root = ''): void
    {
        $libFileIO = libFileIO::new();

        $src_path = __DIR__ . DIRECTORY_SEPARATOR . 'demo_module';
        $dst_path = $root . DIRECTORY_SEPARATOR . $repo;

        $libFileIO->copyDir($src_path, $dst_path);

        $this->output('Module "' . $repo . '" created successfully at: ' . $dst_path);
        $this->output('Next: Edit module.json → Write code in go.php → See README.md for details');

        unset($repo, $root, $libFileIO, $src_path, $dst_path);
    }

    /**
     * @param string $repo
     * @param string $root
     *
     * @return void
     * @throws \ReflectionException
     */
    public function install(string $repo, string $root = ''): void
    {
        if (!str_contains($repo, '/')) {
            $this->output('Invalid repository format. Expected "{user}/{repo}", "{user}/{repo}{@tag}", "{user}/{repo}{#source: https/git}" or "{user}/{repo}{@tag}{#source: https/git}".', true, true);
            return;
        }

        $tag  = '';
        $type = 'https';

        $pos_tag  = strpos($repo, self::TAG_SEPARATOR);
        $pos_type = strpos($repo, self::TYPE_SEPARATOR);

        if (false !== $pos_tag && false !== $pos_type) {
            if ($pos_tag >= $pos_type) {
                $this->output('Invalid repository format. Expected "{user}/{repo}{@tag}{#source: https/git}" with tag before source.', true, true);
                return;
            }

            $tag  = substr($repo, $pos_tag + 1, $pos_type - $pos_tag - 1);
            $type = substr($repo, $pos_type + 1);
            $repo = substr($repo, 0, $pos_tag);
        } elseif (false !== $pos_tag) {
            $tag  = substr($repo, $pos_tag + 1);
            $repo = substr($repo, 0, $pos_tag);
        } elseif (false !== $pos_type) {
            $type = substr($repo, $pos_type + 1);
            $repo = substr($repo, 0, $pos_type);
        }

        if ($this->app->is_cli && '' !== $root) {
            $this->module_root = rtrim($root, '\\/') . DIRECTORY_SEPARATOR;
            $this->procMgr->setWorkDir($this->module_root);
        }

        [$user_name, $repo_name] = explode('/', $repo);
        $metadata = $this->getModuleMeta($repo_name);

        if (empty($metadata)) {
            $git_url = $this->local_env['git_platforms'][$this->local_env['git_source']]['git' === $type ? 'ssh_url' : 'https_url'];

            $git_url = str_replace('{user}', $user_name, $git_url);
            $git_url = str_replace('{repo}', $repo_name, $git_url);

            $this->output('Installing "' . $repo_name . '" from ' . $git_url);

            $this->installUrl($repo_name, $git_url, $tag);
            unset($git_url);
        } else {
            $this->output($repo_name . ' already exists. Checking dependencies...');

            if (isset($metadata['dependencies']) && !empty($metadata['dependencies'])) {
                $this->installDependencies($metadata['dependencies']);
            }
        }

        unset($repo, $root, $tag, $type, $pos_tag, $pos_type, $user_name, $repo_name, $metadata);
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
            if (str_starts_with($url, 'git@')) {
                if ('' === $this->ssk_key) {
                    $this->output('SSH Key NOT found, or, OpenSSH NOT installed.');
                    $this->output('Skip installing "' . $repo . '" from ' . $url);

                    unset($repo, $url, $tag, $metadata);
                    return;
                }

                putenv('GIT_SSH_COMMAND=ssh -T -i ' . escapeshellarg($this->ssk_key) . ' -o StrictHostKeyChecking=no');
            } else {
                putenv('GIT_SSH_COMMAND=');
            }

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
            [$url, $tag] = str_contains($dependency, self::TAG_SEPARATOR)
                ? explode(self::TAG_SEPARATOR, $dependency)
                : [$dependency, ''];

            $this->installUrl($repo, $url, $tag);
        }

        unset($repo_dependencies, $repo, $dependency, $url, $tag);
    }

    /**
     * @param string $message
     * @param bool   $empty_line
     * @param bool   $throw_exception
     *
     * @return void
     * @throws \Exception
     */
    public function output(string $message, bool $empty_line = false, bool $throw_exception = false): void
    {
        echo $message . PHP_EOL;

        if ($empty_line) {
            echo PHP_EOL;
        }

        if ($throw_exception) {
            throw new \Exception($message, E_USER_ERROR);
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
     * @return string
     * @throws \ReflectionException
     */
    private function findSshKey(): string
    {
        $user_home    = getenv('WINNT' === PHP_OS ? 'USERPROFILE' : 'HOME');
        $ssh_key_path = $user_home . DIRECTORY_SEPARATOR . '.ssh' . DIRECTORY_SEPARATOR . 'id_rsa';

        if (!is_file($ssh_key_path)) {
            $this->output('SSH Key NOT found.');
            return '';
        }

        $this->output('SSH Key found: ' . $ssh_key_path);

        $ssh_ver = null;
        chmod($ssh_key_path, 0600);

        $findSshVer = function (string $output) use (&$ssh_ver): void
        {
            if (str_starts_with($output, 'OpenSSH_')) {
                $ssh_ver = $output;
            }

            unset($output);
        };

        $this->procMgr->command(['ssh', '-V'])->run();

        $exit_code = $this->procMgr->awaitProc($findSshVer, $findSshVer);

        if (0 !== $exit_code || is_null($ssh_ver)) {
            $this->output('OpenSSH not installed.');
            $ssh_key_path = '';
        }

        $this->output('OpenSSH version: ' . $ssh_ver, true);

        unset($user_home, $ssh_ver, $findSshVer, $exit_code);
        return $ssh_key_path;
    }

    /**
     * @return void
     */
    private function saveEnv(): void
    {
        file_put_contents($this->config_file, json_encode($this->local_env, JSON_FORMAT));
    }
}