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
     * Call INIT commands
     *
     * @param array $cmd_group
     *
     * @return array
     * @throws \ReflectionException
     */
    public function call_init(array $cmd_group): array
    {
        //CGI results
        $call_results = [];

        foreach ($cmd_group as $class => $methods) {
            //Fill class name
            $class = $this->unit_router->get_cls($class);

            //Call methods
            foreach ($methods as $method) {
                $call_results += $this->call_func($class, $method);
            }
        }

        unset($cmd_group, $class, $methods, $method);
        return $call_results;
    }

    /**
     * Call service commands
     *
     * @param array $cmd_group
     * @param array $call_section
     * @param array $input_params
     *
     * @return array
     * @throws \ReflectionException
     */
    public function call_service(array $cmd_group, array $call_section, array $input_params): array
    {
        //CGI results and call before list
        $call_results = $call_before = [];

        foreach ($call_section as $path => $cmd) {
            $call_before[$this->unit_router->get_cls($path)] = $this->unit_router->parse_cmd($cmd);
        }

        unset($call_section, $path, $cmd);

        //Process CMD group
        foreach ($cmd_group as $class => $methods) {
            //Fill class name
            $class = $this->unit_router->get_cls($class);

            //Run call before functions
            $call_results += $this->call_before($class, $call_before, $input_params);

            //Run service function
            foreach ($methods as $method) {
                $call_results += $this->call_before($class, $method, $input_params);
            }
        }

        unset($cmd_group, $input_params, $call_before, $class, $methods, $method);
        return $call_results;
    }

    /**
     * Run CALL section
     *
     * @param string $class
     * @param array  $call_before
     * @param array  $input_params
     *
     * @return array
     * @throws \ReflectionException
     */
    private function call_before(string $class, array $call_before, array $input_params): array
    {
        //CGI results
        $call_results = [];

        //Root namespace prefix
        $namespace = '\\';

        //Extract class units
        $class_units = false !== strpos($class = trim($class, '\\'), '\\') ? explode('\\', $class) : [$class];

        //Find all matched paths
        foreach ($class_units as $path_unit) {
            $namespace .= $path_unit;

            //Try to find matched path
            if (isset($call_before[$namespace])) {
                //Run CALL section
                foreach ($call_before[$namespace] as $pre_class => $pre_methods) {
                    //Fill class name
                    $pre_class = $this->unit_router->get_cls($pre_class);

                    //Call methods
                    foreach ($pre_methods as $pre_method) {
                        $call_results += $this->call_func($pre_class, $pre_method, $input_params);
                    }
                }
            }

            //Fill last namespace separator
            $namespace .= '\\';
        }

        unset($class, $call_before, $input_params, $namespace, $class_units, $path_unit, $pre_class, $pre_methods, $pre_method);
        return $call_results;
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
    private function call_func(string $class, string $method, array $params = []): array
    {
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
        $fn_result = call_user_func([$class_object, $method], ...$matched_params['param']);

        //Build result
        $result = !is_null($fn_result) ? [$this->unit_router->get_name($class, $method) => &$fn_result] : [];

        unset($class, $method, $params, $method_reflect, $matched_params, $class_object, $fn_result);
        return $result;
    }
}