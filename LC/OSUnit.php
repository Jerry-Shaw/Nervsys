<?php

/**
 * OSUnit library
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

namespace Nervsys\LC;

class OSUnit extends Factory
{
    public string $php_os;
    public string $hw_hash  = '';
    public string $php_path = '';

    /** @var OS\Linux|OS\WINNT|OS\Darwin $lib_os */
    protected object $lib_os;

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->php_os = PHP_OS;
        $this->lib_os = parent::getObj(__NAMESPACE__ . '\\OS\\' . $this->php_os);
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
     * @param string $cmd
     *
     * @return $this
     */
    public function setCmd(string $cmd): self
    {
        $this->lib_os->os_cmd = &$cmd;

        unset($cmd);
        return $this;
    }

    /**
     * @return string
     */
    public function fetchCmd(): string
    {
        return $this->lib_os->os_cmd;
    }

    /**
     * @param int $return_var
     *
     * @return array
     */
    public function execCmd(int &$return_var = 0): array
    {
        exec($this->lib_os->os_cmd, $output, $return_var);

        return $output;
    }

    /**
     * @return $this
     */
    public function setAsBg(): self
    {
        $this->lib_os->setAsBg();

        return $this;
    }

    /**
     * @return $this
     */
    public function setEnvPath(): self
    {
        $this->lib_os->setEnvPath();

        return $this;
    }
}