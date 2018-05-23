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

use core\pool\cmd as pool_cmd;
use core\pool\conf as pool_conf;

class cmd
{
    /**
     * Prepare cmd
     */
    public static function prep_cmd(): void
    {
        //Extract cmd
        pool_cmd::$cmd_cgi = pool_cmd::$cmd_cli = false !== strpos(pool_cmd::$cmd, '-')
            ? explode('-', pool_cmd::$cmd)
            : [pool_cmd::$cmd];

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
        if (empty(pool_conf::$CGI)) {
            return;
        }

        //Mapping CGI config
        foreach (pool_conf::$CGI as $name => $item) {
            $key = array_search($name, pool_cmd::$cmd_cgi, true);

            if (false !== $key) {
                pool_cmd::$cmd_cgi[$key] = $item;
                pool_cmd::$param_cgi[$item] = $name;
            } else {
                foreach (pool_cmd::$cmd_cgi as $key => $val) {
                    if (0 !== strpos($val, $name)) {
                        continue;
                    }

                    $cmd = substr_replace($val, $item, 0, strlen($name));

                    pool_cmd::$cmd_cgi[$key] = $cmd;
                    pool_cmd::$param_cgi[$cmd] = $val;
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
        if (empty(pool_conf::$CLI)) {
            pool_cmd::$cmd_cli = [];
            return;
        }

        //Check CLI config
        foreach (pool_cmd::$cmd_cli as $key => $item) {
            if (!isset(pool_conf::$CLI[$item])) {
                unset(pool_cmd::$cmd_cli[$key]);
            }
        }

        unset($key, $item);
    }
}