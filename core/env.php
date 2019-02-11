<?php

/**
 * ENV script
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

class env
{
    /**
     * Initialize ENV
     */
    public static function init(): void
    {
        //Require PHP version >= 7.2.0
        if (version_compare(PHP_VERSION, '7.2.0', '<')) {
            exit('NervSys needs PHP 7.2.0 or higher!');
        }

        //Define NervSys version
        define('VER', '7.2.18');

        //Define absolute root path
        define('ROOT', substr(strtr(__DIR__, ['/' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR]) . DIRECTORY_SEPARATOR, 0, -5));

        //Register autoload function
        spl_autoload_register(
            static function (string $class): void
            {
                require (false !== strpos($class, '\\') ? ROOT . strtr($class, '\\', DIRECTORY_SEPARATOR) : $class) . '.php';
                unset($class);
            }
        );

        //Register error handler
        register_shutdown_function(['core\\handler\\error', 'shutdown_handler']);
        set_exception_handler(['core\\handler\\error', 'exception_handler']);
        set_error_handler(['core\\handler\\error', 'error_handler']);
    }
}