<?php

/**
 * NS System script
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core;

//Require PHP version >= 7.2.0
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('NervSys needs PHP 7.2.0 or higher!');
}

//Define NervSys version
define('SYSVER', '7.4.0 Beta');

//Define system root path
define('SYSROOT', dirname(__DIR__));

//Find entry script file path
$entry_script = false === strpos($_SERVER['SCRIPT_FILENAME'], $cwd_path = getcwd())
    ? $cwd_path . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_FILENAME']
    : $_SERVER['SCRIPT_FILENAME'];

//Find true ROOT path
$sys_path   = explode(DIRECTORY_SEPARATOR, SYSROOT);
$entry_path = explode(DIRECTORY_SEPARATOR, $entry_script);
$root_path  = implode(DIRECTORY_SEPARATOR, array_intersect($sys_path, $entry_path));

//Define ROOT path
define('ROOT', $root_path);

//Define APP path (ROOT related)
define('APP_PATH', 'app');

//Define entry script file path (default: api.php)
define('ENTRY_SCRIPT', $entry_script);

//Free memory
unset($entry_script, $cwd_path, $sys_path, $entry_path, $root_path);

//Define JSON formats
define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

//Set only include path to ROOT/include
set_include_path(ROOT . DIRECTORY_SEPARATOR . 'include');

//Register autoload function
spl_autoload_register(
    static function (string $class): void
    {
        //Load class file without namespace directly from include path
        if (false === strpos($class, '\\')) {
            require $class . '.php';
            return;
        }

        //Get relative path of target class file
        $file = strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';

        //Load class file from SYSROOT or ROOT
        foreach ([ROOT, SYSROOT] as $path) {
            if (is_file($class_file = $path . DIRECTORY_SEPARATOR . $file)) {
                require $class_file;
                break;
            }
        }

        unset($class, $file, $path, $class_file);
    }
);

//Load libraries
use core\lib\cgi;
use core\lib\stc\error;
use core\lib\stc\factory;
use core\lib\std\io;
use core\lib\std\pool;
use core\lib\std\router;

//Register error handler
register_shutdown_function([error::class, 'shutdown_handler']);
set_exception_handler([error::class, 'exception_handler']);
set_error_handler([error::class, 'error_handler']);

/**
 * Class ns
 *
 * @package core
 */
class ns
{
    /** @var \core\lib\std\pool $unit_pool */
    private static $unit_pool;

    /** @var \core\lib\std\io $unit_io */
    private static $unit_io;

    /**
     * System boot
     *
     * @param bool $output
     *
     * @throws \Exception
     */
    public static function boot(bool $output = true): void
    {
        //Load app.ini
        $conf = self::load_ini();

        /** @var \core\lib\std\router $unit_router */
        $unit_router = factory::build(router::class);

        /** @var \core\lib\cgi $unit_cgi */
        $unit_cgi = factory::build(cgi::class);

        /** @var \core\lib\std\io unit_io */
        self::$unit_io = factory::build(io::class);

        //Set default timezone
        date_default_timezone_set($conf['sys']['timezone']);

        //Verify CORS in CGI mode
        if (!self::$unit_pool->is_CLI && !self::pass_cors($conf['cors'])) {
            exit;
        }

        //Run INIT section (ONLY CGI)
        foreach (self::$unit_pool->conf['init'] as $value) {
            try {
                //Call INIT functions using default router
                self::$unit_pool->result += $unit_cgi->call($unit_router->parse_cmd($value), self::$unit_pool->conf['call'], []);
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable, false);
                throw new \Exception('Initialize Failed!', E_USER_ERROR);
            }
        }

        //Read input data
        if (self::$unit_pool->is_CLI) {
            //Read arguments
            $data_argv = self::$unit_io->read_argv();
        } else {
            //Read CMD from URL
            $url_cmd = self::$unit_io->read_url();

            //Read data package
            $data_pack = self::$unit_io->read_http() + self::$unit_io->read_input(file_get_contents('php://input'));

            //Merge arguments
            $data_argv = [
                'c' => '' !== $url_cmd ? $url_cmd : ($data_pack['c'] ?? ''),
                'r' => $data_pack['r'] ?? 'json',
                'd' => &$data_pack
            ];

            unset($data_pack['c'], $data_pack['r']);
        }

        //Copy to pool
        self::$unit_pool->cmd  = &$data_argv['c'];
        self::$unit_pool->ret  = &$data_argv['r'];
        self::$unit_pool->data = &$data_argv['d'];

        //Get router stack
        $router_stack = self::$unit_pool->router_stack;

        //Append default router
        $router_stack[] = [$unit_router, 'parse_cmd'];

        //Proceed once CMD can be parsed
        foreach ($router_stack as $router) {
            if (!empty($cmd_group = call_user_func($router, $data_argv['c']))) {
                self::$unit_pool->result += $unit_cgi->call($cmd_group, self::$unit_pool->conf['call'], $data_argv['d']);
                break;
            }
        }

        //No output
        if (!$output) {
            return;
        }

        //Output
        self::output();
    }

    /**
     * System stop
     */
    public static function stop(): void
    {
        self::output();
        exit;
    }

    /**
     * System output
     */
    public static function output(): void
    {
        //Get final results
        $result = !empty(self::$unit_pool->error)
            ? self::$unit_pool->error + ['data' => self::$unit_pool->result]
            : (1 === count(self::$unit_pool->result) ? current(self::$unit_pool->result) : self::$unit_pool->result);

        //Output results
        if (in_array(self::$unit_pool->ret, ['json', 'xml'], true)) {
            echo self::$unit_io->{'build_' . self::$unit_pool->ret}((array)$result);
        }

        //Output logs
        echo '' !== self::$unit_pool->log ? PHP_EOL . PHP_EOL . self::$unit_pool->log : '';

        unset($result);
    }

    /**
     * Load app.ini
     */
    private static function load_ini(): array
    {
        /** @var \core\lib\std\pool unit_pool */
        self::$unit_pool = factory::build(pool::class);

        //Read app.ini
        if (is_file($app_ini = ROOT . DIRECTORY_SEPARATOR . APP_PATH . DIRECTORY_SEPARATOR . 'app.ini')) {
            $app_conf = parse_ini_file($app_ini, true, INI_SCANNER_TYPED);

            foreach ($app_conf as $key => $value) {
                $key = strtolower($key);

                //Update conf values
                self::$unit_pool->conf[$key] = array_replace_recursive(self::$unit_pool->conf[$key], $value);
            }

            unset($app_conf, $key, $value);
        }

        unset($app_ini);
        return self::$unit_pool->conf;
    }

    /**
     * Check CORS permission
     *
     * @param array $cors_conf
     *
     * @return bool
     */
    private static function pass_cors(array $cors_conf): bool
    {
        //Check Server ENV
        if (!isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (self::$unit_pool->is_TLS ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
            return true;
        }

        //Exit on access NOT permitted
        if (is_null($allow_headers = $cors_conf[$_SERVER['HTTP_ORIGIN']] ?? $cors_conf['*'] ?? null)) {
            return false;
        }

        //Response access allowed headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        //Skip OPTION request
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            return false;
        }

        unset($cors_conf, $allow_headers);
        return true;
    }
}