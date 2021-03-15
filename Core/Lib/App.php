<?php

/**
 * NS App library
 *
 * Copyright 2016-2021 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

namespace Core\Lib;

use Core\Factory;

/**
 * Class App
 *
 * @package Core\Lib
 */
class App extends Factory
{
    public string $log_path    = '';
    public string $root_path   = '';
    public string $script_path = '';

    public string $api_path  = 'api';
    public string $client_ip = '0.0.0.0';
    public string $timezone  = 'Asia/Shanghai';

    public bool $is_cli     = false;
    public bool $is_tls     = false;
    public bool $core_debug = false;

    /**
     * App constructor.
     */
    public function __construct()
    {
        //Get server script path
        $this->script_path = strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);

        //Get absolute script path
        if (DIRECTORY_SEPARATOR !== $this->script_path[0] && ':' !== $this->script_path[1]) {
            $this->script_path = getcwd() . DIRECTORY_SEPARATOR . $this->script_path;
        }

        //Make parent path as root_path
        $root_path = dirname(dirname($this->script_path));

        //Copy root_path to App->root_path
        $this->root_path = &$root_path;

        //Register autoload (root_path based)
        spl_autoload_register(
            static function (string $class_name) use ($root_path): void
            {
                autoload($class_name, $root_path);
                unset($class_name, $root_path);
            }
        );

        //Create global log path (root_path/logs)
        if (!is_dir($this->log_path = $root_path . DIRECTORY_SEPARATOR . 'logs')) {
            mkdir($this->log_path, 0777, true);
            chmod($this->log_path, 0777);
        }

        //Set App environment
        $this->setEnv();

        unset($root_path);
    }

    /**
     * Set api pathname
     *
     * @param string $pathname
     *
     * @return $this
     */
    public function setApiPath(string $pathname): self
    {
        $this->api_path = &$pathname;

        unset($pathname);
        return $this;
    }

    /**
     * Set default timezone
     *
     * @param string $timezone
     *
     * @return $this
     */
    public function setTimezone(string $timezone): self
    {
        $this->timezone = &$timezone;

        unset($timezone);
        return $this;
    }

    /**
     * Set core_debug mode
     *
     * @param bool $core_debug_mode
     *
     * @return $this
     */
    public function setCoreDebug(bool $core_debug_mode): self
    {
        $this->core_debug = &$core_debug_mode;

        unset($core_debug_mode);
        return $this;
    }

    /**
     * Add autoload pathname (root_path related)
     *
     * @param string $pathname
     *
     * @return $this
     */
    public function addAutoload(string $pathname): self
    {
        $path = $this->root_path . DIRECTORY_SEPARATOR . $pathname;

        spl_autoload_register(
            static function (string $class_name) use ($path): void
            {
                autoload($class_name, $path);
                unset($class_name, $path);
            }
        );

        unset($pathname, $path);
        return $this;
    }

    /**
     * Add include pathname (root_path related)
     *
     * @param string $pathname
     *
     * @return $this
     */
    public function addIncPath(string $pathname): self
    {
        set_include_path($this->root_path . DIRECTORY_SEPARATOR . $pathname . PATH_SEPARATOR . get_include_path());

        unset($pathname);
        return $this;
    }

    /**
     * Parse conf file in JSON/INI
     *
     * @param string $file_path
     * @param bool   $ini_secs
     *
     * @return array
     * @throws \Exception
     */
    public function parseConf(string $file_path, bool $ini_secs): array
    {
        $file_data = file_get_contents($file_path);
        $file_ext  = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        try {
            switch ($file_ext) {
                case 'ini':
                    $config = parse_ini_string($file_data, $ini_secs, INI_SCANNER_TYPED);
                    break;

                case 'json':
                    $config = json_decode($file_data, true);
                    break;

                default:
                    $config = json_decode($file_data, true) ?? parse_ini_string($file_data, $ini_secs, INI_SCANNER_TYPED);
                    break;
            }

            if (!is_array($config)) {
                throw new \Exception('Configuration ERROR!');
            }
        } catch (\Throwable $throwable) {
            throw new \Exception('Failed to parse "' . $file_path . '": ' . $throwable->getMessage());
        }

        unset($file_path, $ini_secs, $file_data, $file_ext);
        return $config;
    }

    /**
     * Show debug message and continue
     *
     * @param \Throwable $throwable
     * @param bool       $show_on_cli
     */
    public function showDebug(\Throwable $throwable, bool $show_on_cli = false): void
    {
        if ($this->core_debug && ($show_on_cli ? true : !$this->is_cli)) {
            Error::new()->exceptionHandler($throwable, false);
        }

        unset($throwable, $show_on_cli);
    }

    /**
     * Set App environment
     */
    private function setEnv(): void
    {
        //Check running mode
        if ($this->is_cli = ('cli' === PHP_SAPI)) {
            $this->client_ip = 'Local CLI';
            return;
        }

        //Get TLS mode
        $this->is_tls = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        //Get IP rec
        $ip_rec = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? $_SERVER['HTTP_X_FORWARDED_FOR'] . ', ' . $_SERVER['REMOTE_ADDR']
            : $_SERVER['REMOTE_ADDR'];

        //Get IP list
        $ip_list = false !== strpos($ip_rec, ', ')
            ? explode(', ', $ip_rec)
            : [$ip_rec];

        //Get client IP
        foreach ($ip_list as $value) {
            if (is_string($addr = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6))) {
                $this->client_ip = &$addr;
                break;
            }
        }

        unset($ip_rec, $ip_list, $value, $addr);
    }
}