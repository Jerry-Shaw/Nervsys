<?php

/**
 * Factory library
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

class Factory
{
    private static array $objects = [];

    /**
     * @return static
     * @throws \ReflectionException
     */
    public static function new(): static
    {
        return self::getObj(get_called_class(), func_get_args());
    }

    /**
     * @param string $class_name
     * @param array  $class_args
     *
     * @return object
     * @throws \ReflectionException
     */
    public static function getObj(string $class_name, array $class_args = []): object
    {
        if (!method_exists($class_name, '__construct')) {
            $class_args = [];
        } elseif (
            1 === count($class_args)
            && isset($class_args[0])
            && is_array($class_args[0])
            && !array_is_list($class_args[0])) {
            $class_args = self::buildArgs(Reflect::getMethod($class_name, '__construct')->getParameters(), $class_args[0]);
        }

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
            $param_info  = Reflect::getParameterInfo($param_reflect);
            $param_exist = array_key_exists($param_info['name'], $data_package);

            if ($param_info['is_variadic']) {
                $args[] = $param_exist ? (array)$data_package[$param_info['name']] : [];
                continue;
            }

            if (!$param_exist) {
                if ($param_info['has_default']) {
                    $args[] = $param_info['default_value'];
                    continue;
                }

                $object_name = array_search(false, $param_info['type'], true);

                if (false !== $object_name) {
                    $args[] = self::getObj($object_name);
                    continue;
                }

                $diff[] = '$' . $param_info['name'] . ' NOT found';
                continue;
            }

            if (array_key_exists('float', $param_info['type']) && is_numeric($data_package[$param_info['name']])) {
                $args[] = (float)$data_package[$param_info['name']];
            } elseif (array_key_exists('int', $param_info['type']) && is_numeric($data_package[$param_info['name']])) {
                $args[] = (int)$data_package[$param_info['name']];
            } elseif (array_key_exists('string', $param_info['type']) && (is_string($data_package[$param_info['name']]) || is_numeric($data_package[$param_info['name']]))) {
                $args[] = trim((string)$data_package[$param_info['name']]);
            } elseif (array_key_exists('array', $param_info['type']) && is_array($data_package[$param_info['name']])) {
                $args[] = $data_package[$param_info['name']];
            } elseif (array_key_exists('bool', $param_info['type']) && is_bool($data_package[$param_info['name']])) {
                $args[] = $data_package[$param_info['name']];
            } elseif (array_key_exists('object', $param_info['type']) && is_object($data_package[$param_info['name']])) {
                $args[] = $data_package[$param_info['name']];
            } elseif (empty($param_info['type']) || array_key_exists('mixed', $param_info['type'])) {
                $args[] = $data_package[$param_info['name']];
            } else {
                $expected = implode('|', array_keys($param_info['type']));
                $detected = gettype($data_package[$param_info['name']]);

                if (in_array($detected, ['integer', 'double', 'string'], true)) {
                    $param_value = (string)$data_package[$param_info['name']];
                } elseif ('boolean' === $detected) {
                    $param_value = true === $data_package[$param_info['name']] ? 'true' : 'false';
                } elseif ('array' === $detected) {
                    $param_value = '[' . implode(', ', $data_package[$param_info['name']]) . ']';
                } elseif ('NULL' === $detected) {
                    $param_value = 'NULL';
                } else {
                    $param_value = '(' . $detected . ')';
                }

                $diff[] = '$' . $param_info['name'] . ' needs \''
                    . $expected . '\' value instead of '
                    . $detected . ' \'' . $param_value . '\'';
            }
        }

        if (!empty($diff)) {
            throw new \Exception(implode(', ', $diff), E_ERROR);
        }

        unset($param_reflects, $data_package, $diff, $param_reflect, $param_info, $param_exist, $object_name, $expected, $detected, $param_value);
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