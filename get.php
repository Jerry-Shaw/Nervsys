<?php

/**
 * ACT Script
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
 * Copyright 2016-2017 秋水之冰
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
 * This script is made for special GET Requests.
 * Running Access-Key check firstly is recommended for higher security.
 * Don't make it as the same as API script, it is for known GET Requests only.
 */

declare(strict_types = 1);

//Load CFG file (basic function script is loaded in the cfg file as also).
require __DIR__ . '/core/_include/cfg.php';

//Load data_key as an overall module and start it.
load_lib('core', 'data_key');
//Define client to 'LOCAL'
\data_key::$client = 'LOCAL';
//Start data_key process
\data_key::start();

//Set default values
$act = '';

//Extract GET values
extract($_GET);

//Run Methods dependently
switch ($act) {
    default:
        $header_content = false !== strpos($_SERVER['REQUEST_URI'], '&amp;') ? 'Location: ' . htmlspecialchars_decode($_SERVER['REQUEST_URI']) : 'HTTP/1.0 404 Not Found';
        header($header_content);
        break;
}