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
     * Arguments will be filled in the order as being passed
     *
     * @param mixed ...$arguments
     *
     * @return object
     */
    public static function new(...$arguments): object
    {
        return fty::build(get_called_class(), $arguments);
    }

    /**
     * Get original stdClass from called class with alias
     * Defined by class saved from "as"
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
     * Move stdClass under alias (overwrite)
     *
     * @param string $alias
     *
     * @return $this
     */
    public function as(string $alias): object
    {
        return fty::move($this, $alias);
    }

    /**
     * Configurate class properties
     *
     * @param array $setting
     *
     * @return $this
     */
    public function conf(array $setting): object
    {
        //Filter settings
        $setting = array_intersect_key($setting, get_object_vars($this));

        foreach ($setting as $key => $val) {
            $this->$key = $val;
        }

        unset($setting, $key, $val);
        return $this;
    }
}