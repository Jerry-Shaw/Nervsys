<?php

/**
 * Nervsys Module Manager - Core Logic
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

namespace Nervsys\modules\manager\lib;

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\App;
use Nervsys\Core\Mgr\ProcMgr;
use Nervsys\Ext\libFileIO;

class core extends Factory
{
    public const  TAG_SEPARATOR  = '@';
    public const  TYPE_SEPARATOR = '#';

    private App     $app;
    private ProcMgr $ProcMgr;

    private int $max_depth = 10;

    private array  $local_env    = [];
    private string $module_root  = '';
    private string $config_file  = '';
    private string $git_version  = '';
    private string $ssh_version  = '';
    private string $ssh_key_path = '';

    /**
     * @param string $dirname
     *
     * @throws \ReflectionException
     */
    public function __construct(string $dirname = '')
    {
        $this->app = App::new();

        $this->module_root = $this->app->root_path . DIRECTORY_SEPARATOR
            . ('' !== $dirname ? $dirname : $this->app->api_dir)
            . DIRECTORY_SEPARATOR;

        if (!is_dir($this->module_root)) {
            try {
                mkdir($this->module_root, 0777, true);
            } catch (\Throwable) {
            }
        }

        $this->ProcMgr = ProcMgr::new()->setWorkDir($this->module_root);

        $local_config_file = __DIR__ . DIRECTORY_SEPARATOR . 'local.json';
        $this->config_file = $this->module_root . '.mm';

        if (!is_file($this->config_file)) {
            copy($local_config_file, $this->config_file);
        }

        $this->loadConfig();

        $ssh_info           = $this->detectSsh();
        $this->ssh_key_path = $ssh_info['path'];
        $this->ssh_version  = $ssh_info['version'];

        unset($dirname, $local_config_file, $ssh_info);
    }

    /**
     * @param string $repo
     *
     * @return array
     */
    public function setRemote(string $repo): array
    {
        if (!str_contains($repo, '://')) {
            $repo = 'https://' . $repo;
        }

        $host      = parse_url($repo, PHP_URL_HOST);
        $supported = array_keys($this->local_env['git_platforms']);

        if (!in_array($host, $supported, true)) {
            return [
                'success' => false,
                'message' => 'Source "' . $repo . '" not supported. Allowed: ' . implode(', ', $supported)
            ];
        }

        $this->local_env['git_source'] = $host;

        $save_result = $this->saveConfig();

        if (!$save_result['success']) {
            return $save_result;
        }

        $result = [
            'success' => true,
            'message' => 'Remote source set to ' . $host,
            'data'    => [
                'source' => $host
            ],
        ];

        unset($repo, $host, $supported, $save_result);
        return $result;
    }

    /**
     * @param string $name
     *
     * @return array
     */
    public function init(string $name): array
    {
        $src = __DIR__ . DIRECTORY_SEPARATOR . 'demo_module';
        $dst = $this->module_root . DIRECTORY_SEPARATOR . $name;

        try {
            $libFileIO = libFileIO::new();
            $libFileIO->copyDir($src, $dst);
        } catch (\Throwable $throwable) {
            return [
                'success' => false,
                'message' => 'Failed to create module: ' . $throwable->getMessage()
            ];
        }

        $result = [
            'success' => true,
            'message' => 'Module "' . $name . '" created at ' . $dst,
            'data'    => [
                'path'        => $dst,
                'git_version' => $this->git_version,
                'ssh_version' => $this->ssh_version,
            ],
        ];

        unset($name, $src, $dst, $libFileIO);
        return $result;
    }

    /**
     * @param string        $spec
     * @param int           $depth
     * @param callable|null $output
     *
     * @return array
     * @throws \ReflectionException
     */
    public function install(string $spec, int $depth = 0, callable|null $output = null): array
    {
        if ($depth > $this->max_depth) {
            $msg = 'Maximum dependency depth (' . $this->max_depth . ') exceeded.';

            if (is_callable($output)) {
                $output($msg, true);
            }

            return [
                'success' => false,
                'message' => $msg,
            ];
        }

        $git = $this->checkGit($output);
        if (!$git['success']) {
            return $git;
        }
        unset($git);

        $parsed = $this->parseUserRepo($spec, $output);
        if (!$parsed['success']) {
            return $parsed;
        }

        $this->ProcMgr->setWorkDir($this->module_root);
        $metadata = $this->getModuleMeta($parsed['data']['repo']);

        $install_result = empty($metadata)
            ? $this->installModule(
                $parsed['data']['repo'],
                $parsed['data']['user'],
                $parsed['data']['type'],
                $parsed['data']['tag'],
                $depth,
                $output
            )
            : $this->updateModule(
                $parsed['data']['repo'],
                $metadata,
                $parsed['data']['tag'],
                $depth,
                $output
            );

        if (!$install_result['success']) {
            return $install_result;
        }

        unset($install_result);

        $msg = 'Module "' . $parsed['data']['repo'] . '" installed/updated successfully.';

        if (is_callable($output)) {
            $output($msg, true);
        }

        $result = [
            'success' => true,
            'message' => $msg,
            'data'    => [
                'module'      => $parsed['data']['repo'],
                'path'        => $this->module_root . $parsed['data']['repo'],
                'metadata'    => $this->getModuleMeta($parsed['data']['repo']),
                'git_version' => $this->git_version,
                'ssh_version' => $this->ssh_version,
            ],
        ];

        unset($spec, $depth, $output, $parsed, $metadata, $msg);
        return $result;
    }

    /**
     * @param callable|null $output
     *
     * @return array
     * @throws \ReflectionException
     */
    public function checkGit(callable|null $output = null): array
    {
        if ('' !== $this->git_version) {
            if (is_callable($output)) {
                $output('Git found, version: ' . $this->git_version);
            }

            return [
                'success' => true,
                'message' => 'Git is available.',
                'version' => $this->git_version,
            ];
        }

        $exec_result = $this->execGitCommand(['git', '-v']);

        if (!$exec_result['success']) {
            $msg = 'Git not found. Please install Git and add it to your PATH.';

            if (is_callable($output)) {
                $output($msg, true);
            }

            return [
                'success' => false,
                'message' => $msg,
                'version' => '',
            ];
        }

        $version     = '';
        $output_text = $exec_result['output'] ?? '';

        if (str_starts_with($output_text, 'git version ')) {
            $version = trim(substr($output_text, strlen('git version ')));
        }

        if ('' === $version) {
            $msg = 'Unable to determine Git version.';

            if (is_callable($output)) {
                $output($msg, true);
            }

            return [
                'success' => false,
                'message' => $msg,
                'version' => '',
            ];
        }

        $this->git_version = $version;

        if (is_callable($output)) {
            $output('Git found, version: ' . $version, true);
        }

        $result = [
            'success' => true,
            'message' => 'Git version: ' . $version,
            'version' => $version
        ];

        unset($output, $exec_result, $version, $output_text, $msg);
        return $result;
    }

    /**
     * @return void
     */
    private function loadConfig(): void
    {
        $content  = file_get_contents($this->config_file);
        $env_data = (false !== $content) ? json_decode($content, true) : null;

        $this->local_env = is_array($env_data) ? $env_data : [];

        if (!isset($this->local_env['git_source'])) {
            $this->local_env['git_source'] = 'github.com';
        }

        if (!isset($this->local_env['git_platforms']) || !is_array($this->local_env['git_platforms'])) {
            $this->local_env['git_platforms'] = [];
        }

        unset($content, $env_data);
    }

    /**
     * @return array
     */
    private function saveConfig(): array
    {
        $json = json_encode($this->local_env, JSON_PRETTY);

        if (false === $json) {
            return ['success' => false, 'message' => 'Failed to encode configuration.'];
        }

        $write_result = file_put_contents($this->config_file, $json);

        if (false === $write_result) {
            return ['success' => false, 'message' => 'Failed to write configuration file.'];
        }

        $result = ['success' => true, 'message' => 'Configuration written to ' . $this->config_file];

        unset($json, $write_result);
        return $result;
    }

    /**
     * @param array  $cmd
     * @param string $cwd
     *
     * @return array
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function execGitCommand(array $cmd, string $cwd = ''): array
    {
        $proc = $this->ProcMgr->command($cmd);

        if ('' !== $cwd) {
            $proc->setWorkDir($cwd);
        }

        $proc_idx = $proc->run(getmypid());

        $stdout = '';
        $stderr = '';

        $exit_code = $this->ProcMgr->awaitProc(
            $proc_idx,
            function (string $output) use (&$stdout): void
            {
                $stdout .= $output;
                unset($output);
            },
            function (string $output) use (&$stderr): void
            {
                $stderr .= $output;
                unset($output);
            }
        );

        $result = ['success' => (0 === $exit_code), 'output' => $stdout, 'error' => $stderr];

        unset($cmd, $cwd, $proc, $proc_idx, $stdout, $stderr, $exit_code);
        return $result;
    }

    /**
     * @param string        $user_repo
     * @param callable|null $output
     *
     * @return array
     */
    private function parseUserRepo(string $user_repo, callable|null $output = null): array
    {
        if (!str_contains($user_repo, '/')) {
            $msg = 'Invalid format. Expected "{user}/{repo}[@tag][#https|#git]"';

            if (is_callable($output)) {
                $output($msg, true);
            }

            return ['success' => false, 'message' => $msg];
        }

        $tag  = '';
        $type = 'https';

        $pos_tag  = strpos($user_repo, self::TAG_SEPARATOR);
        $pos_type = strpos($user_repo, self::TYPE_SEPARATOR);

        if (false !== $pos_tag && false !== $pos_type) {
            if ($pos_tag >= $pos_type) {
                $msg = 'Tag must appear before source type (#).';

                if (is_callable($output)) {
                    $output($msg, true);
                }

                return ['success' => false, 'message' => $msg];
            }

            $tag       = substr($user_repo, $pos_tag + 1, $pos_type - $pos_tag - 1);
            $type      = substr($user_repo, $pos_type + 1);
            $user_repo = substr($user_repo, 0, $pos_tag);
        } elseif (false !== $pos_tag) {
            $tag       = substr($user_repo, $pos_tag + 1);
            $user_repo = substr($user_repo, 0, $pos_tag);
        } elseif (false !== $pos_type) {
            $type      = substr($user_repo, $pos_type + 1);
            $user_repo = substr($user_repo, 0, $pos_type);
        }

        if (!in_array($type, ['https', 'git'], true)) {
            $msg = 'Invalid source type. Use "https" or "git".';

            if (is_callable($output)) {
                $output($msg, true);
            }

            return ['success' => false, 'message' => $msg];
        }

        [$user, $repo] = explode('/', $user_repo);

        $result = [
            'success' => true,
            'data'    => [
                'user' => $user,
                'repo' => $repo,
                'tag'  => $tag,
                'type' => $type
            ]
        ];

        unset($user_repo, $output, $tag, $type, $pos_tag, $pos_type, $user, $repo);
        return $result;
    }

    /**
     * @param string $user
     * @param string $repo
     * @param string $type
     *
     * @return string  Git URL or empty string on failure
     */
    private function buildGitUrl(string $user, string $repo, string $type): string
    {
        $source    = $this->local_env['git_source'] ?? 'github.com';
        $platforms = $this->local_env['git_platforms'] ?? [];

        if (!isset($platforms[$source])) {
            return '';
        }

        $key = ('git' === $type) ? 'ssh_url' : 'https_url';
        $url = $platforms[$source][$key] ?? null;

        if (is_null($url)) {
            return '';
        }

        $result = str_replace(['{user}', '{repo}'], [$user, $repo], $url);

        unset($user, $repo, $type, $source, $platforms, $key, $url);
        return $result;
    }

    /**
     * @param string        $repo
     * @param string        $user
     * @param string        $type
     * @param string        $tag
     * @param int           $depth
     * @param callable|null $output
     *
     * @return array
     * @throws \ReflectionException
     */
    private function installModule(string $repo, string $user, string $type, string $tag, int $depth, callable|null $output = null): array
    {
        $url = $this->buildGitUrl($user, $repo, $type);

        if ('' === $url) {
            $msg = 'No Git URL configured for source: ' . ($this->local_env['git_source'] ?? 'unknown');

            if (is_callable($output)) {
                $output($msg, true);
            }

            return ['success' => false, 'message' => $msg];
        }

        $ssh = $this->setupSshEnv($url);
        if (!$ssh['success']) {
            return $ssh;
        }
        unset($ssh);

        if (is_callable($output)) {
            $output('Installing module "' . $repo . '" from ' . $url . ('' !== $tag ? ' (tag: ' . $tag . ')' : ''));
        }

        $cmd = ['git', 'clone'];

        if ('' !== $tag) {
            $cmd[] = '-b';
            $cmd[] = $tag;
        }

        $cmd[] = $url;
        $cmd[] = $this->module_root . $repo;

        $git_result = $this->execGitCommand($cmd);

        if (!$git_result['success']) {
            $msg = 'Clone failed for ' . $repo . ': ' . $git_result['error'];

            if (is_callable($output)) {
                $output($msg, true);
            }

            return ['success' => false, 'message' => $msg];
        }

        $metadata = $this->getModuleMeta($repo);

        if (!empty($metadata['dependencies'])) {
            $dep_result = $this->installDependencies($metadata['dependencies'], $depth + 1, $output);

            if (!$dep_result['success']) {
                return $dep_result;
            }

            unset($dep_result);
        }

        $result = ['success' => true, 'message' => 'Module "' . $repo . '" installed'];

        unset($repo, $user, $type, $tag, $depth, $output, $url, $msg, $cmd, $git_result, $metadata);
        return $result;
    }

    /**
     * @param string        $repo
     * @param array         $metadata
     * @param string        $tag
     * @param int           $depth
     * @param callable|null $output
     *
     * @return array
     * @throws \ReflectionException
     */
    private function updateModule(string $repo, array $metadata, string $tag, int $depth, callable|null $output = null): array
    {
        $metadata['repo']    ??= '';
        $metadata['version'] ??= '';

        if ('' !== $metadata['repo'] && ('' === $tag || $tag !== $metadata['version'])) {
            if (is_callable($output)) {
                if ('' !== $tag) {
                    $output('Updating module "' . $repo . '" to tag "' . $tag . '"');
                } else {
                    $output('Updating module "' . $repo . '" to latest version');
                }
            }

            $pos = strpos($metadata['repo'], self::TAG_SEPARATOR);
            $url = (false !== $pos) ? substr($metadata['repo'], 0, $pos) : $metadata['repo'];

            $ssh = $this->setupSshEnv($url);
            if (!$ssh['success']) {
                return $ssh;
            }
            unset($ssh);

            $path = $this->module_root . $repo;

            $git_result = $this->execGitCommand(['git', 'fetch', 'origin'], $path);
            if (!$git_result['success']) {
                $msg = 'Fetch failed: ' . $git_result['error'];

                if (is_callable($output)) {
                    $output($msg, true);
                }

                return ['success' => false, 'message' => $msg];
            }
            unset($git_result);

            if ('' !== $tag) {
                $git_result = $this->execGitCommand(['git', 'checkout', $tag], $path);

                if (!$git_result['success']) {
                    $msg = 'Checkout failed: ' . $git_result['error'];

                    if (is_callable($output)) {
                        $output($msg, true);
                    }

                    return ['success' => false, 'message' => $msg];
                }

                unset($git_result);
            }

            $git_result = $this->execGitCommand(['git', 'pull'], $path);

            if (!$git_result['success']) {
                $msg = 'Pull failed: ' . $git_result['error'];

                if (is_callable($output)) {
                    $output($msg, true);
                }

                return ['success' => false, 'message' => $msg];
            }

            unset($git_result, $path);
        }

        if (!empty($metadata['dependencies'])) {
            $dep_result = $this->installDependencies($metadata['dependencies'], $depth + 1, $output);

            if (!$dep_result['success']) {
                return $dep_result;
            }

            unset($dep_result);
        }

        $result = ['success' => true, 'message' => 'Module updated successfully'];

        unset($repo, $metadata, $tag, $depth, $output, $pos, $url, $path, $msg);
        return $result;
    }

    /**
     * @param array         $dependencies
     * @param int           $depth
     * @param callable|null $output
     *
     * @return array
     * @throws \ReflectionException
     */
    private function installDependencies(array $dependencies, int $depth, callable|null $output = null): array
    {
        foreach ($dependencies as $repo => $dependency) {
            [$url, $tag] = str_contains($dependency, self::TAG_SEPARATOR)
                ? explode(self::TAG_SEPARATOR, $dependency, 2)
                : [$dependency, ''];

            $metadata = $this->getModuleMeta($repo);

            if (empty($metadata)) {
                if (is_callable($output)) {
                    $output('Installing dependency "' . $repo . '" from ' . $url . ('' !== $tag ? ' (tag: ' . $tag . ')' : ''));
                }

                $ssh = $this->setupSshEnv($url);
                if (!$ssh['success']) {
                    return $ssh;
                }
                unset($ssh);

                $cmd = ['git', 'clone'];

                if ('' !== $tag) {
                    $cmd[] = '-b';
                    $cmd[] = $tag;
                }

                $cmd[] = $url;
                $cmd[] = $this->module_root . $repo;

                $git_result = $this->execGitCommand($cmd);

                if (!$git_result['success']) {
                    $msg = 'Clone failed for ' . $repo . ': ' . $git_result['error'];

                    if (is_callable($output)) {
                        $output($msg, true);
                    }

                    return ['success' => false, 'message' => $msg];
                }

                unset($cmd, $git_result);

                $metadata = $this->getModuleMeta($repo);
            }

            if (!empty($metadata['dependencies'])) {
                $dep_result = $this->installDependencies($metadata['dependencies'], $depth + 1, $output);

                if (!$dep_result['success']) {
                    return $dep_result;
                }

                unset($dep_result);
            }
        }

        $result = ['success' => true, 'message' => 'Dependencies successfully installed'];

        unset($dependencies, $depth, $output, $repo, $dependency, $url, $tag, $metadata);
        return $result;
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

        $content = file_get_contents($meta_file);
        if (false === $content) {
            return [];
        }

        $metadata = json_decode($content, true);
        if (!is_array($metadata)) {
            return [];
        }

        if (!isset($metadata['name']) || $metadata['name'] !== $repo) {
            return [];
        }

        if (!isset($metadata['entry']) || !is_file($module_path . DIRECTORY_SEPARATOR . $metadata['entry'])) {
            return [];
        }

        unset($repo, $module_path, $meta_file, $content);
        return $metadata;
    }

    /**
     * @return array|string[]
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function detectSsh(): array
    {
        $user_home = getenv('WINNT' === PHP_OS ? 'USERPROFILE' : 'HOME');
        $key_path  = $user_home . DIRECTORY_SEPARATOR . '.ssh' . DIRECTORY_SEPARATOR . 'id_rsa';

        if (!is_file($key_path)) {
            return ['path' => '', 'version' => ''];
        }

        try {
            chmod($key_path, 0600);
        } catch (\Throwable) {
        }

        $ssh_ver  = '';
        $proc_idx = $this->ProcMgr->command(['ssh', '-V'])->run(getmypid());

        $this->ProcMgr->awaitProc(
            $proc_idx,
            function (string $output) use (&$ssh_ver): void
            {
                if (str_starts_with($output, 'OpenSSH_')) {
                    $ssh_ver = $output;
                }

                unset($output);
            },
            null
        );

        $result = ['path' => $key_path, 'version' => $ssh_ver];

        unset($user_home, $key_path, $ssh_ver, $proc_idx);
        return $result;
    }

    /**
     * @param string $url
     *
     * @return array
     */
    private function setupSshEnv(string $url): array
    {
        if (!str_starts_with($url, 'git@')) {
            putenv('GIT_SSH_COMMAND=');
            return ['success' => true, 'message' => ''];
        }

        if ('' === $this->ssh_key_path) {
            return ['success' => false, 'message' => 'SSH key not found for ' . $url];
        }

        putenv('GIT_SSH_COMMAND=ssh -T -i ' . escapeshellarg($this->ssh_key_path) . ' -o StrictHostKeyChecking=no');

        $result = ['success' => true, 'message' => 'SSH key found for ' . $url];

        unset($url);
        return $result;
    }
}