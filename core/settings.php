<?php

/**
 * Setting script
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

//Check PHP version
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('NervSys needs PHP 7.2.0 or higher!');
}

//Set time limit
set_time_limit(0);

//Set ignore user abort
ignore_user_abort(true);

//Set default timezone
date_default_timezone_set('PRC');

//Set response header
header('Content-Type: application/json; charset=utf-8');

//Define NervSys version
define('NS_VER', '6.2.0');

//Define absolute root path
define('ROOT', substr(realpath(__DIR__), 0, -4));

//Register autoload function
spl_autoload_register(
    static function (string $lib): void
    {
        if (false !== strpos($lib, '\\')) {
            //Load from namespace path
            if (false !== $file = realpath(ROOT . strtr($lib, '\\', DIRECTORY_SEPARATOR) . '.php')) {
                require $file;
            }

            unset($file);
        } else {
            //Load from include path
            require $lib . '.php';
        }

        unset($lib);
    }
);