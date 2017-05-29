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
 * This script is an universal API script for NervSys.
 * Authentication is recommended for security before running "data_pool::start()".
 */

declare(strict_types = 1);

//Load CFG file (basic function script is loaded in the cfg file as also).
require __DIR__ . '/core/_include/cfg.php';

//Detect PHP SAPI
if ('cli' !== PHP_SAPI) {
    //Code Block for CGI Mode
    //Load data_key as an overall module and start it.
    load_lib('core', 'key_visit');
    //Start data_key process
    \key_visit::start();
    //Load data_pool as an overall module and start it.
    load_lib('core', 'data_pool');
    //Start data_pool process
    \data_pool::start();
    //Valid values for "data_pool::$format" are "json" and "raw", which should be changed via GET or POST
    //All returned data will be output in JSON by default, or, kept in data pool for further use by setting to "raw"
    if ('json' === \data_pool::$format) {
        //Force output content to UTF-8 formatted plain text
        header('Content-Type: text/plain; charset=UTF-8');
        //Output JSON formatted result
        echo json_encode(\data_pool::$pool);
        exit;
    }
} else {
    //Code Block for CLI Mode
    //Force output content to UTF-8 formatted plain text
    header('Content-Type: text/plain; charset=UTF-8');
    //Load CLI Module
    load_lib('core', 'ctrl_cli');
    //Pass options
    \ctrl_cli::$opt = getopt(CLI_RUN_OPTIONS, CLI_LONG_OPTIONS, $optind);
    //Pass variables
    \ctrl_cli::$var = array_slice($argv, $optind);
    //Start CLI
    $result = \ctrl_cli::start();
    //Output Result
    if (!empty($result)) {
        //Output JSON formatted result via STDOUT
        fwrite(STDOUT, json_encode($result) . PHP_EOL);
        //Close STDOUT stream
        fclose(STDOUT);
        exit;
    }
}