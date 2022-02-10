<?php

/**
 * Nervsys Factory library
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

class Factory extends Reflect
{
    protected static array $objects = [];

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
     * @param array  $class_params
     *
     * @return object
     * @throws \ReflectionException
     */
    public static function getObj(string $class_name, array $class_params = []): object
    {
        $key = $class_name;

        if (!empty($class_params)) {
            $key .= json_encode($class_params);
        }

        $hash_key = hash('md5', $key);

        if (!isset(self::$objects[$hash_key])) {
            if (method_exists($class_name, '__construct') && 1 === count($class_params)) {
                $pass_params = current($class_params);

                if (is_array($pass_params) && !array_is_list($pass_params)) {
                    $class_params = self::getArgs($class_name, '__construct', $pass_params);
                }

                unset($pass_params);
            }

            self::$objects[$hash_key] = new ('\\' . trim($class_name, '\\'))(...$class_params);
        }

        unset($class_name, $class_params, $key);
        return self::$objects[$hash_key];
    }

    /**
     * @param string $class
     * @param string $method
     * @param array  $data
     *
     * @return array
     * @throws \ReflectionException
     */
    public static function getArgs(string $class, string $method, array $data): array
    {
        $result = ['args' => [], 'diff' => []];
        $params = self::getParams($class, $method);

        foreach ($params as $param_reflect) {
            $param_info = self::getParamInfo($param_reflect);

            if (!$param_info['build_in']) {
                $result['args'][] = self::getObj($param_info['type'], $data);
                continue;
            }

            if (!isset($data[$param_info['name']])) {
                $param_info['has_default']
                    ? $result['args'][] = $param_info['default_value']
                    : $result['diff'][] = '"' . $param_info['name'] . '" not found';
                continue;
            }

            switch ($param_info['type']) {
                case 'int':
                    is_numeric($data[$param_info['name']])
                        ? $result['args'][] = (int)$data[$param_info['name']]
                        : $result['diff'][] = '"' . $param_info['name'] . '" int expected, but got "' . $data[$param_info['name']] . '"';
                    break;
                case 'bool':
                    is_bool($data[$param_info['name']])
                        ? $result['args'][] = (bool)$data[$param_info['name']]
                        : $result['diff'][] = '"' . $param_info['name'] . '" boolean expected, but got "' . $data[$param_info['name']] . '"';
                    break;
                case 'float':
                    is_numeric($data[$param_info['name']])
                        ? $result['args'][] = (float)$data[$param_info['name']]
                        : $result['diff'][] = '"' . $param_info['name'] . '" float expected, but got "' . $data[$param_info['name']] . '"';
                    break;
                case 'array':
                    is_array($data[$param_info['name']]) || is_object($data[$param_info['name']])
                        ? $result['args'][] = (array)$data[$param_info['name']]
                        : $result['diff'][] = '"' . $param_info['name'] . '" array expected, but got "' . $data[$param_info['name']] . '"';
                    break;
                case 'string':
                    is_string($data[$param_info['name']]) || is_numeric($data[$param_info['name']])
                        ? $result['args'][] = trim((string)$data[$param_info['name']])
                        : $result['diff'][] = '"' . $param_info['name'] . '" string expected, but got "' . $data[$param_info['name']] . '"';
                    break;
                case 'object':
                    is_object($data[$param_info['name']]) || is_array($data[$param_info['name']])
                        ? $result['args'][] = (object)$data[$param_info['name']]
                        : $result['diff'][] = '"' . $param_info['name'] . '" object expected, but got "' . $data[$param_info['name']] . '"';
                    break;
                default:
                    $result['args'][] = $data[$param_info['name']];
                    break;
            }
        }

        unset($class, $method, $data, $params, $param_reflect, $param_info);
        return $result;
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