<?php

/**
 * System script
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

namespace core;

use core\handler\error;
use core\handler\operator;

use core\parser\settings;

class system
{
    public static function start(): void
    {
        //Set response header
        header('Content-Type: application/json; charset=utf-8');

        //Define NervSys version
        define('NS_VER', '6.2.2');

        //Define absolute root path
        define('ROOT', substr(strtr(__DIR__, ['/' => DIRECTORY_SEPARATOR, '\\' => DIRECTORY_SEPARATOR]), 0, -4));

        //Register autoload function
        spl_autoload_register(
            static function (string $library): void
            {
                if (false !== strpos($library, '\\')) {
                    //Load from namespace path
                    require ROOT . strtr($library, '\\', DIRECTORY_SEPARATOR) . '.php';
                } else {
                    //Load from include path
                    require $library . '.php';
                }

                unset($library);
            }
        );

        //Track error
        error::track();

        //Load settings
        settings::load();

        //Start operator
        operator::start();
    }
}