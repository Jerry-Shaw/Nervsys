<?php

/**
 * NS System TrustZone handler
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

use core\lib\stc\factory;

/**
 * Class trustzone
 *
 * @package core\lib\std
 */
final class trustzone
{
    //TrustZone data
    private $tz_data = [];

    /**
     * Initialize TrustZone
     *
     * @param string $class
     * @param array  $params
     *
     * @throws \ReflectionException
     */
    public function init(string $class, array $params): void
    {
        //Create class object
        $class_object = factory::create($class, $params);

        //Get all public methods
        $method_list = get_class_methods($class_object);

        //Get TrustZone data from class
        $tz_data = $class_object->tz ?? [];

        //Get filtered methods as TrustZone data
        $this->tz_data = !in_array('*', $tz_data, true) ? array_intersect($method_list, $tz_data) : $method_list;

        //Free memory
        unset($class, $params, $class_object, $method_list, $tz_data);
    }

    /**
     * Check permission in TrustZone
     *
     * @param string $method
     *
     * @return bool
     */
    public function deny(string $method): bool
    {
        return !in_array($method, $this->tz_data, true);
    }
}