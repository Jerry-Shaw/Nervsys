<?php

/**
 * Git operation library
 *
 * Copyright 2025 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;
use Nervsys\Core\Mgr\ProcMgr;

class libGit extends Factory
{
    public string $git_root;

    /**
     * @param string $path
     *
     * @return libGit
     */
    public function setPath(string $path): self
    {
        $this->git_root = $path;

        unset($path);
        return $this;
    }

    /**
     * Clone a Git repository.
     *
     * @param string $repo_url
     * @param string $local_path
     * @param bool   $with_submodule
     *
     * @throws \ReflectionException
     */
    public function clone(string $repo_url, string $local_path, bool $with_submodule = true): void
    {
        $command = ['clone', $repo_url, $local_path];

        if ($with_submodule) {
            $command[] = '--recurse-submodules';
        }

        unset($local_path, $with_submodule);
        $this->runCommand($command);
    }

    /**
     * Fetch changes from a remote repository.
     *
     * @throws \ReflectionException
     */
    public function fetch(): void
    {
        $this->runCommand(['fetch']);
    }

    /**
     * Checkout a branch or commit, or reset all changes if '.' is provided.
     *
     * @param string $branch_or_commit
     *
     * @throws \ReflectionException
     */
    public function checkout(string $branch_or_commit): void
    {
        if ('.' === $branch_or_commit) {
            $this->runCommand(['checkout', '--', '.']);
        } else {
            $this->runCommand(['checkout', $branch_or_commit]);
        }
    }

    /**
     * Pull changes from a remote repository.
     *
     * @param bool   $with_submodule
     * @param string $submodule_strategy
     *
     * @throws \ReflectionException
     */
    public function pull(bool $with_submodule = true, string $submodule_strategy = ''): void
    {
        if ($with_submodule) {
            $this->updateSubmodule($submodule_strategy);
        }

        $this->runCommand(['pull']);
    }

    /**
     * Reset the repository to a specific commit.
     *
     * @param string $commit_id
     * @param bool   $hard_reset
     *
     * @throws \ReflectionException
     */
    public function reset(string $commit_id, bool $hard_reset = true): void
    {
        $command = ['reset', $commit_id];

        if ($hard_reset) {
            $command[] = '--hard';
        }

        $this->runCommand($command);
    }

    /**
     * Show the status of the repository.
     *
     * @throws \ReflectionException
     */
    public function status(): void
    {
        $this->runCommand(['status']);
    }

    /**
     * Show the commit log.
     *
     * @throws \ReflectionException
     */
    public function log(): void
    {
        $this->runCommand(['log']);
    }

    /**
     * Show the reflog of the repository.
     *
     * @throws \ReflectionException
     */
    public function reflog(): void
    {
        $this->runCommand(['reflog']);
    }

    /**
     * Synchronize submodules with the remote repository.
     *
     * @throws \ReflectionException
     */
    public function syncSubmodule(): void
    {
        $this->runCommand(['submodule', 'sync']);
    }

    /**
     * Initialize submodules in the repository.
     *
     * @throws \ReflectionException
     */
    public function initSubmodule(): void
    {
        $this->runCommand(['submodule', 'init']);
    }

    /**
     * Update submodules using merge/rebase strategy.
     *
     * @throws \ReflectionException
     */
    public function updateSubmodule(string $strategy = ''): void
    {
        $command = ['submodule', 'update', '--remote'];

        if (in_array($strategy, ['merge', 'rebase'], true)) {
            $command[] = '--' . $strategy;
        }

        unset($strategy);
        $this->runCommand($command);
    }

    /**
     * Display output.
     *
     * @param string $msg
     */
    public function displayLog(string $msg): void
    {
        echo $msg . "<br>" . PHP_EOL;
        unset($msg);
    }

    /**
     * Execute Git command.
     *
     * @param array $commands
     *
     * @throws \ReflectionException
     * @throws \Exception
     */
    private function runCommand(array $commands): void
    {
        $proc_mgr = ProcMgr::new(['git', ...$commands]);

        $proc_mgr->readAt(0, 500)
            ->setWorkDir($this->git_root)
            ->runProc()
            ->awaitProc([$this, 'displayLog']);
    }
}