<?php

/**
 * Factory Extension
 *
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

namespace ext;

class factory
{
    /**
     * Get new object from called class
     * Defined by class and arguments
     *
     * @return $this
     */
    public static function new(): object
    {
        return \core\lib\stc\factory::build(get_called_class(), func_get_args());
    }

    /**
     * Get original object from called class with alias
     * Defined by class created from "as"
     *
     * @param $alias
     *
     * @return $this
     * @throws \Exception
     */
    public static function use($alias): object
    {
        return \core\lib\stc\factory::find($alias);
    }

    /**
     * Configure class properties
     *
     * @param array $setting
     *
     * @return $this
     */
    public function conf(array $setting): object
    {
        $setting = array_intersect_key($setting, get_object_vars($this));

        foreach ($setting as $key => $val) {
            $this->$key = $val;
        }

        unset($setting, $key, $val);
        return $this;
    }

    /**
     * Copy object as alias (overwrite)
     *
     * @param string $alias
     *
     * @return $this
     */
    public function as($alias): object
    {
        return \core\lib\stc\factory::move($this, $alias);
    }
}