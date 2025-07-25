<?php

/**
 * OS Manager library
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;
use Nervsys\Core\OSC;

class OSMgr extends Factory
{
    /** @var OSC\Linux|OSC\WINNT|OSC\Darwin $lib_os */
    protected object $lib_os;

    public array $ip_v4 = [];
    public array $ip_v6 = [];

    public string $hw_hash  = '';
    public string $php_path = '';

    private bool $in_background = false;
    private bool $use_profile   = false;

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->lib_os = parent::getObj(NS_NAMESPACE . '\\Core\\OSC\\' . PHP_OS);
    }

    /**
     * @return array
     */
    public function getIPv4(): array
    {
        if (empty($this->ip_v4)) {
            $this->ip_v4 = $this->lib_os->getIPv4();
        }

        return $this->ip_v4;
    }

    /**
     * @return array
     */
    public function getIPv6(): array
    {
        if (empty($this->ip_v6)) {
            $this->ip_v6 = $this->lib_os->getIPv6();
        }

        return $this->ip_v6;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        if ('' === $this->hw_hash) {
            $this->hw_hash = $this->lib_os->getHwHash();
        }

        return $this->hw_hash;
    }

    /**
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        if ('' === $this->php_path) {
            $this->php_path = $this->lib_os->getPhpPath();
        }

        return $this->php_path;
    }

    /**
     * @param int $pid
     *
     * @return void
     */
    public function killProc(int $pid): void
    {
        $this->lib_os->killProc($pid);
    }

    /**
     * @param bool $in_background
     *
     * @return $this
     */
    public function inBackground(bool $in_background): self
    {
        $this->in_background = &$in_background;

        unset($in_background);
        return $this;
    }

    /**
     * @param bool $use_profile
     *
     * @return $this
     */
    public function useProfile(bool $use_profile): self
    {
        $this->use_profile = &$use_profile;

        unset($use_profile);
        return $this;
    }

    /**
     * @param string $command
     *
     * @return string
     */
    public function buildCmd(string $command): string
    {
        if ($this->in_background) {
            $command = $this->lib_os->buildBackgroundCmd($command);
        }

        if ($this->use_profile) {
            $command = $this->lib_os->runWithProfile($command);
        }

        return $command;
    }

    /**
     * @param string $command
     * @param string $separator
     *
     * @return string[]
     */
    public function buildProcCmd(string $command, string $separator = "\n"): array
    {
        $proc_command = str_contains($command, $separator)
            ? explode($separator, $command)
            : [$command];

        unset($command, $separator);
        return $proc_command;
    }
}