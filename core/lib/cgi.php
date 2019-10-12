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

/**
 * Class cgi
 *
 * @package core\lib
 */
class cgi
{
    /** @var \core\lib\std\reflect $unit_reflect */
    private $unit_reflect;

    /**
     * cgi constructor.
     */
    public function __construct()
    {
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
     * Get full class name
     *
     * @param string $class
     *
     * @return string
     */
    public function get_cls(string $class): string
    {
        return 0 !== strpos($class, '\\') ? '\\' . APP_PATH . '\\' . $class : $class;
    }

    /**
     * Get result key name
     *
     * @param string $class
     * @param string $method
     *
     * @return string
     */
    public function get_name(string $class, string $method): string
    {
        return strtr($class . '/' . $method, '\\', '/');
    }

    /**
     * Get matched params
     *
     * @param array $need_params
     * @param array $input_params
     *
     * @return array
     * @throws \ReflectionException
     */
    public function get_params(array $need_params, array $input_params): array
    {
        //Default result
        $result = ['param' => [], 'diff' => []];

        /** @var \ReflectionParameter $param_reflect */
        foreach ($need_params as $param_reflect) {
            $param_info = $this->unit_reflect->get_param_info($param_reflect);

            //Dependency injection
            if ($param_info['has_class']) {
                $result['param'][] = factory::build($param_info['class']);
                continue;
            }

            //Param NOT exists
            if (!isset($input_params[$param_info['name']])) {
                $result['diff'][] = $param_info['name'];
                continue;
            }

            //Param without type
            if (!$param_info['has_type']) {
                $result['param'][] = $input_params[$param_info['name']];
                continue;
            }

            //Type detection
            switch ($param_info['type']) {
                case 'int':
                    is_numeric($input_params[$param_info['name']])
                        ? $result['param'][] = (int)$input_params[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'bool':
                    is_bool($input_params[$param_info['name']])
                        ? $result['param'][] = (bool)$input_params[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'float':
                    is_numeric($input_params[$param_info['name']])
                        ? $result['param'][] = (float)$input_params[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'array':
                    is_array($input_params[$param_info['name']]) || is_object($input_params[$param_info['name']])
                        ? $result['param'][] = (array)$input_params[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'string':
                    is_string($input_params[$param_info['name']]) || is_numeric($input_params[$param_info['name']])
                        ? $result['param'][] = trim((string)$input_params[$param_info['name']])
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'object':
                    is_object($input_params[$param_info['name']]) || is_array($input_params[$param_info['name']])
                        ? $result['param'][] = (object)$input_params[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                default:
                    $result['param'][] = $input_params[$param_info['name']];
                    break;
            }
        }

        unset($need_params, $input_params, $param_reflect, $param_info);
        return $result;
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
    public function call_fn(string $class, string $method, array $params): array
    {
        //Get full class name
        $class = $this->get_cls($class);

        /** @var \ReflectionMethod $method_reflect */
        $method_reflect = $this->unit_reflect->get_method($class, $method);

        //Check method visibility
        if (!$method_reflect->isPublic()) {
            throw new \Exception($this->get_name($class, $method) . ' => NOT for public!', E_USER_NOTICE);
        }

        //Check method property
        if (!$method_reflect->isStatic()) {
            if (method_exists($class, '__construct')) {
                $matched_params = $this->get_params($this->unit_reflect->get_params($class, '__construct'), $params);

                if (!empty($matched_params['diff'])) {
                    throw new \Exception($this->get_name($class, '__construct') . ' => Missing params: [' . implode(', ', $matched_params['diff']) . ']', E_USER_NOTICE);
                }

                //Get class object with __construct
                $class_object = factory::build($class, $matched_params['param']);
            } else {
                //Get class object without __construct
                $class_object = factory::build($class);
            }
        } else {
            //Static class
            $class_object = &$class;
        }

        //Filter method params
        $matched_params = $this->get_params($this->unit_reflect->get_params($class, $method), $params);

        if (!empty($matched_params['diff'])) {
            throw new \Exception($this->get_name($class, $method) . ' => Missing params: [' . implode(', ', $matched_params['diff']) . ']', E_USER_NOTICE);
        }

        //Call method
        $fn_result = !empty($matched_params['param'])
            ? forward_static_call_array([$class_object, $method], $matched_params['param'])
            : forward_static_call([$class_object, $method]);

        //Build result
        $result = !is_null($fn_result) ? [$this->get_name($class, $method) => &$fn_result] : [];

        unset($class, $method, $params, $method_reflect, $matched_params, $class_object, $fn_result);
        return $result;
    }
}