<?php

/**
 * CMD Parser
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

use core\system;

use core\handler\platform;

class cmd extends input
{
    /**
     * Parse CMD
     */
    public static function parse(): void
    {
        //Check CMD
        if ('' === self::$cmd) {
            system::stop();
        }

        //Extract CMD
        $cmd = false !== strpos(self::$cmd, '-') ? explode('-', self::$cmd) : [self::$cmd];

        //Prepare CMD
        self::$cmd_cli = self::prep_cli($cmd);
        self::$cmd_cgi = self::prep_cgi($cmd);

        unset($cmd);
    }

    /**
     * Prepare CLI CMD
     *
     * @param array $cmd
     *
     * @return array
     */
    private static function prep_cli(array $cmd): array
    {
        if (!self::$is_cli) {
            return [];
        }

        //Check PHP command
        if (in_array('PHP', $cmd, true)) {
            self::$cli['PHP'] = platform::sys_path();
        }

        //Check setting
        if (empty(self::$cli)) {
            return [];
        }

        //Build CMD
        $order = [];
        foreach ($cmd as $key => $item) {
            if (isset(self::$cli[$item]) && '' !== self::$cli[$item]) {
                $order[$item] = self::$cli[$item];
            }
        }

        unset($cmd, $key, $item);
        return $order;
    }

    /**
     * Prepare CGI CMD
     *
     * @param array $cmd
     *
     * @return array
     */
    private static function prep_cgi(array $cmd): array
    {
        if (empty(self::$cgi)) {
            return $cmd;
        }

        //Mapping CGI config
        foreach (self::$cgi as $name => $item) {
            if (!empty($keys = array_keys($cmd, $name, true))) {
                //Replace CMD
                foreach ($keys as $key) {
                    $cmd[$key] = $item;
                }

                //Add param
                self::$param_cgi[$item] = $name;
            } else {
                foreach ($cmd as $key => $val) {
                    if (0 === strpos($val, $name)) {
                        //Replace CMD
                        $cmd[$key] = substr_replace($val, $item, 0, strlen($name));

                        //Add param
                        self::$param_cgi[$cmd[$key]] = $val;
                    }
                }
            }
        }

        //Build CMD
        $order = false !== strpos($val = implode('-', $cmd), '-') ? explode('-', $val) : [$val];

        unset($cmd, $name, $item, $keys, $key, $val);
        return $order;
    }
}