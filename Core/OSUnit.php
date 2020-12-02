<?php

/**
 * NS OSUnit module
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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
    /** @var object $os_obj */
    protected object $os_obj;

    /**
     * OSUnit constructor.
     */
    public function __construct()
    {
        $this->os_obj = parent::getObj('\\Core\\Lib\\OS\\' . PHP_OS);
    }

    /**
     * Get hardware hash
     *
     * @return string
     * @throws \Exception
     */
    public function getHwHash(): string
    {
        return $this->os_obj->getHwHash();
    }

    /**
     * Get PHP executable path
     *
     * @return string
     * @throws \Exception
     */
    public function getPhpPath(): string
    {
        return $this->os_obj->getPhpPath();
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
        $this->os_obj->os_cmd = &$cmd;

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
        return $this->os_obj->os_cmd;
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
        exec($this->os_obj->os_cmd, $output, $return_var);

        return $output;
    }

    /**
     * Set as background command
     *
     * @return $this
     */
    public function setAsBg(): self
    {
        $this->os_obj->setAsBg();

        return $this;
    }

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    public function setEnvPath(): self
    {
        $this->os_obj->setEnvPath();

        return $this;
    }
}