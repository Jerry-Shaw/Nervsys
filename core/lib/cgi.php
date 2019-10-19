<?php

/**
 * NS System CGI script
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

use core\lib\stc\factory;
use core\lib\std\reflect;
use core\lib\std\router;

/**
 * Class cgi
 *
 * @package core\lib
 */
class cgi
{
    /** @var \core\lib\std\router $unit_router */
    private $unit_router;

    /** @var \core\lib\std\reflect $unit_reflect */
    private $unit_reflect;

    /**
     * cgi constructor.
     */
    public function __construct()
    {
        /** @var \core\lib\std\router unit_router */
        $this->unit_router = factory::build(router::class);

        /** @var \core\lib\std\reflect unit_reflect */
        $this->unit_reflect = factory::build(reflect::class);
    }


    /**
     * @param string $class
     * @param array  $methods
     *
     * @return array
     */
    public function run(string $class, array $methods): array
    {

    }


    /**
     * Call function
     *
     * @param string $class
     * @param string $method
     * @param array  $params
     *
     * @return array
     * @throws \ReflectionException
     */
    public function call_fn(string $class, string $method, array $params = []): array
    {
        //Get full class name
        $class = $this->unit_router->get_cls($class);

        /** @var \ReflectionMethod $method_reflect */
        $method_reflect = $this->unit_reflect->get_method($class, $method);

        //Check method visibility
        if (!$method_reflect->isPublic()) {
            throw new \Exception($this->unit_router->get_name($class, $method) . ' => NOT for public!', E_USER_NOTICE);
        }

        //Create class instance
        $class_object = !$method_reflect->isStatic() ? factory::create($class, $params) : $class;

        //Filter method params
        $matched_params = $this->unit_reflect->build_params($class, $method, $params);

        if (!empty($matched_params['diff'])) {
            throw new \Exception($this->unit_router->get_name($class, $method) . ' => Missing params: [' . implode(', ', $matched_params['diff']) . ']', E_USER_NOTICE);
        }

        //Call method
        $fn_result = !empty($matched_params['param'])
            ? forward_static_call_array([$class_object, $method], $matched_params['param'])
            : forward_static_call([$class_object, $method]);

        //Build result
        $result = !is_null($fn_result) ? [$this->unit_router->get_name($class, $method) => &$fn_result] : [];

        unset($class, $method, $params, $method_reflect, $matched_params, $class_object, $fn_result);
        return $result;
    }
}