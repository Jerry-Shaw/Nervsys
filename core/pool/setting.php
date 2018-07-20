<?php

/**
 * Setting Pool
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

namespace core\pool;

class setting
{
    //Configuration
    public static $log  = [];
    public static $cgi  = [];
    public static $cli  = [];
    public static $cors = [];
    public static $init = [];
    public static $load = [];
    public static $path = [];

    //Runtime values
    public static $is_cli   = true;
    public static $is_https = true;

    //Setting file path
    const PATH = ROOT . 'core' . DIRECTORY_SEPARATOR . 'system.ini';
}