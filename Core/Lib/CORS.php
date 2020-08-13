<?php

/**
 * NS CORS library
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
 * Class CORS
 *
 * @package Core\Lib
 */
class CORS extends Factory
{
    private array  $allowed_list = [];

    private string $allow_headers = 'X-Requested-With, Content-Type, Content-Length';

    /**
     * Add CORS record
     *
     * @param string $allow_origin
     * @param string $allow_headers
     *
     * @return $this
     */
    public function addRecord(string $allow_origin, string $allow_headers = ''): self
    {
        $allow_headers = '' !== $allow_headers
            ? $this->allow_headers . ', ' . $allow_headers
            : $this->allow_headers;

        $this->allowed_list[$allow_origin] = &$allow_headers;

        unset($allow_origin, $allow_headers);
        return $this;
    }

    /**
     * Check CORS permission
     *
     * @param \Core\Lib\App $app
     */
    public function checkPerm(App $app): void
    {
        //Server ENV passed
        if ($app->is_cli
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === ($app->is_tls ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
            return;
        }

        //Access NOT allowed
        if (is_null($allow_headers = $this->allowed_list[$_SERVER['HTTP_ORIGIN']] ?? $this->allowed_list['*'] ?? null)) {
            http_response_code(406);
            exit();
        }

        //Response allowed headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        //Exit on OPTION request
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            http_response_code(200);
            exit();
        }

        unset($allow_headers);
    }
}