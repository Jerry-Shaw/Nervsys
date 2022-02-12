<?php

/**
 * App library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Lib;

use Nervsys\LC\Factory;

class App extends Factory
{
    public string $api_path  = 'api';
    public string $client_ip = '0.0.0.0';
    public string $timezone  = 'Asia/Shanghai';

    public bool $core_debug = false;

    /**
     * App constructor
     */
    public function __construct()
    {
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

    /**
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
}