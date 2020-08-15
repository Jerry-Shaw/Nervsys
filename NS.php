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

//Strict type declare
declare(strict_types = 1);

use Core\Execute;
use Core\Factory;
use Core\Lib\App;
use Core\Lib\CORS;
use Core\Lib\IOUnit;
use Core\Lib\Router;

//Require PHP version >= 7.4.0
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    exit('NervSys needs PHP 7.4.0 or higher!');
}

//Define NervSys version
define('NS_VER', '8.0.0 Alpha');

//Define SYSTEM ROOT path
define('NS_ROOT', __DIR__);

//Define JSON formats
define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

//Detect extension support
define('SPT_OPC', extension_loaded('Zend OPcache'));

//Autoload function
function Autoload(string $class_name, string $root_path = NS_ROOT): void
{
    //Get relative path of class file
    $file_name = strtr($class_name, '\\', DIRECTORY_SEPARATOR) . '.php';

    //Skip non-existent class file
    if (!is_file($class_file = $root_path . DIRECTORY_SEPARATOR . $file_name)) {
        return;
    }

    //Compile/require class file
    $file_compiled = false;

    if (SPT_OPC && 0 === strpos($class_file, NS_ROOT)) {
        $file_compiled = opcache_compile_file($class_file);
    }

    if (!$file_compiled) {
        require $class_file;
    }

    unset($class_name, $root_path, $file_name, $class_file, $file_compiled);
}

//Compile/require Factory module
Autoload(Factory::class);

//Register autoload (NS_ROOT based)
spl_autoload_register(
    static function (string $class_name): void
    {
        Autoload($class_name);
    }
);

/**
 * Class NS
 */
class NS extends Factory
{
    private App $app;

    /**
     * NS constructor.
     */
    public function __construct()
    {
        //Misc settings
        set_time_limit(0);
        ignore_user_abort(true);

        //Set error_reporting level
        error_reporting(E_ALL);

        //Init App library
        $App = App::new();

        //Copy App library
        $this->app = &$App;

        //Set default timezone
        date_default_timezone_set($App->timezone);

        //Set include path
        set_include_path($App->root_path . DIRECTORY_SEPARATOR . $App->inc_path);

        //Register autoload ($App->root_path based)
        spl_autoload_register(
            static function (string $class_name) use ($App): void
            {
                Autoload($class_name, $App->root_path);
            }
        );
    }

    /**
     * Run NS system
     */
    public function run(): void
    {
        //Init Error library
        $Error = \Core\Lib\Error::new();

        //Register error handler
        register_shutdown_function($Error->shutdown_handler);
        set_exception_handler($Error->exception_handler);
        set_error_handler($Error->error_handler);

        //Check CORS Permission
        CORS::new()->checkPerm($this->app);

        //Input date parser
        $IOUnit = IOUnit::new();

        //Call data reader
        call_user_func(!$this->app->is_cli ? $IOUnit->cgi_reader : $IOUnit->cli_reader);

        //Init Execute Module
        $Execute = Execute::new(Router::new()->parse($IOUnit->src_cmd));

        //Fetch results
        $IOUnit->src_output += $Execute->callScript();
        $IOUnit->src_output += $Execute->callProgram();

        //Output results
        call_user_func($IOUnit->output_handler, $IOUnit);
    }
}