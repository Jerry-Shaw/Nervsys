<?php

/**
 * API Script
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
 * This script is an universal API script.
 * Access-Key check is recommended for higher security.
 * Enable GET Method can be controlled by "\data_pool::$enable_get".
 * But it is strongly not recommended to enable GET Method.
 * Just simply leave it as it is for system security.
 * Or, only enable it when debug is needed.
 */

declare(strict_types = 1);

//Load CFG file (basic function script is loaded in the cfg file as also).
require __DIR__ . '/core/_include/cfg.php';

//Load data_key as an overall module and start it.
load_lib('core', 'data_key');
//Start data_key process
\data_key::start();

//Load data_pool as an overall module and start it.
load_lib('core', 'data_pool');
//Start data_pool process
\data_pool::start();

//"json" is the default output format, "raw" data will be kept if no "cmd" is detected
//Keep running the rest script if user needs raw data which is kept in the data pool
if ('json' === \data_pool::$format) {
    header('Content-Type: text/plain; charset=UTF-8');
    echo json_encode(\data_pool::$data);
    exit;
}