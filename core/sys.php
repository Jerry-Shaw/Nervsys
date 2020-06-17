<?php

/**
 * NS System script
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

//Set namespace
namespace core;

//Require PHP version >= 7.2.0
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('NervSys needs PHP 7.2.0 or higher!');
}

//Get script file as entry script
$entry_script = strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);

//Check absolute path of entry script and correct
if (DIRECTORY_SEPARATOR !== $entry_script[0] && ':' !== $entry_script[1]) {
    $entry_script = getcwd() . DIRECTORY_SEPARATOR . $entry_script;
}

//Define NervSys version
define('NS_VER', '7.4.2');

//Define system root path
define('SYSROOT', dirname(__DIR__));

//Define APP pathname
define('APP_PATH', 'app');

//Define entry script path
define('ENTRY_SCRIPT', $entry_script);

//Define ROOT path
define('ROOT', is_dir($parent_path = (dirname($entry_path = dirname($entry_script)) . DIRECTORY_SEPARATOR . APP_PATH)) ? $parent_path : $entry_path);

//Define JSON formats
define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

//Free memory
unset($entry_script, $parent_path, $entry_path);

//Set only include path to ROOT/inc
set_include_path(ROOT . DIRECTORY_SEPARATOR . 'inc');

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

        //Compile/Load class file from SYSROOT or ROOT
        foreach ([ROOT, SYSROOT] as $path) {
            if (!is_file($class_file = $path . DIRECTORY_SEPARATOR . $file)) {
                continue;
            }

            $file_compiled = false;

            if (0 === strpos($class_file, __DIR__) && function_exists('opcache_compile_file')) {
                $file_compiled = opcache_compile_file($class_file);
            }

            if (!$file_compiled) {
                require $class_file;
            }

            break;
        }

        unset($class, $file, $path, $class_file, $file_compiled);
    }
);

//Load main libs
use core\lib\ns;
use core\lib\stc\error;

/**
 * Class sys
 *
 * @package core
 */
class sys
{
    static function boot(): void
    {
        //Misc settings
        set_time_limit(0);
        ignore_user_abort(true);

        //Set error_reporting level
        error_reporting(E_ALL);

        //Register error handler
        register_shutdown_function([error::class, 'shutdown_handler']);
        set_exception_handler([error::class, 'exception_handler']);
        set_error_handler([error::class, 'error_handler']);

        //Start NS Core
        new ns();
    }
}