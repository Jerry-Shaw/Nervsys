<?php

/**
 * App library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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
    public string $api_path;
    public string $log_path;
    public string $root_path;
    public string $script_path;

    public string $client_ip = '0.0.0.0';
    public string $timezone  = 'Asia/Shanghai';

    public bool $is_cli = false;
    public bool $is_tls = false;

    public bool $core_debug = false;

    /**
     * App constructor
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $this->script_path = realpath(strtr($_SERVER['SCRIPT_FILENAME'], '\\/', DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR));

        if (false === $this->script_path) {
            $this->script_path = realpath(getcwd() . DIRECTORY_SEPARATOR . $this->script_path);

            if (false === $this->script_path) {
                throw new \Exception('Script path NOT detected!', E_USER_ERROR);
            }
        }

        $this->root_path = dirname($this->script_path, 2);
        $this->log_path  = $this->root_path . DIRECTORY_SEPARATOR . 'logs';
        $this->api_path  = 'api';

        if (!is_dir($this->log_path)) {
            try {
                mkdir($this->log_path, 0777, true);
                chmod($this->log_path, 0777);
            } catch (\Throwable) {
                //Dir already exists
            }
        }

        $this->setAppEnv();
    }

    /**
     * @return void
     */
    private function setAppEnv(): void
    {
        $this->is_cli = 'cli' === PHP_SAPI;

        if ($this->is_cli) {
            return;
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