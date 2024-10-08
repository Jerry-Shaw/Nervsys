<?php

/**
 * App library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2024 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core\Lib;

use Nervsys\Core\Factory;

class App extends Factory
{
    public string $log_path    = '';
    public string $root_path   = '';
    public string $script_path = '';

    public string $api_dir    = 'api';
    public string $client_ip  = '0.0.0.0';
    public string $user_lang  = 'Unknown';
    public string $user_agent = 'Unknown';
    public string $timezone   = 'Asia/Shanghai';

    public bool $is_cli = false;
    public bool $is_tls = false;

    public bool $debug_mode = false;

    /**
     * @param string $root_path
     *
     * @return $this
     */
    public function setRoot(string $root_path): self
    {
        $this->root_path = &$root_path;

        unset($root_path);
        return $this;
    }

    /**
     * @param string $api_dir
     *
     * @return $this
     */
    public function setApiDir(string $api_dir): self
    {
        $this->api_dir = &$api_dir;

        unset($api_dir);
        return $this;
    }

    /**
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale(string $locale): self
    {
        setlocale(LC_ALL, $locale);
        putenv('LC_ALL=' . $locale);

        unset($locale);
        return $this;
    }

    /**
     * @param bool $core_debug
     *
     * @return $this
     */
    public function setDebugMode(bool $core_debug): self
    {
        $this->debug_mode = &$core_debug;

        unset($core_debug);
        return $this;
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function initIOEnv(): self
    {
        $this->script_path = realpath(strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR));

        if (false === $this->script_path) {
            $this->script_path = realpath(getcwd() . DIRECTORY_SEPARATOR . $_SERVER['SCRIPT_FILENAME']);

            if (false === $this->script_path) {
                throw new \Exception('Script path NOT detected!', E_USER_ERROR);
            }
        }

        if ('' === $this->root_path) {
            for ($i = 1; $i <= 2; ++$i) {
                $this->root_path = dirname($this->script_path, $i);

                if (is_dir($this->root_path . DIRECTORY_SEPARATOR . $this->api_dir)) {
                    break;
                }
            }
        }

        $this->log_path = $this->root_path . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($this->log_path)) {
            try {
                mkdir($this->log_path, 0777, true);
                chmod($this->log_path, 0777);
            } catch (\Throwable) {
                //Dir already exists
            }
        }

        return $this;
    }

    /**
     * @return void
     */
    public function initAppEnv(): void
    {
        if ('cli' === PHP_SAPI) {
            $this->is_cli     = true;
            $this->client_ip  = '127.0.0.1';
            $this->user_lang  = 'System Lang';
            $this->user_agent = 'CLI Command';
            return;
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $this->user_agent = &$_SERVER['HTTP_USER_AGENT'];
        }

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $this->user_lang = &$_SERVER['HTTP_ACCEPT_LANGUAGE'];
        }

        $this->is_tls = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        $ip_rec = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? $_SERVER['HTTP_X_FORWARDED_FOR'] . ', ' . $_SERVER['REMOTE_ADDR']
            : $_SERVER['REMOTE_ADDR'];

        $ip_list = str_contains($ip_rec, ', ')
            ? explode(', ', $ip_rec)
            : [$ip_rec];

        foreach ($ip_list as $value) {
            if (is_string($addr = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6))) {
                $this->client_ip = &$addr;
                break;
            }
        }

        unset($ip_rec, $ip_list, $value, $addr);
    }
}