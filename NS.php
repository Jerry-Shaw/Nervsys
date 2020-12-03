<?php

/**
 * NS System script
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

//Detect extension support
define('SPT_OPC', extension_loaded('Zend OPcache'));

//Autoload function
function autoload(string $class_name, string $root_path = NS_ROOT): void
{
    //Get relative path of class file
    $file_name = strtr($class_name, '\\', DIRECTORY_SEPARATOR) . '.php';

    //Load script file from include path
    if (false === strpos($class_name, '\\')) {
        require $file_name;
        return;
    }

    //Skip non-existent class file
    if (!is_file($class_file = $root_path . DIRECTORY_SEPARATOR . $file_name)) {
        return;
    }

    $file_compiled = false;

    //Compile class file
    if (SPT_OPC && 0 === strpos($class_file, NS_ROOT)) {
        $file_compiled = opcache_compile_file($class_file);
    }

    //Require class file
    if (!$file_compiled) {
        require $class_file;
    }

    unset($class_name, $root_path, $file_name, $class_file, $file_compiled);
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

//Init App ENV
$app = App::new();

//Register autoload ($app->root_path based)
spl_autoload_register(
    static function (string $class_name) use ($app): void
    {
        autoload($class_name, $app->root_path);
        unset($class_name, $app);
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
        //Init App
        $app = App::new();

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

        //Input date parser
        $io_unit = IOUnit::new();

        //Call data reader
        call_user_func(!$app->is_cli ? $io_unit->cgi_reader : $io_unit->cli_reader);

        //Init Execute Module
        $execute = Execute::new();

        //Set commands
        $execute->setCmd(Router::new()->parse($io_unit->src_cmd));

        //Fetch results
        $io_unit->src_output += $execute->callCgi();
        $io_unit->src_output += $execute->callCli();

        //Output results
        call_user_func($io_unit->output_handler, $io_unit);
    }
}