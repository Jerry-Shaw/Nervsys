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

use core\handler\operator;
use core\handler\platform;

use core\pool\command;
use core\pool\setting;

class cmd extends command
{
    /**
     * Prepare CMD
     */
    public static function prep(): void
    {
        //Check CMD
        if ('' === self::$cmd) {
            operator::stop('CMD NOT found!');
        }

        //Extract CMD
        $cmd = false !== strpos(self::$cmd, '-') ? explode('-', self::$cmd) : [self::$cmd];

        //Prepare CLI CMD
        if (!setting::$is_cgi) {
            self::$cmd_cli = self::prep_cli($cmd);
        }

        //Prepare CGI CMD
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
        //Check PHP command
        if (in_array('PHP', $cmd, true)) {
            setting::$cli['PHP'] = platform::sys_path();
        }

        //Check CLI setting
        if (empty(setting::$cli)) {
            return [];
        }

        $order = [];

        //Build CLI CMD
        foreach ($cmd as $key => $item) {
            if (isset(setting::$cli[$item]) && '' !== setting::$cli[$item]) {
                $order[$item] = setting::$cli[$item];
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
        if (empty(setting::$cgi)) {
            return $cmd;
        }

        //Mapping CGI config
        foreach (setting::$cgi as $name => $item) {
            if (!empty($keys = array_keys($cmd, $name, true))) {
                //Replace mapped CMD
                foreach ($keys as $key) {
                    $cmd[$key] = $item;
                }

                //Add mapping params
                self::$param_cgi[$item] = $name;
            } else {
                foreach ($cmd as $key => $val) {
                    if (0 === strpos($val, $name)) {
                        //Replace mapped CMD
                        $cmd[$key] = substr_replace($val, $item, 0, strlen($name));

                        //Add mapping params
                        self::$param_cgi[$cmd[$key]] = $val;
                    }
                }
            }
        }

        //Rebuild CGI CMD
        $order = false !== strpos($val = implode('-', $cmd), '-') ? explode('-', $val) : [$val];

        unset($cmd, $name, $item, $keys, $key, $val);
        return $order;
    }
}