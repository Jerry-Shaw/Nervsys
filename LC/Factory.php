<?php

/**
 * Factory library
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

class Factory
{
    protected static array $objects = [];

    /**
     * @return static
     * @throws \ReflectionException
     */
    public static function new(): static
    {
        $class_name = get_called_class();
        $class_args = func_get_args();

        if (method_exists($class_name, '__construct')) {
            if (1 === count($class_args) && is_array($class_args[0])) {
                $class_args = $class_args[0];
            }

            if (!array_is_list($class_args)) {
                $class_args = self::buildArgs(Reflect::getMethod($class_name, '__construct')->getParameters(), $class_args);
            }
        } else {
            $class_args = [];
        }

        $object = self::getObj($class_name, $class_args);

        unset($class_name, $class_args);
        return $object;
    }

    /**
     * @param string $class_name
     * @param array  $class_args
     *
     * @return object
     */
    public static function getObj(string $class_name, array $class_args = []): object
    {
        $class_key = hash('md5', $class_name . json_encode($class_args));

        if (!isset(self::$objects[$class_key])) {
            self::$objects[$class_key] = new ('\\' . trim($class_name, '\\'))(...$class_args);
        }

        unset($class_name, $class_args);
        return self::$objects[$class_key];
    }

    /**
     * @param array $param_reflects
     * @param array $data_package
     *
     * @return array
     * @throws \ReflectionException
     * @throws \Exception
     */
    public static function buildArgs(array $param_reflects, array $data_package): array
    {
        $args = $diff = [];

        foreach ($param_reflects as $param_reflect) {
            $param_info = Reflect::getParameterInfo($param_reflect);

            if (!$param_info['build_in']) {
                $args[] = self::getObj($param_info['type']);
                continue;
            }

            if (!isset($data_package[$param_info['name']])) {
                $param_info['has_default']
                    ? $args[] = $param_info['default_value']
                    : $diff[] = '$' . $param_info['name'] . ' not found';
                continue;
            }

            if ('int' === $param_info['type'] && is_numeric($data_package[$param_info['name']])) {
                $args[] = (int)$data_package[$param_info['name']];
            } elseif ('float' === $param_info['type'] && is_numeric($data_package[$param_info['name']])) {
                $args[] = (float)$data_package[$param_info['name']];
            } elseif ('string' === $param_info['type'] && (is_string($data_package[$param_info['name']]) || is_numeric($data_package[$param_info['name']]))) {
                $args[] = trim((string)$data_package[$param_info['name']]);
            } elseif ('array' === $param_info['type'] && is_array($data_package[$param_info['name']])) {
                $args[] = $data_package[$param_info['name']];
            } elseif ('bool' === $param_info['type'] && is_bool($data_package[$param_info['name']])) {
                $args[] = $data_package[$param_info['name']];
            } elseif ('object' === $param_info['type'] && is_object($data_package[$param_info['name']])) {
                $args[] = $data_package[$param_info['name']];
            } elseif (is_null($param_info['type'])) {
                $args[] = $data_package[$param_info['name']];
            } else {
                $diff[] = '$' . $param_info['name'] . ': expected \'' . $param_info['type'] . '\', but got \'' . $data_package[$param_info['name']] . '\'';
            }
        }

        if (!empty($diff)) {
            throw new \Exception(implode(', ', $diff), E_ERROR);
        }

        unset($param_reflects, $data_package, $diff, $param_reflect, $param_info);
        return $args;
    }

    /**
     * @param object $object
     *
     * @return void
     */
    public static function destroy(object $object): void
    {
        $keys = array_keys(self::$objects, $object, true);

        foreach ($keys as $key) {
            unset(self::$objects[$key]);
        }

        unset($object, $keys, $key);
    }
}