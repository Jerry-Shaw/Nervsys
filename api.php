<?php

/**
 * API Script
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

declare(strict_types = 1);

//Load CFG file (basic function script is loaded in the cfg file as also).
require __DIR__ . '/core/_inc/cfg.php';

//Detect PHP SAPI
if ('cli' !== PHP_SAPI) {
    //Code Block for CGI Mode
    //Start \core\key\visit
    \core\ctrl\visit::start();
    //Start \core\ctrl\pool
    \core\ctrl\pool::start();
    //Valid values for "\core\ctrl\pool::$format" are "json" and "raw", which should be changed via GET or POST
    //All returned data will be output in JSON by default, or, kept in "\core\ctrl\pool::$pool" for further use by setting to "raw"
    if ('json' === \core\ctrl\pool::$format) {
        //Force output content to UTF-8 formatted plain text
        header('Content-Type: text/plain; charset=UTF-8');
        //Output JSON formatted result
        echo json_encode(\core\ctrl\pool::$pool);
    }
} else {
    //Code Block for CLI Mode
    //Force output content to UTF-8 formatted plain text
    header('Content-Type: text/plain; charset=UTF-8');
    //Pass CLI options
    \core\ctrl\cli::$opt = getopt(CLI_RUN_OPTIONS, CLI_LONG_OPTIONS, $optind);
    //Pass CLI variables
    \core\ctrl\cli::$var = array_slice($argv, $optind);
    //Start CLI
    $result = \core\ctrl\cli::start();
    //Output Result
    if (!empty($result)) {
        //Output JSON formatted result via STDOUT
        fwrite(STDOUT, json_encode($result) . PHP_EOL);
        //Close STDOUT stream
        fclose(STDOUT);
    }
}