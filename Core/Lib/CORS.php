<?php

/**
 * Cross-origin resource sharing library
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

class CORS extends Factory
{
    public array $origin_list = [];

    private string $allowed_headers = 'Content-Length, Content-Type, X-Requested-With';
    private string $exposed_headers = 'Cache-Control, Content-Language, Content-Type, Expires, Last-Modified, Pragma';

    /**
     * @param string $allowed_origin
     * @param string $allowed_headers
     * @param string $exposed_headers
     *
     * @return $this
     */
    public function addRule(string $allowed_origin, string $allowed_headers = '', string $exposed_headers = ''): self
    {
        if (!isset($this->origin_list[$allowed_origin])) {
            $this->origin_list[$allowed_origin]['allow']  = $this->allowed_headers;
            $this->origin_list[$allowed_origin]['expose'] = $this->exposed_headers;
        }

        if ('' !== $allowed_headers) {
            $this->origin_list[$allowed_origin]['allow'] .= ', ' . $allowed_headers;
        }

        if ('' !== $exposed_headers) {
            $this->origin_list[$allowed_origin]['expose'] .= ', ' . $exposed_headers;
        }

        unset($allowed_origin, $allowed_headers, $exposed_headers);
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

        if (!isset($this->origin_list[$_SERVER['HTTP_ORIGIN']]) && !isset($this->origin_list['*'])) {
            !headers_sent() && http_response_code(406);
            exit(0);
        }

        $cors_rules = $this->origin_list[$_SERVER['HTTP_ORIGIN']] ?? $this->origin_list['*'];

        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);

        header('Access-Control-Allow-Headers: ' . $cors_rules['allow']);
        header('Access-Control-Expose-Headers: ' . $cors_rules['expose']);

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            !headers_sent() && http_response_code(204);
            exit(0);
        }

        unset($is_tls, $cors_rules);
    }
}