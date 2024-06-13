<?php

/**
 * Cross-origin resource sharing library
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

class CORS extends Factory
{
    private array  $allowed_list    = [];
    private string $allowed_headers = 'X-Requested-With, Content-Type, Content-Length';

    /**
     * @param string $allowed_origin
     * @param string $allowed_headers
     *
     * @return $this
     */
    public function addRule(string $allowed_origin, string $allowed_headers = ''): self
    {
        $accept_headers = $this->allowed_headers;

        if ('' !== $allowed_headers) {
            $accept_headers .= ', ' . $allowed_headers;
        }

        $this->allowed_list[$allowed_origin] = &$accept_headers;

        unset($allowed_origin, $allowed_headers, $accept_headers);
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
            !headers_sent() && http_response_code(406);
            exit(0);
        }

        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            !headers_sent() && http_response_code(204);
            exit(0);
        }

        unset($is_tls, $allow_headers);
    }
}