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
     * Get trust CMD
     *
     * @param string $cmd
     *
     * @return array
     * @throws \ReflectionException
     */
    public function trust_cmd(string $cmd): array
    {
        //Trust group
        $trust_group = [];

        //Parse command
        $cmd_group = $this->parse_cmd($cmd);

        /** @var \core\lib\std\pool $unit_pool */
        $unit_pool = factory::build(pool::class);

        //Filter commands via TrustZone
        while (is_array($group = array_shift($cmd_group))) {
            //Skip non-exist class
            if (!class_exists($class = $this->get_cls(array_shift($group)))) {
                continue;
            }

            //Get trust data
            $trust_data = trustzone::init($class, $unit_pool->data);

            //Check auto_call mode
            if (!$unit_pool->conf['sys']['auto_call'] || !empty($group)) {
                $trust_data = array_intersect($trust_data, $group);
            }

            //Skip empty trust data calling
            if (empty($trust_data)) {
                continue;
            }

            //Prepend class into trust data
            array_unshift($trust_data, $class);

            //Add to trust group
            $trust_group[] = $trust_data;
        }

        unset($cmd, $cmd_group, $unit_pool, $group, $class, $trust_data);
        return $trust_group;
    }

    /**
     * Get trust CLI
     *
     * @param string $cmd
     * @param array  $cli_conf
     *
     * @return array
     */
    public function trust_cli(string $cmd, array $cli_conf): array
    {
        $cmd_group   = false !== strpos($cmd, '-') ? array_flip(explode('-', $cmd)) : [$cmd => 0];
        $trust_group = array_intersect_key($cli_conf, $cmd_group);

        unset($cmd, $cli_conf, $cmd_group);
        return $trust_group;
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
        return 0 !== strpos($class = strtr($class, '/', '\\'), '\\') ? '\\' . APP_PATH . '\\' . $class : $class;
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