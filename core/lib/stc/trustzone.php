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

namespace core\lib\stc;

/**
 * Class trustzone
 *
 * @package core\lib\stc
 */
final class trustzone
{
    /**
     * Initialize TrustZone
     *
     * @param string $class
     * @param array  $params
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function init(string $class, array $params): array
    {
        //Create class object
        $class_object = factory::create($class, $params);

        //TrustZone NOT found
        if (!isset($class_object->tz)) {
            unset($class, $params, $class_object);
            return [];
        }

        //Get all public methods
        $method_list = get_class_methods($class_object);

        //Remove all magic methods
        foreach ($method_list as $key => $value) {
            if (0 === strpos($value, '__')) {
                unset($method_list[$key]);
            }
        }

        //Get filtered methods as TrustZone data
        $tz_data = !in_array('*', $tz_data = (array)$class_object->tz, true)
            ? array_intersect($tz_data, $method_list)
            : $method_list;

        unset($class, $params, $class_object, $method_list, $key, $value);
        return $tz_data;
    }
}