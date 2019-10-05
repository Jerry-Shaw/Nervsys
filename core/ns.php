<?php

/**
 * NS System script
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core;

//Require PHP version >= 7.2.0
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('NervSys needs PHP 7.2.0 or higher!');
}

//Define NervSys version
define('SYSVER', '7.4.0 Beta');

//Define system root path
define('SYSROOT', dirname(__DIR__));

//Find entry script file path
$entry_script = false === strpos($_SERVER['SCRIPT_FILENAME'], $cwd_path = getcwd())
    ? $cwd_path . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_FILENAME']
    : $_SERVER['SCRIPT_FILENAME'];

//Find true ROOT path
$sys_path   = explode(DIRECTORY_SEPARATOR, SYSROOT);
$entry_path = explode(DIRECTORY_SEPARATOR, $entry_script);
$root_path  = implode(DIRECTORY_SEPARATOR, array_intersect($sys_path, $entry_path));

//Define ROOT path
define('ROOT', $root_path);

//Define entry script file path
define('ENTRY_SCRIPT', $entry_script);

//Free memory
unset($entry_script, $cwd_path, $sys_path, $entry_path, $root_path);

//Define JSON formats
define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

//Set only include path to ROOT/include
set_include_path(ROOT . DIRECTORY_SEPARATOR . 'include');

//Register autoload function
spl_autoload_register(
    static function (string $class): void
    {
        //Load class file without namespace directly from include path
        if (false === strpos($class, '\\')) {
            require $class . '.php';
            return;
        }

        //Get relative path of target class file
        $file = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

        //Load class file from SYSROOT or ROOT
        foreach ([ROOT, SYSROOT] as $path) {
            if (is_file($class_file = $path . DIRECTORY_SEPARATOR . $file)) {
                require $class_file;
                break;
            }
        }

        unset($class, $file, $path, $class_file);
    }
);

//Error library
use core\lib\error;

//Register error handler
register_shutdown_function([error::class, 'shutdown_handler']);
set_exception_handler([error::class, 'exception_handler']);
set_error_handler([error::class, 'error_handler']);


/**
 * Class ns
 *
 * @package core
 */
class ns
{
    //Customized libraries
    public static $io     = core\lib\io::class;
    public static $log    = core\lib\log::class;
    public static $router = core\lib\router::class;


    //Current error level
    protected static $err_lv = E_ALL;


    public static function boot(): void
    {
        //Get error_reporting level
        self::$err_lv = error_reporting();


    }


}