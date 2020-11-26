<?php

/**
 * NS Reflect module
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

namespace Core;

/**
 * Class Reflect
 *
 * @package Core
 */
class Reflect extends Factory
{
    private array $pool = [];

    /**
     * Get class reflection
     *
     * @param string $class
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    public function getClass(string $class): \ReflectionClass
    {
        if (!isset($this->pool[$class])) {
            $this->pool[$class] = new \ReflectionClass($class);
        }

        return $this->pool[$class];
    }

    /**
     * Get method reflection
     *
     * @param string $class
     * @param string $method
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    public function getMethod(string $class, string $method): \ReflectionMethod
    {
        if (!isset($this->pool[$key = $class . ':' . $method])) {
            $this->pool[$key] = $this->getClass($class)->getMethod($method);
        }

        unset($class, $method);
        return $this->pool[$key];
    }

    /**
     * Get all params from method
     *
     * @param string $class
     * @param string $method
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getParams(string $class, string $method): array
    {
        if (!isset($this->pool[$key = $class . '>' . $method])) {
            $this->pool[$key] = $this->getMethod($class, $method)->getParameters();
        }

        unset($class, $method);
        return $this->pool[$key];
    }

    /**
     * Get method list from class
     *
     * @param string $class
     * @param int    $filter
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getMethods(string $class, int $filter): array
    {
        return $this->getClass($class)->getMethods($filter);
    }

    /**
     * Get return type hint
     *
     * @param string $class
     * @param string $method
     *
     * @return string
     * @throws \ReflectionException
     */
    public function getReturnType(string $class, string $method): string
    {
        $return_type = !is_null($type_reflect = $this->getMethod($class, $method)->getReturnType()) ? $type_reflect->getName() : '';

        unset($class, $method, $type_reflect);
        return $return_type;
    }

    /**
     * Get information of a param
     *
     * @param \ReflectionParameter $parameter
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getParamInfo(\ReflectionParameter $parameter): array
    {
        $info = [];

        //Get name
        $info['name'] = $parameter->getName();

        //Get default value
        $info['has_default'] = $parameter->isDefaultValueAvailable();
        if ($info['has_default']) {
            $info['default'] = $parameter->getDefaultValue();
        }

        //Get param type & class
        $reflect_type = $parameter->getType();

        if (!is_null($reflect_type)) {
            $info['type']  = $reflect_type->getName();
            $info['class'] = !$reflect_type->isBuiltin() ? '\\' . $info['type'] : null;
        } else {
            $info['type']  = null;
            $info['class'] = null;
        }

        unset($parameter, $reflect_type);
        return $info;
    }

    /**
     * Build params for a method
     *
     * @param string $class
     * @param string $method
     * @param array  $inputs
     *
     * @return array
     * @throws \ReflectionException
     */
    public function buildParams(string $class, string $method, array $inputs): array
    {
        //Default result
        $result = ['param' => [], 'diff' => []];

        //Get needed params
        $need_params = $this->getParams($class, $method);

        /** @var \ReflectionParameter $param_reflect */
        foreach ($need_params as $param_reflect) {
            $param_info = $this->getParamInfo($param_reflect);

            //Dependency injection
            if (!is_null($param_info['class'])) {
                $result['param'][] = parent::getObj($param_info['class']);
                continue;
            }

            //Check param and default value
            if (!isset($inputs[$param_info['name']])) {
                $param_info['has_default']
                    ? $result['param'][] = $param_info['default']
                    : $result['diff'][] = $param_info['name'];
                continue;
            }

            //Type detection
            switch ($param_info['type']) {
                case 'int':
                    is_numeric($inputs[$param_info['name']])
                        ? $result['param'][] = (int)$inputs[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'bool':
                    is_bool($inputs[$param_info['name']])
                        ? $result['param'][] = (bool)$inputs[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'float':
                    is_numeric($inputs[$param_info['name']])
                        ? $result['param'][] = (float)$inputs[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'array':
                    is_array($inputs[$param_info['name']]) || is_object($inputs[$param_info['name']])
                        ? $result['param'][] = (array)$inputs[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'string':
                    is_string($inputs[$param_info['name']]) || is_numeric($inputs[$param_info['name']])
                        ? $result['param'][] = trim((string)$inputs[$param_info['name']])
                        : $result['diff'][] = $param_info['name'];
                    break;
                case 'object':
                    is_object($inputs[$param_info['name']]) || is_array($inputs[$param_info['name']])
                        ? $result['param'][] = (object)$inputs[$param_info['name']]
                        : $result['diff'][] = $param_info['name'];
                    break;
                default:
                    $result['param'][] = $inputs[$param_info['name']];
                    break;
            }
        }

        unset($class, $method, $inputs, $need_params, $param_reflect, $param_info);
        return $result;
    }
}