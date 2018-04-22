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
    //Runtime CMD
    public static $cmd = '';

    //Runtime Data
    public static $data = [];

    //Runtime Result
    public static $result = [];

    //CGI/CLI CMD
    protected static $cgi_cmd = [];
    protected static $cli_cmd = [];

    //CGI/CLI Data
    protected static $cgi_data = [];
    protected static $cli_data = ['time' => 0, 'pipe' => '', 'argv' => [], 'ret' => false, 'log' => false];

    //CGI/CLI Config
    protected static $conf_cgi = [];
    protected static $conf_cli = ['PHP_EXE' => 'OS Controller'];

    //CORS Config
    private static $conf_cors = [];

    //Preload Config
    private static $conf_pre_run  = [];
    private static $conf_pre_load = [];

    //Config file path
    const CONF_PATH = ROOT . '/core/conf.ini';

    /**
     * Load config file
     *
     * @throws \Exception
     */
    public static function load_conf(): void
    {
        $path = realpath(self::CONF_PATH);
        if (false === $path) return;

        $conf = parse_ini_file($path, true);
        if (false === $conf) return;

        if (isset($conf['CGI'])) self::$conf_cgi = &$conf['CGI'];
        if (isset($conf['CLI'])) self::$conf_cli += $conf['CLI'];

        if (isset($conf['CORS'])) self::$conf_cors = &$conf['CORS'];

        if (isset($conf['Pre-Run'])) self::$conf_pre_run = &$conf['Pre-Run'];
        if (isset($conf['Pre-Load'])) self::$conf_pre_load = &$conf['Pre-Load'];

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
     * Load data parser
     */
    public static function load_parser(): void
    {
        //Call Pre-Run Methods
        self::pre_run();

        //Detect SAPI Method
        if ('cli' !== PHP_SAPI) {
            //Read HTTP data
            self::read_http();

            //Read INPUT data
            self::read_input();

            //Prepare & Parse CMD
            if (self::prep_cmd()) self::parse_cmd(false);
        } else {
            //Read OPTION data
            $optind = self::read_opt();

            //Prepare CMD
            $get_cmd = self::prep_cmd();
            if ($get_cmd) self::parse_cmd(true);

            //Extract arguments
            $argument = array_slice($_SERVER['argv'], $optind);
            if (empty($argument)) return;

            //Redirect CMD to first argument
            if (!$get_cmd) self::$data['c'] = array_shift($argument);

            //Merge rest data to $cli_data['argv']
            if (!empty($argument)) self::$cli_data['argv'] = &$argument;

            //Prepare & Parse CMD
            if (self::prep_cmd()) self::parse_cmd(true);

            unset($optind, $get_cmd, $argument);
        }
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
            if (!isset($opt[$key])) continue;

            $result['get'] = true;
            $result['data'] = $opt[$key];

            unset($opt[$key]);
        }

        unset($keys, $key);
        return $result;
    }

    /**
     * Get Root Class
     *
     * @param string $lib
     *
     * @return string
     */
    protected static function prep_class(string $lib): string
    {
        $lib = trim($lib, '/\\');
        $lib = '\\' . strtr($lib, '/', '\\');

        return $lib;
    }

    /**
     * Read OPTION data
     *
     * @return int
     */
    private static function read_opt(): int
    {
        /**
         * CLI options
         *
         * c/cmd: commands (separated by "-" when multiple)
         * d/data: CGI data content
         * p/pipe: CLI pipe content
         * r/ret: process return option
         * l/log: process log option (cmd, data, error, result)
         * t/time: read time (in microseconds; default "0" means read till done. Works when r/ret or l/log is set)
         */
        $opt = getopt('c:d:p:t:rl', ['cmd:', 'data:', 'pipe', 'time:', 'ret', 'log'], $optind);
        if (empty($opt)) return $optind;

        //Process CMD data
        $val = self::opt_val($opt, ['c', 'cmd']);
        if ($val['get'] && '' !== $val['data']) self::$data += ['c' => &$val['data']];

        //Process CGI data
        $val = self::opt_val($opt, ['d', 'data']);
        if ($val['get'] && '' !== $val['data']) self::parse_data($val['data']);

        //Process PIPE data
        $val = self::opt_val($opt, ['p', 'pipe']);
        if ($val['get'] && '' !== $val['data']) self::$cli_data['pipe'] = &$val['data'];

        //Process pipe read time
        $val = self::opt_val($opt, ['t', 'time']);
        if ($val['get'] && is_numeric($val['data'])) self::$cli_data['time'] = (int)$val['data'];

        //Process return option
        $val = self::opt_val($opt, ['r', 'ret']);
        if ($val['get']) self::$cli_data['ret'] = true;

        //Process log option
        $val = self::opt_val($opt, ['l', 'log']);
        if ($val['get']) self::$cli_data['log'] = true;

        unset($opt, $val);
        return $optind;
    }

    /**
     * Get data from HTTP Request
     */
    private static function read_http(): void
    {
        //Collecting data
        if (!empty($_FILES)) self::$data += $_FILES;
        if (!empty($_POST)) self::$data += $_POST;
        if (!empty($_GET)) self::$data += $_GET;
    }

    /**
     * Get data from raw input stream
     */
    private static function read_input(): void
    {
        $input = file_get_contents('php://input');
        if (false === $input) return;

        $data = json_decode($input, true);
        if (is_array($data) && !empty($data)) self::$data += $data;

        unset($input, $data);
    }

    /**
     * Call Pre-Run Methods
     */
    private static function pre_run(): void
    {
        if (empty(self::$conf_pre_run)) return;

        foreach (self::$conf_pre_run as $key => $item) {
            $class = self::prep_class($key);

            if (is_string($item)) forward_static_call([$class, $item]);
            else foreach ($item as $method) forward_static_call([$class, $method]);
        }

        unset($key, $item, $class, $method);
    }

    /**
     * Merge Pre-Load Methods
     */
    private static function pre_load(): void
    {
        if (empty(self::$conf_pre_load)) return;

        $load = [];
        foreach (self::$conf_pre_load as $key => $item) {
            $load[] = $key;
            if (is_string($item)) $load[] = $item;
            else foreach ($item as $method) $load[] = $method;
        }

        if ('' !== self::$cmd) $load[] = self::$cmd;
        self::$cmd = implode('-', $load);

        unset($load, $key, $item, $method);
    }

    /**
     * Prepare "CMD" for CGI
     *
     * @param string $cmd
     *
     * @return array
     */
    private static function prep_cgi(string $cmd): array
    {
        //Explode command
        $data = false !== strpos($cmd, '-') ? explode('-', $cmd) : ('' !== $cmd ? [$cmd] : []);

        //No CGI config keys
        if (empty(self::$conf_cgi)) return $data;

        //Mapping CGI config keys
        foreach ($data as $key => $value) {
            if (!isset(self::$conf_cgi[$value])) continue;

            $data[$key] = self::$conf_cgi[$value];
            self::$cgi_data[self::$conf_cgi[$value]] = $value;
        }

        $data = array_unique($data);

        unset($cmd, $key, $value);
        return $data;
    }

    /**
     * Prepare CMD
     *
     * @return bool
     */
    private static function prep_cmd(): bool
    {
        if ('' !== self::$cmd) return true;

        $val = self::opt_val(self::$data, ['c', 'cmd']);
        if ($val['get'] && is_string($val['data'])) {
            self::$cmd = &$val['data'];
            $get = true;
        } else $get = false;

        unset($val);
        return $get;
    }

    /**
     * Parse CMD content
     *
     * @param bool $is_cli
     */
    private static function parse_cmd(bool $is_cli): void
    {
        //Merge Pre-Load Methods
        self::pre_load();

        //Fill CMD for CGI
        self::$cgi_cmd = self::prep_cgi(self::$cmd);

        //Return for CGI
        if (!$is_cli) return;

        //Build CMD for CLI
        $data = false !== strpos(self::$cmd, '-') ? explode('-', self::$cmd) : ('' !== self::$cmd ? [self::$cmd] : []);
        foreach ($data as $item) if (isset(self::$conf_cli[$item])) self::$cli_cmd[] = $item;
        self::$cli_cmd = array_unique(self::$cli_cmd);

        unset($is_cli, $data, $item);
    }

    /**
     * Parse data content
     *
     * @param string $value
     */
    private static function parse_data(string $value): void
    {
        //Decode data in JSON & HTTP Query
        $data = json_decode($value, true);
        if (!is_array($data)) parse_str($value, $data);

        //Merge data
        if (!empty($data)) self::$data += $data;

        unset($value, $data);
    }
}