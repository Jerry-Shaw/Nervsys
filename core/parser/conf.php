<?php

/**
 * Config Parser
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

use core\pool\conf as pool_conf;

class conf
{
    /**
     * Load config settings
     */
    public static function load(): void
    {
        //Check HTTPS
        pool_conf::$IS_HTTPS = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        //Check config file
        $path = realpath(pool_conf::CONF_PATH);

        if (false === $path) {
            //todo log (debug): config not found
            return;
        }

        //Read config file
        $conf = parse_ini_file($path, true);

        if (false === $conf) {
            //todo log (debug): config error
            return;
        }

        foreach ($conf as $key => $val) {
            if (isset(pool_conf::$$key)) {
                pool_conf::$$key = $val;
            }
        }

        unset($path, $conf, $key, $val);
    }
}