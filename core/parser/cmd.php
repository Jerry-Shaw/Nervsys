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

use core\pool\order;
use core\pool\config;

class cmd
{
    /**
     * Prepare cmd
     */
    public static function prep(): void
    {
        //Extract cmd
        order::$cmd_cgi = order::$cmd_cli = false !== strpos(order::$cmd, '-')
            ? explode('-', order::$cmd)
            : [order::$cmd];

        //Prepare CGI cmd
        self::prep_cgi();

        //Prepare CLI cmd
        self::prep_cli();
    }


    /**
     * Prepare cgi cmd
     */
    private static function prep_cgi(): void
    {
        if (empty(config::$CGI)) {
            return;
        }

        //Mapping CGI config
        foreach (config::$CGI as $name => $item) {
            $key = array_search($name, order::$cmd_cgi, true);

            if (false !== $key) {
                order::$cmd_cgi[$key] = $item;
                order::$param_cgi[$item] = $name;
            } else {
                foreach (order::$cmd_cgi as $key => $val) {
                    if (0 !== strpos($val, $name)) {
                        continue;
                    }

                    $cmd = substr_replace($val, $item, 0, strlen($name));

                    order::$cmd_cgi[$key] = $cmd;
                    order::$param_cgi[$cmd] = $val;
                }
            }
        }

        unset($name, $item, $key, $val, $cmd);
    }

    /**
     * Prepare cli cmd
     */
    private static function prep_cli(): void
    {
        if (empty(config::$CLI)) {
            order::$cmd_cli = [];
            return;
        }

        //Check CLI config
        foreach (order::$cmd_cli as $key => $item) {
            if (!isset(config::$CLI[$item])) {
                unset(order::$cmd_cli[$key]);
            }
        }

        unset($key, $item);
    }
}