<?php

/**
 * Basic Configurations
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author 彼岸花开 <330931138@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
 * Copyright 2016-2017 秋水之冰
 * Copyright 2016 彼岸花开
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

//Basic Settings
set_time_limit(0);
error_reporting(E_ALL);
ignore_user_abort(true);
date_default_timezone_set('PRC');
header('Content-Type:text/html; charset=utf-8');

//Document Root Definition
define('ROOT', substr(__DIR__, 0, -14));

//Enable/Disable HTTP GET Method
define('ENABLE_GET', false);

//Enable/Disable API Safe Zone
define('SECURE_API', true);

//Enable/Disable Language Module for Error Controlling Module
define('ERROR_LANG', true);

//Define the path containing Encrypt/Decrypt module
define('CRYPT_PATH', 'core');

//Define Online State Tags
define('ONLINE_TAGS', ['uuid', 'char']);

//Define Available languages
define('LANGUAGE_LIST', ['en-US', 'zh-CN']);

//File Storage Server Settings
define('FILE_PATH', 'E:/Sites/Files/');
define('FILE_DOMAIN', 'https://file.oobase.com/');

//MySQL Settings
define('MySQL_HOST', '127.0.0.1');
define('MySQL_PORT', 3306);
define('MySQL_DB', 'DB_NAME');
define('MySQL_USER', 'root');
define('MySQL_PWD', '');
define('MySQL_CHARSET', 'utf8mb4');
define('MySQL_PERSISTENT', true);

//Redis Settings
define('Redis_HOST', '127.0.0.1');
define('Redis_PORT', 6379);
define('Redis_DB', 0);
define('Redis_AUTH', '');
define('Redis_PERSISTENT', true);
define('Redis_SESSION', true);

//SMTP Mail Settings
define('SMTP_HOST', 'SMTP_HOST');
define('SMTP_PORT', 465);
define('SMTP_USER', 'SMTP_USER');
define('SMTP_PWD', 'SMTP_PWD');
define('SMTP_SENDER', 'SMTP_SENDER');

/**
 * CLI Options
 *
 * c: Config file path
 * d/l: Debug/Log options, valid values are "cmd", "err", "all", not supported by Internal Mode.
 * t: Stream content check retry times
 * w: Wait time for output
 *
 * cmd/map: Data to request an internal API
 * data: HTTP query data for Internal Mode or other strings for External Mode via STDIN communication
 */
define('CLI_CFG', ROOT . '/_cli/cfg.json');
define('CLI_LOG_PATH', ROOT . '/_cli/_log/');
define('CLI_WORK_PATH', ROOT . '/_cli/_temp/');
define('CLI_EXEC_PATH', 'D:/Programs/iisExpress/Programs/PHP/php.exe');//CLI executable binary path
define('CLI_RUN_OPTIONS', 'c::d::l::t::w::');//CLI options
define('CLI_LONG_OPTIONS', ['cmd:', 'map:', 'data:']);//Data options
define('CLI_STREAM_TRY', 20);//Default retry times for stream checking
define('CLI_STREAM_WAIT', 5);//Default time wait for stream checking (in microseconds)

//Load basic function script
require __DIR__ . '/cfg_fn.php';