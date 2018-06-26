<?php

/**
 * API Entry
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

//Declare strict types
declare(strict_types = 1);

//Check PHP version
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('NervSys needs PHP 7.2.0 or higher!');
}

//Set error level
error_reporting(E_ALL | E_STRICT);

//Set time limit
set_time_limit(0);

//Set ignore user abort
ignore_user_abort(true);

//Set default timezone
date_default_timezone_set('PRC');

//Set response header
header('Content-Type: application/json; charset=utf-8');

//Load initial script
require __DIR__ . '/core/system.php';

//Start system
\core\system::load();
\core\system::start();

//Output JSON result
\core\parser\output::json();