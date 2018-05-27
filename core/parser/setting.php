<?php

/**
 * Setting Parser
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

class setting
{
    /**
     * Load config settings
     */
    public static function load(): void
    {
        //Running mode detection
        config::$IS_CGI = 'cli' !== PHP_SAPI;

        //HTTPS mode detection
        config::$IS_HTTPS = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        //Check config file
        $path = realpath(config::CONF_PATH);

        if (false === $path) {
            trigger_error('Config file NOT found!', E_USER_NOTICE);
            return;
        }

        //Read config file
        $conf = parse_ini_file($path, true);

        if (false === $conf) {
            trigger_error('Config file ERROR!', E_USER_WARNING);
            return;
        }

        //Set config value
        foreach ($conf as $key => $val) {
            if (isset(config::$$key)) {
                config::$$key = $val;
            }
        }

        unset($path, $conf, $key, $val);
    }
}