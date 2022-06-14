<?php

/**
 * Security library
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

namespace Nervsys\LC\Lib;

use Nervsys\LC\Factory;
use Nervsys\LC\Reflect;

class Security extends Factory
{
    /**
     * @param string   $class_name
     * @param string   $method_name
     * @param array    $class_args
     * @param int|null $filter
     *
     * @return callable
     * @throws \ReflectionException
     */
    public function getApiMethod(string $class_name, string $method_name, array $class_args = [], int $filter = null): callable
    {
        $traits = Reflect::getTraits($class_name);

        /**
         * @var string           $name
         * @var \ReflectionClass $obj
         */
        foreach ($traits as $name => $obj) {
            if (str_starts_with($name, NS_NAMESPACE)) {
                if (in_array($method_name, get_class_methods($name), true)) {
                    unset($class_name, $method_name, $class_args, $filter, $traits, $name, $obj);
                    return [$this, 'targetBlocked'];
                }
            }
        }

        $methods = Reflect::getMethods($class_name, $filter);

        /** @var \ReflectionMethod $obj */
        foreach ($methods as $obj) {
            if ($method_name !== $obj->name) {
                continue;
            }

            if (str_starts_with($obj->class, NS_NAMESPACE)) {
                unset($class_name, $method_name, $class_args, $filter, $traits, $name, $obj, $methods);
                return [$this, 'targetBlocked'];
            }

            $callable = [!$obj->isStatic() ? parent::getObj($class_name, $class_args) : $class_name, $method_name];

            unset($class_name, $method_name, $class_args, $filter, $traits, $name, $obj, $methods);
            return $callable;
        }

        unset($class_name, $method_name, $class_args, $filter, $traits, $name, $obj, $methods);
        return [$this, 'targetInvalid'];
    }

    /**
     * @return void
     */
    public function targetBlocked(): void
    {

    }

    /**
     * @return void
     */
    public function targetInvalid(): void
    {

    }

    /**
     * @return void
     */
    public function argumentInvalid(): void
    {

    }
}