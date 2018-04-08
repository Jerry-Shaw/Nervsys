<?php

/**
 * Router Module
 *
 * Copyright 2016-2018 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017-2018 秋水之冰 <27206617@qq.com>
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

namespace core\ctr;

class router
{
    //Argument cmd
    public static $cmd = '';

    //Argument data
    public static $data = [];

    //Result data
    public static $result = [];

    //CGI / CLI settings
    protected static $conf_cgi = [];
    protected static $conf_cli = [];

    //CORS settings
    private static $conf_cors = [];

    //Config file path
    const conf_path = ROOT . '/core/conf.ini';

    /**
     * Load config file
     *
     * @throws \Exception
     */
    public static function load_conf(): void
    {
        $path = realpath(self::conf_path);
        if (false === $path) return;

        $conf = parse_ini_file($path, true);
        if (false === $conf) return;

        if (isset($conf['CGI'])) self::$conf_cgi = &$conf['CGI'];
        if (isset($conf['CLI'])) self::$conf_cli = &$conf['CLI'];
        if (isset($conf['CORS'])) self::$conf_cors = &$conf['CORS'];

        unset($path, $conf);
    }

    /**
     * Load CORS setting
     */
    public static function load_cors(): void
    {
        if (empty(self::$conf_cors) || !isset($_SERVER['HTTP_ORIGIN']) || $_SERVER['HTTP_ORIGIN'] === (self::is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) return;

        $origin = $_SERVER['HTTP_ORIGIN'];

        $unit = parse_url($origin);
        if (!isset($unit['port'])) $origin .= 'https' === $unit['scheme'] ? ':443' : ':80';

        if (!isset(self::$conf_cors[$origin])) exit;

        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . self::$conf_cors[$origin]);

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) exit;

        unset($origin, $unit);
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
        $result = ['get' => false, 'data' => null];

        foreach ($keys as $key) {
            if (isset($opt[$key])) {
                $result['get'] = true;
                $result['data'] = $opt[$key];

                unset($opt[$key]);
            }
        }

        unset($keys, $key);
        return $result;
    }
}