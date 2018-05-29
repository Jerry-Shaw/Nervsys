<?php

/**
 * API Entry
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

//Declare strict types
declare(strict_types = 1);

//Check PHP version
if (version_compare(PHP_VERSION, '7.1.0', '<')) {
    exit('NervSys needs PHP 7.1.0 or higher!');
}

//Set time limit
set_time_limit(0);

//Set error level
error_reporting(E_ALL);

//Set ignore user abort
ignore_user_abort(true);

//Set default timezone
date_default_timezone_set('PRC');

//Set response header
header('Content-Type: application/json; charset=utf-8');

//Define NervSys version
define('NS_VER', '6.0.0');

//Define absolute root path
define('ROOT', realpath(__DIR__));

//Register autoload function
spl_autoload_register(
    static function (string $library): void
    {
        if (false === strpos($library, '\\')) {
            return;
        }

        //Check library path
        $lib_file = realpath(ROOT . DIRECTORY_SEPARATOR . strtr($library, '\\', DIRECTORY_SEPARATOR) . '.php');

        if (is_string($lib_file)) {
            require $lib_file;
        }

        unset($library, $lib_file);
    }
);

//Start error handler
\core\helper\error::start();

//Start observer handler
\core\handler\observer::start();

//Output observer collection
echo \core\handler\observer::collect();