<?php

/**
 * NS System Reflection controller
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

/**
 * Class reflect
 *
 * @package core\lib
 */
final class reflect
{
    //Reflection pool
    private $pool = [];

    /**
     * Get class reflection
     *
     * @param string $class
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    public function get_class(string $class): \ReflectionClass
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
    public function get_method(string $class, string $method): \ReflectionMethod
    {
        if (!isset($this->pool[$key = $class . ':method:' . $method])) {
            $this->pool[$key] = $this->get_class($class)->getMethod($method);
        }

        unset($class, $method);
        return $this->pool[$key];
    }

    /**
     * Get property reflection
     *
     * @param string $class
     * @param string $property
     *
     * @return \ReflectionProperty
     * @throws \ReflectionException
     */
    public function get_property(string $class, string $property): \ReflectionProperty
    {
        if (!isset($this->pool[$key = $class . ':property:' . $property])) {
            $this->pool[$key] = $this->get_class($class)->getProperty($property);
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
    public function get_params(string $class, string $method): array
    {
        if (!isset($this->pool[$key = $class . ':params:' . $method])) {
            $this->pool[$key] = $this->get_method($class, $method)->getParameters();
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
    public function get_method_list(string $class, int $filter): array
    {
        return $this->get_class($class)->getMethods($filter);
    }

    /**
     * Get property list from class
     *
     * @param string $class
     * @param int    $filter
     *
     * @return array
     * @throws \ReflectionException
     */
    public function get_property_list(string $class, int $filter): array
    {
        return $this->get_class($class)->getProperties($filter);
    }

    /**
     * Get information of a param
     *
     * @param \ReflectionParameter $parameter
     *
     * @return array
     * @throws \ReflectionException
     */
    public function get_param_info(\ReflectionParameter $parameter): array
    {
        $info = [];

        //Get name
        $info['name'] = $parameter->getName();

        //Get default value
        $info['has_default'] = $parameter->isDefaultValueAvailable();
        if ($info['has_default']) {
            $info['default'] = $parameter->getDefaultValue();
        }

        //Get param type
        $info['has_type'] = $parameter->hasType();
        if ($info['has_type']) {
            $info['type'] = $parameter->getType()->getName();

            //Get class name
            $param_class       = $parameter->getClass();
            $info['has_class'] = is_object($param_class);

            if ($info['has_class']) {
                $info['class'] = $param_class->getName();
            }

            unset($param_class);
        } else {
            $info['has_class'] = false;
        }

        unset($parameter);
        return $info;
    }
}