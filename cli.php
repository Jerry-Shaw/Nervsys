<?php

/**
 * CLI Script
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2015-2017 Jerry Shaw
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

/**
 * This script is an universal language crossing CLI script.
 * Only trusted languages and modules should be allowed to be calling
 */

declare(strict_types = 1);

//Detect PHP SAPI
if ('cli' !== PHP_SAPI) exit;

//Load CFG file (basic function script is loaded in the cfg file as also).
require __DIR__ . '/core/_include/cfg.php';

//Force to show in text/plain encoding in UTF-8
header('Content-Type: text/plain; charset=UTF-8');

//Try to get options
$option = getopt('', CLI_RUN_OPTION, $optind);

//Parse options
if (!empty($option) && isset($option['cmd']) && false !== $option['cmd'] && '' !== $option['cmd']) {
    //Internal calling

    //Clean options
    if (isset($option['map']) && (false === $option['map'] || '' === $option['map'])) unset($option['map']);
    if (isset($option['data']) && (false === $option['data'] || '' === $option['data'])) unset($option['data']);

    //Regroup CLI data
    $cli_data = ['cmd' => $option['cmd']];
    if (isset($option['map'])) $cli_data['map'] = &$option['map'];

    //Parse data content
    $data = [];
    if (isset($option['data'])) {
        parse_str($option['data'], $data);
        if (!empty($data)) $cli_data = array_merge($cli_data, $data);
    }

    //Load Data Controlling Module
    load_lib('core', 'data_pool');

    //Pass data to Data Controlling Module
    \data_pool::$cli = &$cli_data;

    //Start data_pool process
    \data_pool::start();

    //Show result in JSON
    echo json_encode(\data_pool::$pool);
} else {
    //External calling

    //Load CLI Controlling Module
    load_lib('core', 'ctrl_cli');

    //Pass variables
    \ctrl_cli::$var = &$argv;

    //Run CLI and get result
    $result = \ctrl_cli::run_cli();

    //Show result in JSON
    echo json_encode($result);
}