<?php

/**
 * Router Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2018 秋水之冰
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

    //Allowed header
    public static $header = [];

    //Config settings
    protected static $conf_cgi = [];
    protected static $conf_cli = [];

    //Argument hash
    private static $argv_hash = '';

    //Config file path
    const conf_path = ROOT . '/core/conf.ini';

    /**
     * Load CORS file for Cross Domain Request
     */
    public static function load_cors(): void
    {
        if (!isset($_SERVER['HTTP_ORIGIN']) || $_SERVER['HTTP_ORIGIN'] === (self::is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) return;

        $unit = parse_url($_SERVER['HTTP_ORIGIN']);
        if (!isset($unit['port'])) $unit['port'] = 'https' === $unit['scheme'] ? 443 : 80;

        $file = realpath(ROOT . '/cors/' . implode('.', $unit) . '.php');
        if (false === $file) exit;

        require $file;
        unset($unit, $file);

        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        if (!empty(self::$header)) header('Access-Control-Allow-Headers: ' . implode(', ', self::$header));

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) exit;
    }

    /**
     * Load config file for CGI / CLI
     *
     * @throws \Exception
     */
    public static function load_conf(): void
    {
        $conf_path = realpath(self::conf_path);
        if (false === $conf_path) return;

        $config = parse_ini_file($conf_path, true);
        if (false === $config) return;

        if (isset($config['CGI'])) self::$conf_cgi = &$config['CGI'];
        if (isset($config['CLI'])) self::$conf_cli = &$config['CLI'];

        unset($conf_path, $config);
    }

    /**
     * Output result
     */
    public static function output(): void
    {
        //Output Runtime Values
        if (2 === DEBUG) {
            self::$result['duration'] = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 4) . 's';
            self::$result['memory'] = round(memory_get_usage(true) / 1048576, 4) . 'MB';
            self::$result['peak'] = round(memory_get_peak_usage(true) / 1048576, 4) . 'MB';
        }

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
     * Check HTTPS protocol
     *
     * @return bool
     */
    public static function is_https(): bool
    {
        return (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);
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
}