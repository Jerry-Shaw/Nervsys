<?php

/**
 * NS System Configuration controller
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

namespace core\lib;

/**
 * Class conf
 *
 * @package core\lib
 */
final class conf
{
    /**
     * Conf value pool
     *
     * @var array
     */
    private $pool = [];

    /**
     * Get a conf
     *
     * @param string $name
     *
     * @return array
     */
    public function __get(string $name): array
    {
        return $this->pool[$name] ?? [];
    }

    /**
     * Set a conf
     *
     * @param string $name
     * @param array  $value
     */
    public function __set(string $name, array $value): void
    {
        $this->pool[$name] = &$value;
        unset($name, $value);
    }
}