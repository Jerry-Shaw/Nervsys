<?php

/**
 * Factory Extension
 *
 * Copyright 2016-2019 liu <2579186091@qq.com>
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

namespace ext;

use core\lib\stc\factory as fty;

/**
 * Class factory
 *
 * @package ext
 */
class factory
{
    /**
     * Use an object (copy to class property)
     *
     * @param string $name
     * @param array  $arguments
     *
     * @return $this
     */
    public function __call(string $name, array $arguments): object
    {
        if (in_array(substr($name, 0, 4), ['set_', 'use_'], true) && false !== $var_name = substr($name, 4)) {
            $this->$var_name = current($arguments);
        }

        unset($name, $arguments, $var_name);
        return $this;
    }

    /**
     * Create a stdClass by passing multiple mixed arguments
     * Arguments will be filled in the right order automatically
     *
     * @param array $arguments
     *
     * @return $this
     * @throws \ReflectionException
     */
    public static function create(array $arguments = []): object
    {
        return fty::create(get_called_class(), $arguments);
    }

    /**
     * New a stdClass by passing simply arguments
     * Arguments will be simply passed as is
     *
     * @return $this
     */
    public static function new(): object
    {
        return fty::build(get_called_class(), func_get_args());
    }

    /**
     * Get original stdClass from called class with alias
     *
     * @param string $alias
     *
     * @return $this
     * @throws \Exception
     */
    public static function use(string $alias): object
    {
        return fty::find($alias);
    }

    /**
     * Move stdClass using alias (overwrite)
     *
     * @param string $alias
     *
     * @return $this
     */
    public function as(string $alias): object
    {
        return fty::move($this, $alias);
    }
}