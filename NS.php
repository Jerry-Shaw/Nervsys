<?php

/**
 * NS System script
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

declare(strict_types = 1);

use Core\Execute;
use Core\Factory;
use Core\Lib\App;
use Core\Lib\CORS;
use Core\Lib\Error;
use Core\Lib\IOUnit;
use Core\Lib\Router;

//Misc settings
set_time_limit(0);
ignore_user_abort(true);

//Require PHP version >= 7.4.0
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    exit('NervSys needs PHP 7.4.0 or higher!');
}

//Define NervSys version
const NS_VER = '8.0.2';

//Define SYSTEM ROOT path
const NS_ROOT = __DIR__;

//Define JSON formats
const JSON_FORMAT = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;
const JSON_PRETTY = JSON_FORMAT | JSON_PRETTY_PRINT;

//Autoload function
function autoload(string $class_name, string $root_path = NS_ROOT): void
{
    //Get relative path of class file
    $file_name = strtr($class_name, '\\', DIRECTORY_SEPARATOR) . '.php';

    if (false === strpos($class_name, '\\')) {
        //Load script file from include path
        require $file_name;
    } elseif (is_file($class_file = $root_path . DIRECTORY_SEPARATOR . $file_name)) {
        //Require class file
        require $class_file;
    }

    unset($class_name, $root_path, $file_name, $class_file);
}

//Register autoload (NS_ROOT based)
spl_autoload_register(
    static function (string $class_name): void
    {
        autoload($class_name);
        unset($class_name);
    }
);

//Init App library
App::new();

/**
 * Class NS
 */
class NS extends Factory
{
    /**
     * NS constructor.
     */
    public function __construct()
    {
        //Init Error library
        $error = Error::new();

        //Register error handler
        register_shutdown_function($error->shutdown_handler);
        set_exception_handler($error->exception_handler);
        set_error_handler($error->error_handler);

        //Init App library
        $app = App::new();

        //Set default timezone
        date_default_timezone_set($app->timezone);

        //Check CORS Permission
        CORS::new()->checkPerm($app->is_cli, $app->is_tls);

        //Init IOUnit library
        $IOUnit = IOUnit::new();

        //Read input data
        !$app->is_cli ? $IOUnit->readCgi() : $IOUnit->readCli();

        if ('' !== ($IOUnit->src_cmd = trim($IOUnit->src_cmd))) {
            //Init Router library
            $router = Router::new()->parse($IOUnit->src_cmd);

            //Init Execute Module
            $execute = Execute::new()->copyCmd($router);

            //Execute process & fetch results
            $IOUnit->src_output += $execute->callCli();
            $IOUnit->src_output += $execute->callCgi();
        }

        //Output results
        $IOUnit->output();
    }
}