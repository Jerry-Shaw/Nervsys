<?php

/**
 * Command Parser
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

namespace core\parser;

use core\pool\config;
use core\pool\order;

class cmd
{
    /**
     * Prepare cmd
     */
    public static function prep(): void
    {
        //Extract cmd
        $cmd = false !== strpos(order::$cmd, '-') ? explode('-', order::$cmd) : [order::$cmd];

        //Prepare CGI cmd
        order::$cmd_cgi = self::prep_cgi($cmd);

        //Prepare CLI cmd
        if (!config::$IS_CGI) {
            order::$cmd_cli = self::prep_cli($cmd);
        }
    }

    /**
     * Prepare cgi cmd
     *
     * @param array $cmd
     *
     * @return array
     */
    private static function prep_cgi(array $cmd): array
    {
        if (empty(config::$CGI)) {
            return $cmd;
        }

        //Mapping CGI config
        foreach (config::$CGI as $name => $item) {
            $keys = array_keys($cmd, $name, true);

            if (!empty($keys)) {
                foreach ($keys as $key) {
                    $cmd[$key] = $item;
                }

                order::$param_cgi[$item] = $name;
            } else {
                foreach ($cmd as $key => $value) {
                    if (0 !== strpos($value, $name)) {
                        continue;
                    }

                    $order = substr_replace($value, $item, 0, strlen($name));

                    $cmd[$key] = $order;
                    order::$param_cgi[$order] = $value;
                }
            }
        }

        //Rebuild cgi cmd
        $order = implode('-', $cmd);
        $cmd = false !== strpos($order, '-') ? explode('-', $order) : [$order];

        unset($name, $item, $keys, $key, $value, $order);
        return $cmd;
    }

    /**
     * Prepare cli cmd
     *
     * @param array $cmd
     *
     * @return array
     */
    private static function prep_cli(array $cmd): array
    {
        //Check PHP command
        if (in_array('PHP', $cmd, true)) {
            config::$CLI['PHP'] = platform::sys_env();
        }

        //Check cli config
        if (empty(config::$CLI)) {
            return [];
        }

        //Build cli cmd
        $order = [];
        foreach ($cmd as $item) {
            if (isset(config::$CLI[$item]) && '' !== config::$CLI[$item]) {
                $order[$item] = config::$CLI[$item];
            }
        }

        unset($cmd, $item);
        return $order;
    }
}