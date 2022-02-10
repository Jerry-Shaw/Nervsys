<?php

/**
 * Nervsys Reflect library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Lib;

class Reflect extends Factory
{
    private array $pool = [];

    /**
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
     * @param string $class
     * @param string $method
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    public function getMethod(string $class, string $method): \ReflectionMethod
    {
        $key = $class . ':' . $method;

        if (!isset($this->pool[$key])) {
            $this->pool[$key] = $this->getClass($class)->getMethod($method);
        }

        unset($class, $method);
        return $this->pool[$key];
    }

    /**
     * @param string $class
     * @param string $method
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getParams(string $class, string $method): array
    {
        $key = $class . '::' . $method;

        if (!isset($this->pool[$key])) {
            $this->pool[$key] = $this->getMethod($class, $method)->getParameters();
        }

        unset($class, $method);
        return $this->pool[$key];
    }

    /**
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
     * @param \ReflectionParameter $parameter
     *
     * @return array
     * @throws \ReflectionException
     */
    public function getParamInfo(\ReflectionParameter $parameter): array
    {
        $info = [];

        $info['name']          = $parameter->getName();
        $info['has_default']   = $parameter->isDefaultValueAvailable();
        $info['default_value'] = $info['has_default'] ? $parameter->getDefaultValue() : null;

        $reflect_type = $parameter->getType();

        if (!is_null($reflect_type)) {
            $info['type']     = $reflect_type->getName();
            $info['build_in'] = $reflect_type->isBuiltin();
        } else {
            $info['type']     = null;
            $info['build_in'] = true;
        }

        unset($parameter, $reflect_type);
        return $info;
    }
}