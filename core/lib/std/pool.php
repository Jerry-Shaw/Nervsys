<?php

/**
 * NS System Data Pooling controller
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

/**
 * Class pool
 *
 * @package core\lib
 */
final class pool
{
    /**
     * CMD
     *
     * @var string
     */
    public $cmd = '';

    /**
     * Log
     *
     * @var string
     */
    public $log = '';

    /**
     * conf
     *
     * @var array
     */
    public $conf = [];

    /**
     * Data
     *
     * @var array
     */
    public $data = [];

    /**
     * Error
     *
     * @var array
     */
    public $error = [];

    /**
     * Result
     *
     * @var array
     */
    public $result = [];

    /**
     * Others
     *
     * @var array
     */
    public $others = [];

    /**
     * Get value
     *
     * @param string $name
     *
     * @return array
     */
    public function __get(string $name): array
    {
        return $this->others[$name] ?? [];
    }

    /**
     * Set value
     *
     * @param string $name
     * @param array  $value
     */
    public function __set($name, array $value): void
    {
        $this->others[$name] = &$value;
        unset($name, $value);
    }
}