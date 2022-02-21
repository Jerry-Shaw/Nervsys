<?php

/**
 * Reflect library
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

namespace Nervsys\LC;

class Reflect
{
    private static array $reflects = [];

    /**
     * @param string $class
     *
     * @return \ReflectionClass
     * @throws \ReflectionException
     */
    public static function getClass(string $class): \ReflectionClass
    {
        $class = trim($class, '\\');

        if (!isset(self::$reflects[$class])) {
            self::$reflects[$class] = new \ReflectionClass($class);
        }

        return self::$reflects[$class];
    }

    /**
     * @param string $class
     * @param string $method
     *
     * @return \ReflectionMethod
     * @throws \ReflectionException
     */
    public static function getMethod(string $class, string $method): \ReflectionMethod
    {
        $key = trim($class . '::' . $method, '\\');

        if (!isset(self::$reflects[$key])) {
            self::$reflects[$key] = new \ReflectionMethod($class, $method);
        }

        unset($class, $method);
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
            $key = trim((is_object($callable[0]) ? $callable[0]::class : $callable[0]) . '::' . $callable[1], '\\');

            if (!isset(self::$reflects[$key])) {
                self::$reflects[$key] = new \ReflectionMethod($callable);
            }

            $reflect = self::$reflects[$key];
            unset($key);
        } elseif (is_string($callable)) {
            $callable = trim($callable, '\\');

            if (!isset(self::$reflects[$callable])) {
                self::$reflects[$callable] = str_contains($callable, '::')
                    ? new \ReflectionMethod($callable)
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
     * @param \ReflectionParameter $parameter
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function getParameterInfo(\ReflectionParameter $parameter): array
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