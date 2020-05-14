<?php

/**
 * NS System OS unit controller
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

namespace core\lib\os;

/**
 * Class unit
 *
 * @package core\lib\os
 */
abstract class unit
{
    /**
     * @var string OS command
     */
    protected $os_cmd = '';

    /**
     * Get hardware hash value
     *
     * @return string
     */
    abstract public function get_hw_hash(): string;

    /**
     * Get PHP executable path
     *
     * @return string
     */
    abstract public function get_php_path(): string;

    /**
     * Set as background command
     *
     * @return $this
     */
    abstract public function bg(): object;

    /**
     * Set command with ENV values
     *
     * @return $this
     */
    abstract public function env(): object;

    /**
     * Set command for proc_* functions
     *
     * @return $this
     */
    abstract public function proc(): object;
}