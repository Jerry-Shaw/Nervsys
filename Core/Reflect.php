<?php

/**
 * Reflect library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2025 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core;

class Reflect
{
    private static array $reflects = [];

    /**
     * @param string $class_name
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    public static function getClass(string $class_name): \ReflectionClass
    {
        $class_name = trim($class_name, '\\');

        if (!isset(self::$reflects[$class_name])) {
            self::$reflects[$class_name] = new \ReflectionClass($class_name);
        }

        return self::$reflects[$class_name];
    }

    /**
     * @param string $class_name
     * @param string $method_name
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    public static function getMethod(string $class_name, string $method_name): \ReflectionMethod
    {
        $key = trim($class_name, '\\') . '::' . $method_name;

        if (!isset(self::$reflects[$key])) {
            self::$reflects[$key] = new \ReflectionMethod($class_name, $method_name);
        }

        unset($class_name, $method_name);
        return self::$reflects[$key];
    }

    /**
     * @param callable $callable
     *
     * @return \ReflectionFunction|\ReflectionMethod
     * @throws \ReflectionException
     */
    public static function getCallable(callable $callable): \ReflectionFunction|\ReflectionMethod
    {
        if (is_array($callable)) {
            $key = trim((is_object($callable[0]) ? $callable[0]::class : $callable[0]), '\\') . '::' . $callable[1];

            if (!isset(self::$reflects[$key])) {
                self::$reflects[$key] = new \ReflectionMethod(...$callable);
            }

            $reflect = self::$reflects[$key];
            unset($key);
        } elseif (is_string($callable)) {
            $callable = trim($callable, '\\');

            if (!isset(self::$reflects[$callable])) {
                self::$reflects[$callable] = str_contains($callable, '::')
                    ? new \ReflectionMethod(...explode('::', $callable, 2))
                    : new \ReflectionFunction($callable);
            }

            $reflect = self::$reflects[$callable];
        } else {
            $reflect = new \ReflectionFunction($callable);
        }

        unset($callable);
        return $reflect;
    }

    /**
     * @param string   $class_name
     * @param int|null $filter
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function getMethods(string $class_name, int|null $filter = null): array
    {
        $key = trim($class_name, '\\') . '@' . ($filter ?? 0);

        if (!isset(self::$reflects[$key])) {
            self::$reflects[$key] = self::getClass($class_name)->getMethods($filter);
        }

        unset($class_name, $filter);
        return self::$reflects[$key];
    }

    /**
     * @param string $class_name
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function getTraits(string $class_name): array
    {
        $key = trim($class_name, '\\') . '@Trait';

        if (!isset(self::$reflects[$key])) {
            self::$reflects[$key] = self::getClass($class_name)->getTraits();
        }

        unset($class_name);
        return self::$reflects[$key];
    }

    /**
     * @param \ReflectionParameter $parameter
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function getParameterInfo(\ReflectionParameter $parameter): array
    {
        $param_info   = [];
        $reflect_type = $parameter->getType();
        $has_default  = $parameter->isDefaultValueAvailable();

        $param_info['name']          = $parameter->getName();
        $param_info['type']          = [];
        $param_info['is_variadic']   = $parameter->isVariadic();
        $param_info['has_default']   = $has_default;
        $param_info['default_value'] = $has_default ? $parameter->getDefaultValue() : null;

        if (!is_null($reflect_type)) {
            $param_types = $reflect_type instanceof \ReflectionUnionType
                ? $reflect_type->getTypes()
                : [$reflect_type];

            foreach ($param_types as $param_type) {
                $param_info['type'][$param_type->getName()] = $param_type->isBuiltin();
            }
        }

        unset($parameter, $reflect_type, $has_default, $param_types, $param_type);
        return $param_info;
    }
}