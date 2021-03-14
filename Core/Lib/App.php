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
    public string $entry_path  = '';
    public string $parent_path = '';
    public string $script_path = '';

    public string $api_path  = 'api';
    public string $client_ip = '0.0.0.0';
    public string $timezone  = 'Asia/Shanghai';

    public bool $is_cli     = false;
    public bool $is_tls     = false;
    public bool $is_ready   = false;
    public bool $core_debug = false;

    public array $include_list  = [];
    public array $autoload_list = [];

    /**
     * App constructor.
     */
    public function __construct()
    {
        //Get script path
        $this->script_path = strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);

        //Correct script path
        if (DIRECTORY_SEPARATOR !== $this->script_path[0] && ':' !== $this->script_path[1]) {
            $this->script_path = getcwd() . DIRECTORY_SEPARATOR . $this->script_path;
        }

        //Get entry path & parent path
        $this->entry_path  = dirname($this->script_path);
        $this->parent_path = dirname($this->entry_path);

        //Autoload parent path and entry path
        foreach ([$this->parent_path, $this->entry_path] as $path) {
            spl_autoload_register(
                static function (string $class_name) use ($path): void
                {
                    autoload($class_name, $path);
                    unset($class_name, $path);
                }
            );
        }

        //Check CLI/CGI
        if ($this->is_cli = ('cli' === PHP_SAPI)) {
            $this->client_ip = 'Local CLI';
            return;
        }

        //Get TLS mode
        $this->is_tls = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        //Build full IP records
        $ip_rec = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? $_SERVER['HTTP_X_FORWARDED_FOR'] . ', ' . $_SERVER['REMOTE_ADDR']
            : $_SERVER['REMOTE_ADDR'];

        //Build IP list
        $ip_list = false !== strpos($ip_rec, ', ')
            ? explode(', ', $ip_rec)
            : [$ip_rec];

        //Get valid client IP
        foreach ($ip_list as $value) {
            if (is_string($addr = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6))) {
                $this->client_ip = &$addr;
                break;
            }
        }

        unset($path, $ip_rec, $ip_list, $value, $addr);
    }

    /**
     * Set App environment
     *
     * @return $this
     */
    public function setEnv(): self
    {
        //Looking for api directory to get correct root path
        $root_path = is_dir($this->parent_path . DIRECTORY_SEPARATOR . $this->api_path)
            ? $this->parent_path
            : $this->entry_path;

        //Copy root_path to $this->root_path
        $this->root_path = &$root_path;

        //Set autoload path in list
        if (!empty($this->autoload_list)) {
            foreach ($this->autoload_list as $pathname) {
                $path = $root_path . DIRECTORY_SEPARATOR . $pathname;

                spl_autoload_register(
                    static function (string $class_name) use ($path): void
                    {
                        autoload($class_name, $path);
                        unset($class_name, $path);
                    }
                );
            }
        }

        //Set include path in list
        if (!empty($this->include_list)) {
            $path = '';

            foreach ($this->include_list as $pathname) {
                $path .= $root_path . DIRECTORY_SEPARATOR . $pathname . PATH_SEPARATOR;
            }

            set_include_path(substr($path, 0, -1));
        }

        //Create global log path
        $this->createLogPath($root_path);

        //All is ready
        $this->is_ready = true;

        unset($root_path, $pathname, $path);
        return $this;
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
        if (!$this->is_ready) {
            $this->autoload_list[] = $pathname;
        } else {
            $path = $this->root_path . DIRECTORY_SEPARATOR . $pathname;

            spl_autoload_register(
                static function (string $class_name) use ($path): void
                {
                    autoload($class_name, $path);
                    unset($class_name, $path);
                }
            );

            unset($path);
        }

        unset($pathname);
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
        !$this->is_ready
            ? $this->include_list[] = $pathname
            : set_include_path($this->root_path . DIRECTORY_SEPARATOR . $pathname . PATH_SEPARATOR . get_include_path());

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
     * Create log path
     *
     * @param string $log_path
     *
     * @return $this
     */
    private function createLogPath(string $log_path): self
    {
        if (!is_dir($this->log_path = $log_path . DIRECTORY_SEPARATOR . 'logs')) {
            mkdir($this->log_path, 0777, true);
            chmod($this->log_path, 0777);
        }

        unset($log_path);
        return $this;
    }
}