<?php

/**
 * NS System Router controller
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

namespace core\lib\std;

/**
 * Class router
 *
 * @package core\lib\std
 */
final class router
{
    /**
     * Parse CMD
     * Result format: ['class_name' => ['method_1', 'method_2', ...], ...]
     *
     * @param string $cmd
     *
     * @return array
     */
    public function parse_cmd(string $cmd): array
    {
        $full_cmd = strtr($cmd, '/', '\\');
        $is_multi = strpos($full_cmd, '-');

        $routes  = [];
        $cmd_key = '';

        $cmd_list = $is_multi ? explode('-', $full_cmd) : [$full_cmd];

        foreach ($cmd_list as $value) {
            if ('' === $value = trim($value)) {
                continue;
            }

            if (empty($routes) || false !== strpos($value, '\\')) {
                $cmd_key = $value;

                if (!isset($routes[$cmd_key])) {
                    $routes[$cmd_key] = [];
                }

                continue;
            }

            if (!in_array($value, $routes[$cmd_key], true)) {
                $routes[$cmd_key][] = $value;
            }
        }

        unset($cmd, $full_cmd, $is_multi, $cmd_key, $cmd_list, $value);
        return $routes;
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
     * Get router key name
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
}