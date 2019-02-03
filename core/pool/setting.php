<?php

/**
 * Setting Pool
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

namespace core\pool;

class setting extends process
{
    //Mime type
    public static $mime = '';

    //Runtime values
    public static $is_CLI = true;
    public static $is_TLS = true;

    //System settings
    protected static $sys  = [];
    protected static $log  = [];
    protected static $cgi  = [];
    protected static $cli  = [];
    protected static $cors = [];
    protected static $init = [];
    protected static $load = [];
    protected static $path = [];

    //Error reporting level
    protected static $err_lv = E_ALL | E_STRICT;

    //Log path
    const LOG_PATH = ROOT . 'logs' . DIRECTORY_SEPARATOR;

    //Config file path
    const CFG_FILE = ROOT . 'core' . DIRECTORY_SEPARATOR . 'system.ini';
}