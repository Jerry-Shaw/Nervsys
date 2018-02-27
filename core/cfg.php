<?php

/**
 * Config File
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
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
define('NS_VER', '5.0.0');

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
    if (0 !== DEBUG) \core\ctr\router::$result[$module] = &$message;
    unset($module, $message);
}