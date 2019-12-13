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

use core\lib\stc\error;
use core\lib\stc\factory;
use core\lib\std\pool;
use core\lib\std\reflect;
use core\lib\std\router;

/**
 * Class cgi
 *
 * @package core\lib
 */
final class cgi
{
    /** @var \core\lib\std\pool $unit_pool */
    private $unit_pool;

    /** @var \core\lib\std\router $unit_router */
    private $unit_router;

    /** @var \core\lib\std\reflect $unit_reflect */
    private $unit_reflect;

    /**
     * cgi constructor.
     */
    public function __construct()
    {
        /** @var \core\lib\std\pool unit_pool */
        $this->unit_pool = factory::build(pool::class);

        /** @var \core\lib\std\router unit_router */
        $this->unit_router = factory::build(router::class);

        /** @var \core\lib\std\reflect unit_reflect */
        $this->unit_reflect = factory::build(reflect::class);
    }

    /**
     * Call CMD group
     *
     * @param array $cmd_group
     *
     * @return array
     * @throws \ReflectionException
     */
    public function call_group(array $cmd_group): array
    {
        $call_results = [];

        while (is_array($group = array_shift($cmd_group))) {
            //Get full class name
            $class = $this->unit_router->get_cls(array_shift($group));

            //Call methods
            foreach ($group as $method) {
                $call_results += $this->call_func($class, $method);
            }
        }

        unset($cmd_group, $group, $class, $method);
        return $call_results;
    }

    /**
     * Call service commands
     *
     * @return array
     */
    public function call_service(): array
    {
        $call_results = $call_before = [];

        //Get call before list
        foreach ($this->unit_pool->conf['call'] as $path => $cmd) {
            $call_before[$this->unit_router->get_cls($path)] = $this->unit_router->parse_cmd($cmd);
        }

        //Process CMD group
        while (is_array($methods = array_shift($this->unit_pool->cgi_stack))) {
            //Skip non-exist class
            if (!class_exists($class = $this->unit_router->get_cls(array_shift($methods)))) {
                continue;
            }

            try {
                //Run preset calls before service
                $call_results += $this->call_before($class, $call_before);
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable, false);
                unset($throwable);
                continue;
            }

            try {
                //Get trusted method list
                $methods = $this->unit_router->cgi_get_trust($class, $methods);
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable, false);
                unset($throwable);
                continue;
            }

            //Run service functions
            foreach ($methods as $method) {
                try {
                    $call_results += $this->call_func($class, $method);
                } catch (\Throwable $throwable) {
                    error::exception_handler($throwable, false);
                    unset($throwable);
                    continue;
                }
            }
        }

        unset($call_before, $path, $cmd, $methods, $class, $method);
        return $call_results;
    }

    /**
     * Run CALL section
     *
     * @param string $class
     * @param array  $call_before
     *
     * @return array
     * @throws \ReflectionException
     */
    private function call_before(string $class, array $call_before): array
    {
        $call_results = [];

        //Root namespace
        $namespace = '\\';

        //Extract class units
        $class_units = false !== strpos($class = trim($class, '\\'), '\\') ? explode('\\', $class) : [$class];

        //Find all matched paths
        foreach ($class_units as $path_unit) {
            $namespace .= $path_unit;

            //Find matched path and call defined methods
            if (isset($call_before[$namespace])) {
                $call_results += $this->call_group($call_before[$namespace]);
            }

            //Fill last namespace separator
            $namespace .= '\\';
        }

        unset($class, $call_before, $namespace, $class_units, $path_unit, $pre_class, $pre_methods, $pre_method);
        return $call_results;
    }

    /**
     * Call function
     *
     * @param string $class
     * @param string $method
     *
     * @return array
     * @throws \ReflectionException
     */
    private function call_func(string $class, string $method): array
    {
        /** @var \ReflectionMethod $method_reflect */
        $method_reflect = $this->unit_reflect->get_method($class, $method);

        //Check method visibility
        if (!$method_reflect->isPublic()) {
            throw new \Exception($this->unit_router->get_key_name($class, $method) . ' => NOT for public!', E_USER_NOTICE);
        }

        //Create class instance
        $class_object = !$method_reflect->isStatic() ? factory::create($class, $this->unit_pool->data) : $class;

        //Filter method params
        $matched_params = $this->unit_reflect->build_params($class, $method, $this->unit_pool->data);

        //Argument params NOT matched
        if (!empty($matched_params['diff'])) {
            throw new \Exception($this->unit_router->get_key_name($class, $method) . ' => Missing params: [' . implode(', ', $matched_params['diff']) . ']', E_USER_NOTICE);
        }

        //Call method
        $fn_result = call_user_func([$class_object, $method], ...$matched_params['param']);

        //Build result
        $result = !is_null($fn_result) ? [$this->unit_router->get_key_name($class, $method) => &$fn_result] : [];

        unset($class, $method, $method_reflect, $matched_params, $class_object, $fn_result);
        return $result;
    }
}