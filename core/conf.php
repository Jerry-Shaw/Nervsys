<?php

/**
 * Config File
 *
 * Copyright 2016-2018 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
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

/**
 * Debug mode
 * 0: for production environment (Disable ERROR Reporting)
 * 1: for development environment (Display all ERROR, WARNING, NOTICE)
 * 2: for optimization development (Display all ERROR, WARNING, NOTICE and Runtime Values)
 */
define('DEBUG', 1);

//Basic Settings
set_time_limit(0);
ignore_user_abort(true);
error_reporting(0 === DEBUG ? 0 : E_ALL);
date_default_timezone_set('PRC');
header('Content-Type: application/json; charset=utf-8');

//NervSys Version
define('NS_VER', '5.1.8');

//JSON Encode Options
define(
    'JSON_OPT',
    JSON_PRETTY_PRINT |
    JSON_NUMERIC_CHECK |
    JSON_BIGINT_AS_STRING |
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE |
    JSON_PRESERVE_ZERO_FRACTION |
    JSON_PARTIAL_OUTPUT_ON_ERROR |
    JSON_UNESCAPED_LINE_TERMINATORS
);

//Root Path Definition
define('ROOT', realpath(substr(__DIR__, 0, -4)));

//Register autoload function
spl_autoload_register('load');

/**
 * Autoload function
 *
 * @param string $lib
 */
function load(string $lib): void
{
    if (false === strpos($lib, '\\')) return;

    $file = realpath(ROOT . '/' . strtr($lib, '\\', '/') . '.php');
    if (false !== $file) require $file;

    unset($lib, $file);
}

/**
 * Debug function
 *
 * @param string $module
 * @param string $message
 */
function debug(string $module, string $message): void
{
    if (0 === DEBUG) return;
    \core\ctr\router::$result[$module] = &$message;

    unset($module, $message);
}