<?php

/**
 * NS System OS controller
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
 * Copyright 2016-2019 liu <2579186091@qq.com>
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

namespace core\lib\std;

use core\lib\os\unit;
use core\lib\stc\factory;

/**
 * Class os
 *
 * @package core\lib\std
 */
final class os extends unit
{
    /**
     * @var OS controller instance
     */
    protected $unit_os;

    /**
     * ctrl constructor.
     */
    public function __construct()
    {
        $this->unit_os = factory::build('\\core\\lib\\os\\unit\\' . strtolower(PHP_OS));
    }

    /**
     * Get hardware hash value
     *
     * @return string
     */
    public function get_hw_hash(): string
    {
        return $this->unit_os->get_hw_hash();
    }

    /**
     * Get PHP executable path
     *
     * @return string
     */
    public function get_php_path(): string
    {
        return $this->unit_os->get_php_path();
    }

    /**
     * Set command
     *
     * @param string $cmd
     *
     * @return $this
     */
    public function cmd(string $cmd): object
    {
        $this->unit_os->os_cmd = &$cmd;
        return $this;
    }

    /**
     * Build as background command
     *
     * @return $this
     */
    public function bg(): object
    {
        $this->unit_os->bg();
        return $this;
    }

    /**
     * Build command with ENV values
     *
     * @return $this
     */
    public function env(): object
    {
        $this->unit_os->env();
        return $this;
    }

    /**
     * Build command for proc_* functions
     *
     * @return $this
     */
    public function proc(): object
    {
        $this->unit_os->proc();
        return $this;
    }

    /**
     * Fetch command
     *
     * @return string
     */
    public function fetch(): string
    {
        return $this->unit_os->os_cmd;
    }

    /**
     * Execute unit command & capture outputs
     *
     * @param int $return_var
     *
     * @return array
     */
    public function execute(int &$return_var = 0): array
    {
        exec($this->unit_os->os_cmd, $output, $return_var);
        return $output;
    }
}