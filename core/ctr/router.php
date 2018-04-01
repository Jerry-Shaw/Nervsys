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

    //Allowed header
    public static $header = [];

    //Config settings
    protected static $conf_cgi = [];
    protected static $conf_cli = [];

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

        $cors = realpath(ROOT . '/cors/' . implode('.', $unit) . '.php');
        if (false === $cors) exit;

        require $cors;

        unset($unit, $cors);

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
        $path = realpath(self::conf_path);
        if (false === $path) return;

        $conf = parse_ini_file($path, true);
        if (false === $conf) return;

        if (isset($conf['CGI'])) self::$conf_cgi = &$conf['CGI'];
        if (isset($conf['CLI'])) self::$conf_cli = &$conf['CLI'];

        unset($path, $conf);
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