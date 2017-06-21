<?php

/**
 * Basic Configurations
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

//Basic Settings
set_time_limit(0);
error_reporting(E_ALL);
ignore_user_abort(true);
date_default_timezone_set('PRC');
header('Content-Type:text/html; charset=utf-8');

//Document Root Definition
define('ROOT', realpath(substr(__DIR__, 0, -10)));

//Enable/Disable HTTP GET Method
define('ENABLE_GET', true);

//Enable/Disable API Safe Zone
define('SECURE_API', true);

//Enable/Disable Language for Error Module
define('ERROR_LANG', true);

//Define Encrypt/Decrypt module properties
define('CRYPT_NAME', '\\core\\key\\crypt');

//Define Online State Tags
define('ONLINE_TAGS', ['uuid', 'char']);

//Define Available languages
define('LANGUAGE_LIST', ['en-US', 'zh-CN']);

//File Storage Server Settings
define('FILE_PATH', 'E:/Sites/Storage/');
define('FILE_DOMAIN', 'https://YOUR DOMAIN/');

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
define('Redis_SESSION', false);

//SMTP Mail Settings
define('SMTP_HOST', 'SMTP_HOST');
define('SMTP_PORT', 465);
define('SMTP_USER', 'SMTP_USER');
define('SMTP_PWD', 'SMTP_PWD');
define('SMTP_SENDER', 'SMTP_SENDER');

//CLI Settings
define('CLI_LOG_PATH', ROOT . '/temp/cli/log/');//Log path
define('CLI_WORK_PATH', ROOT . '/temp/cli/temp/');//Working path
define('CLI_EXEC_PATH', '"D:/Programs/WebServer/Programs/PHP/php.exe"');//PHP executable binary path
define('CLI_RUN_OPTIONS', 'c:m:d:g:t:w:p:l');//Short options (Equal to Long Options)
define('CLI_LONG_OPTIONS', ['cmd:', 'map:', 'data:', 'get:', 'try:', 'wait:', 'path:', 'log']);//Long options (Preferred)

//OpenSSL Settings
define('SSL_CFG',
       [
           'config'           => 'D:/Programs/WebServer/Programs/PHP/extras/ssl/openssl.cnf',
           'digest_alg'       => 'sha256',
           'private_key_bits' => 2048,
           'private_key_type' => OPENSSL_KEYTYPE_RSA
       ]
);

//Autoload function
spl_autoload_register(function ($library) {
    if (!class_exists($library, true) && false !== strpos($library, '\\')) {
        $path = explode('\\', $library, 2);
        $file = realpath(ROOT . '/' . $path[0] . '/_inc/' . $path[1] . '.php');
        if (false !== $file) require $file;
        unset($path, $file);
    }
    unset($library);
});

//Load basic function script
require __DIR__ . '/fn.php';