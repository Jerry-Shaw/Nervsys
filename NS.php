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
define('NS_VER', '8.0.0');

//Define SYSTEM ROOT path
define('NS_ROOT', __DIR__);

//Define JSON formats
define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

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

//Compile/require Factory module
autoload(Factory::class);

//Register autoload (NS_ROOT based)
spl_autoload_register(
    static function (string $class_name): void
    {
        autoload($class_name);
        unset($class_name);
    }
);

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
        //Init App library with environment
        $app = App::new()->setEnv();

        //Set default timezone
        date_default_timezone_set($app->timezone);

        //Init Error library
        $error = Error::new();

        //Register error handler
        register_shutdown_function($error->shutdown_handler);
        set_exception_handler($error->exception_handler);
        set_error_handler($error->error_handler);

        //Check CORS Permission
        CORS::new()->checkPerm($app);

        //Init IOUnit library
        $io_unit = IOUnit::new();

        //Init Router library
        $router = Router::new();

        //Init Execute Module
        $execute = Execute::new();

        if (!$app->is_cli) {
            //Read CGI input data
            $io_unit->readCgi();
        } else {
            //Read CLI argv data
            $io_unit->readCli();

            //Parse CLI cmd value
            if (!empty($cmd_cli = $router->parse($io_unit->src_cmd, $router->cli_stack))) {
                //Execute CLI process & fetch results
                $io_unit->src_output += $execute->setCmd('cmd_cli', $cmd_cli)->callCli();
            }
        }

        //Execute CGI handler & fetch results
        $io_unit->src_output += $execute->setCmd('cmd_cgi', $router->parse($io_unit->src_cmd, $router->cgi_stack))->callCgi();

        //Output results
        $io_unit->output();
    }
}