<?php

/**
 * NS OSUnit module
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

/**
 * Class OSUnit
 *
 * @package Core
 */
class OSUnit extends Factory
{
    //OS name
    public string $php_os;

    /** @var \Core\Lib\OS\Linux|\Core\Lib\OS\WINNT|\Core\Lib\OS\Darwin $lib_os */
    protected object $lib_os;

    /**
     * OSUnit constructor.
     */
    public function __construct()
    {
        $this->php_os = PHP_OS;
        $this->lib_os = parent::getObj('\\Core\\Lib\\OS\\' . $this->php_os);
    }

    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        return $this->lib_os->getHwHash();
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        return $this->lib_os->getPhpPath();
    }

    /**
     * Set command
     *
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
     * Fetch command
     *
     * @return string
     */
    public function fetchCmd(): string
    {
        return $this->lib_os->os_cmd;
    }

    /**
     * Execute command
     *
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
     * Set as background command
     *
     * @return $this
     */
    public function setAsBg(): self
    {
        $this->lib_os->setAsBg();

        return $this;
    }

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    public function setEnvPath(): self
    {
        $this->lib_os->setEnvPath();

        return $this;
    }
}