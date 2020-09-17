<?php

/**
 * NS App library
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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
    public Error  $error;

    public string $log_path    = '';
    public string $root_path   = '';
    public string $entry_path  = '';
    public string $script_path = '';

    public string $api_path = 'api';
    public string $app_path = 'app';
    public string $inc_path = 'inc';

    public string $client_ip = '0.0.0.0';
    public string $timezone  = 'Asia/Shanghai';

    public bool $is_cli     = false;
    public bool $is_tls     = false;
    public bool $auto_call  = false;
    public bool $core_debug = false;

    /**
     * App constructor.
     */
    public function __construct()
    {
        //Init Error
        $this->error = Error::new();

        //Get script file path
        $this->script_path = strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR);

        //Get absolute path of entry script
        if (DIRECTORY_SEPARATOR !== $this->script_path[0] && ':' !== $this->script_path[1]) {
            $this->script_path = getcwd() . DIRECTORY_SEPARATOR . $this->script_path;
        }

        //Get absolute entry path
        $this->entry_path = dirname($this->script_path);

        //Skip in CLI mode
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

        unset($ip_rec, $ip_list, $value, $addr);
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
     * Set app pathname and root path
     *
     * @param string $pathname
     *
     * @return $this
     */
    public function setAppPath(string $pathname): self
    {
        $this->app_path = &$pathname;

        unset($pathname);
        return $this;
    }

    /**
     * Set inc pathname
     *
     * @param string $pathname
     *
     * @return $this
     */
    public function setIncPath(string $pathname): self
    {
        $this->inc_path = &$pathname;

        set_include_path(
            implode(
                PATH_SEPARATOR,
                [
                    $this->entry_path . DIRECTORY_SEPARATOR . $pathname,
                    dirname($this->entry_path) . DIRECTORY_SEPARATOR . $pathname
                ]
            )
        );

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
     * Set auto_call mode
     *
     * @param bool $auto_call_mode
     *
     * @return $this
     */
    public function setAutoCall(bool $auto_call_mode): self
    {
        $this->auto_call = &$auto_call_mode;

        unset($auto_call_mode);
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
     * Show debug message and continue
     *
     * @param \Throwable $throwable
     * @param bool       $show_on_cli
     */
    public function showDebug(\Throwable $throwable, bool $show_on_cli = false): void
    {
        if ($this->core_debug && ($show_on_cli ? true : !$this->is_cli)) {
            $this->error->exceptionHandler($throwable, false);
        }

        unset($throwable, $show_on_cli);
    }
}