<?php

/**
 * Router Module
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

namespace core\ctr;

use core\ctr\router\cgi, core\ctr\router\cli;

class router
{
    //Argument cmd
    public static $cmd = '';

    //Argument data
    public static $data = [];

    //Result data
    public static $result = [];

    //Data Structure
    public static $struct = [];

    //Allowed Origins & headers
    public static $cross_origins = [];
    public static $cross_headers = [];

    //Argument hash
    private static $argv_hash = '';

    /**
     * Router start
     */
    public static function start(): void
    {
        self::cross_origin();

        'cli' !== PHP_SAPI ? cgi::run() : cli::run();

        if (2 > DEBUG) return;

        //Debug with Runtime Values
        debug('duration', round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's');
        debug('memory', round(memory_get_usage(true) / 1048576, 4) . 'MB');
        debug('peak', round(memory_get_peak_usage(true) / 1048576, 4) . 'MB');
    }

    /**
     * Output result
     */
    public static function output(): void
    {
        //Build result
        switch (count(self::$result)) {
            case 0:
                $output = '';
                break;
            case 1:
                $output = json_encode(current(self::$result), JSON_OPT);
                break;
            default:
                $output = json_encode(self::$result, JSON_OPT);
                break;
        }

        //Output result
        echo 'cli' !== PHP_SAPI ? $output : $output . PHP_EOL;
        unset($output);
    }

    /**
     * Extract values from options
     *
     * @param array $opt
     * @param array $keys
     *
     * @return array
     */
    protected static function opt_val(array &$opt, array $keys): array
    {
        $result = ['get' => false, 'data' => ''];

        foreach ($keys as $key) {
            if (isset($opt[$key])) {
                $result = ['get' => true, 'data' => $opt[$key]];
                unset($opt[$key]);
            }
        }

        unset($keys, $key);
        return $result;
    }

    /**
     * Build data structure
     */
    protected static function build_struc(): void
    {
        $struc = array_keys(self::$data);
        $hash = hash('sha256', implode('|', $struc));

        if (self::$argv_hash === $hash) return;

        self::$struct = &$struc;
        self::$argv_hash = &$hash;

        unset($struc, $hash);
    }

    /**
     * Start Cross-Origin Resource Sharing
     */
    private static function cross_origin(): void
    {
        if (empty(self::$cross_origins) || 'OPTIONS' !== $_SERVER['REQUEST_METHOD']) return;

        header('Access-Control-Allow-Methods: OPTIONS');

        foreach (self::$cross_origins as $origin) header('Access-Control-Allow-Origin: ' . $origin);

        if (!empty(self::$cross_headers)) header('Access-Control-Allow-Headers: ' . implode(', ', self::$cross_headers));

        unset($origin);
        exit;
    }
}