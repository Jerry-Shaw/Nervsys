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

use core\lib\stc\factory;
use core\lib\stc\trustzone;

/**
 * Class router
 *
 * @package core\lib\std
 */
final class router
{
    /**
     * Get Parsed CMD
     *
     * @param string $cmd
     *
     * @return array
     */
    public function parse_cmd(string $cmd): array
    {
        $routes  = [];
        $cmd_key = -1;

        $full_cmd = strtr($cmd, '/', '\\');
        $is_multi = false !== strpos($full_cmd, '-');
        $cmd_list = $is_multi ? explode('-', $full_cmd) : [$full_cmd];

        foreach ($cmd_list as $value) {
            if ('' === $value = trim($value)) {
                continue;
            }

            if (empty($routes) || false !== strpos($value, '\\')) {
                if (!isset($routes[++$cmd_key])) {
                    $routes[$cmd_key] = [];
                }
            }

            if (!in_array($value, $routes[$cmd_key], true)) {
                $routes[$cmd_key][] = $value;
            }
        }

        unset($cmd, $cmd_key, $full_cmd, $is_multi, $cmd_list, $value);
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
        $class = strtr($class, '/', '\\');

        if ('\\' === $class[0]) {
            return $class;
        }

        if (0 === strpos($class, APP_PATH)) {
            return '\\' . $class;
        }

        return '\\' . APP_PATH . '\\' . $class;
    }

    /**
     * Get router key name
     *
     * @param string $class
     * @param string $method
     *
     * @return string
     */
    public function get_key_name(string $class, string $method): string
    {
        return strtr($class . '/' . $method, '\\', '/');
    }

    /**
     * Get CGI trust list
     *
     * @param string $class
     * @param array  $methods
     *
     * @return array
     * @throws \ReflectionException
     */
    public function cgi_get_trust(string $class, array $methods): array
    {
        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = factory::build(pool::class);

        //Get trust data group
        $trust_group = trustzone::init($class, $unit_pool->data);

        //Check auto call mode
        if (!$unit_pool->conf['sys']['auto_call'] || !empty($methods)) {
            $trust_group = array_intersect($trust_group, $methods);
        }

        unset($class, $methods, $unit_pool);
        return $trust_group;
    }

    /**
     * Get CLI trust list
     *
     * @param string $cmd
     * @param array  $cli_conf
     *
     * @return array
     */
    public function cli_get_trust(string $cmd, array $cli_conf): array
    {
        //Trust group
        $trust_group = [];

        //Extract CMD group
        $cmd_group = false !== strpos($cmd, '-') ? explode('-', $cmd) : [$cmd];

        //Find CLI definition
        foreach ($cmd_group as $cmd_value) {
            if (isset($cli_conf[$cmd_value])) {
                $trust_group[] = [$cmd_value, $cli_conf[$cmd_value]];
            }
        }

        unset($cmd, $cli_conf, $cmd_group, $cmd_value);
        return $trust_group;
    }
}