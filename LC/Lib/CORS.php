<?php

/**
 * Cross-origin resource sharing library
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

namespace Nervsys\LC\Lib;

use Nervsys\LC\Factory;

class CORS extends Factory
{
    private array  $allowed_list  = [];
    private string $allow_headers = 'X-Requested-With, Content-Type, Content-Length';

    /**
     * @param string $allow_origin
     * @param string $allow_headers
     *
     * @return $this
     */
    public function addRecord(string $allow_origin, string $allow_headers = ''): self
    {
        $accept_headers = $this->allow_headers;

        if ('' !== $allow_headers) {
            $accept_headers .= ', ' . $allow_headers;
        }

        $this->allowed_list[$allow_origin] = &$accept_headers;

        unset($allow_origin, $allow_headers, $accept_headers);
        return $this;
    }

    /**
     * @param bool $is_tls
     *
     * @return void
     */
    public function checkPermission(bool $is_tls): void
    {
        if (!isset($_SERVER['HTTP_ORIGIN']) || $_SERVER['HTTP_ORIGIN'] === ($is_tls ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
            return;
        }

        if (is_null($allow_headers = $this->allowed_list[$_SERVER['HTTP_ORIGIN']] ?? $this->allowed_list['*'] ?? null)) {
            http_response_code(406);
            exit(0);
        }

        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            http_response_code(204);
            exit(0);
        }

        unset($is_tls, $allow_headers);
    }
}