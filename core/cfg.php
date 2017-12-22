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

//Debug mode
define('DEBUG', true);

//Basic Settings
set_time_limit(0);
ignore_user_abort(true);
error_reporting(DEBUG ? E_ALL : 0);
date_default_timezone_set('PRC');
header('Content-Type: application/json; charset=utf-8');

//NervSys Version
define('NS_VER', '5.0.0');

//Document Root Definition
define('ROOT', realpath(substr(__DIR__, 0, -4)));

//Autoload function
spl_autoload_register('load');

/**
 * Load function
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
    if (DEBUG) 'cli' !== PHP_SAPI ? \core\ctr\router::$result[$module] = &$message : fwrite(STDOUT, $module . ': ' . $message . PHP_EOL);
    unset($module, $message);
}