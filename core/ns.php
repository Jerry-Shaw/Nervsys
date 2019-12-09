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

//Strict type declare
declare(strict_types = 1);

//Namespace declare
namespace core;

//Misc settings
set_time_limit(0);
ignore_user_abort(true);

//Require PHP version >= 7.2.0
if (version_compare(PHP_VERSION, '7.2.0', '<')) {
    exit('NervSys needs PHP 7.2.0 or higher!');
}

//Define NervSys version
define('SYSVER', '7.4.0');

//Define system root path
define('SYSROOT', dirname(__DIR__));

//Get script file as entry script
$entry_script = strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);

//Check absolute path of entry script and correct
if (DIRECTORY_SEPARATOR !== $entry_script[0] && ':' !== $entry_script[1]) {
    $entry_script = getcwd() . DIRECTORY_SEPARATOR . $entry_script;
}

//Explode SYSROOT path and entry script path
$sys_path   = explode(DIRECTORY_SEPARATOR, SYSROOT);
$entry_path = explode(DIRECTORY_SEPARATOR, $entry_script);

//Compare and get ROOT path
$root_path = implode(DIRECTORY_SEPARATOR, array_intersect($sys_path, $entry_path));

//Define ROOT path
define('ROOT', $root_path);

//Define APP path (ROOT related)
define('APP_PATH', 'app');

//Define entry script path (default: api.php)
define('ENTRY_SCRIPT', $entry_script);

//Free memory
unset($entry_script, $sys_path, $entry_path, $root_path);

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
use core\lib\cli;
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
final class ns
{
    /** @var \core\lib\std\pool $unit_pool */
    private $unit_pool;

    /**
     * ns constructor.
     */
    public function __construct()
    {
        /** @var \core\lib\std\pool unit_pool */
        $this->unit_pool = factory::build(pool::class);

        /** @var \core\lib\std\router $unit_router */
        $unit_router = factory::build(router::class);

        /** @var \core\lib\cgi $unit_cgi */
        $unit_cgi = factory::build(cgi::class);

        /** @var \core\lib\cli $unit_cli */
        $unit_cli = factory::build(cli::class);

        /** @var \core\lib\std\io $unit_io */
        $unit_io = factory::build(io::class);

        //Load app.ini
        $conf = $this->load_ini();

        //Set default timezone
        date_default_timezone_set($conf['sys']['timezone']);

        //Verify CORS in CGI mode
        if (!$this->unit_pool->is_CLI && !$this->pass_cors($conf['cors'])) {
            exit;
        }

        //Run INIT section (ONLY CGI)
        foreach ($this->unit_pool->conf['init'] as $value) {
            try {
                //Call INIT functions using default router
                $this->unit_pool->result += $unit_cgi->call_group($unit_router->parse_cmd($value));
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable);
                $unit_io->output($this->unit_pool);
                unset($throwable);
                exit(0);
            }
        }

        //Read input data
        if ($this->unit_pool->is_CLI) {
            //Read arguments
            $data_argv = $unit_io->read_argv();

            //Copy to pool
            $this->unit_pool->cli_params['argv'] = &$data_argv['a'];
            $this->unit_pool->cli_params['pipe'] = &$data_argv['p'];
        } else {
            //Read CMD from URL
            $url_cmd = $unit_io->read_url();

            //Read data package
            $data_pack = $unit_io->read_http() + $unit_io->read_input(file_get_contents('php://input'));

            //Merge arguments
            $data_argv = [
                'c' => '' !== $url_cmd ? $url_cmd : ($data_pack['c'] ?? ''),
                'r' => $data_pack['r'] ?? 'json',
                'd' => &$data_pack
            ];

            unset($url_cmd, $data_pack);
        }

        //Copy to pool
        $this->unit_pool->cmd = &$data_argv['c'];
        $this->unit_pool->ret = &$data_argv['r'];

        //Copy input data
        $this->unit_pool->data += $data_argv['d'];

        //Append default router
        $this->unit_pool->router_stack[] = [$unit_router, 'parse_cmd'];

        //Proceed CGI once CMD can be parsed
        foreach ($this->unit_pool->router_stack as $router) {
            if (!empty($this->unit_pool->cgi_group = call_user_func($router, $data_argv['c']))) {
                $this->unit_pool->result += $unit_cgi->call_service();
                break;
            }
        }

        //Proceed CLI once CMD can be parsed
        if ($this->unit_pool->is_CLI && !empty($this->unit_pool->cli_group = $unit_router->cli_get_trust($data_argv['c'], $this->unit_pool->conf['cli']))) {
            $this->unit_pool->result += $unit_cli->call_program();
        }

        //Output data
        $unit_io->output($this->unit_pool);
        unset($unit_router, $unit_cgi, $unit_cli, $unit_io, $conf, $value, $data_argv, $router);
    }

    /**
     * Load app.ini
     */
    private function load_ini(): array
    {
        if (is_file($app_ini = ROOT . DIRECTORY_SEPARATOR . APP_PATH . DIRECTORY_SEPARATOR . 'app.ini')) {
            $app_conf = parse_ini_file($app_ini, true, INI_SCANNER_TYPED);

            //Update conf values
            foreach ($app_conf as $key => $value) {
                $this->unit_pool->conf[$key = strtolower($key)] = array_replace_recursive($this->unit_pool->conf[$key], $value);
            }

            unset($app_conf, $key, $value);
        }

        unset($app_ini);
        return $this->unit_pool->conf;
    }

    /**
     * Check CORS permission
     *
     * @param array $cors_conf
     *
     * @return bool
     */
    private function pass_cors(array $cors_conf): bool
    {
        //Check Server ENV
        if (!isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === ($this->unit_pool->is_TLS ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
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