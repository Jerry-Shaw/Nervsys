<?php

/**
 * HTTP Request Extension
 *
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

namespace Ext;

use Core\Factory;
use Core\Lib\IOUnit;

/**
 * Class libHttp
 *
 * @package Ext
 */
class libHttp extends Factory
{
    //Pre-defined content types
    const CONTENT_TYPE_XML         = 'application/xml;charset=utf-8';
    const CONTENT_TYPE_JSON        = 'application/json;charset=utf-8';
    const CONTENT_TYPE_FORM_DATA   = 'multipart/form-data';
    const CONTENT_TYPE_URL_ENCODED = 'application/x-www-form-urlencoded;charset=utf-8';

    //cURL default data container
    const CURL_DEFAULT = [
        'http_ver'          => 'HTTP/2',
        'http_method'       => 'GET',
        'http_connection'   => 'keep-alive',
        'http_content_type' => self::CONTENT_TYPE_URL_ENCODED,
        'accept_charset'    => 'UTF-8,*;q=0',
        'accept_encoding'   => 'gzip,deflate,identity,*;q=0',
        'accept_language'   => 'en-US,en,zh-CN,zh,*;q=0',
        'accept_type'       => 'application/json;q=0.9,application/xml;q=0.8,text/plain;q=0.7,text/html;q=0.6,*/*;q=0.5',
        'user_agent'        => 'Mozilla/5.0 (Compatible; NervSys/' . NS_VER . ')',
        'with_body'         => true
    ];

    //Raw data
    public string $raw_header = '';
    public string $raw_cookie = '';

    //parsed data
    public string $http_body   = '';
    public array  $http_header = [];
    public array  $http_cookie = [];

    //cURL info data
    public array  $curl_info  = [];
    public string $curl_error = '';

    //Runtime data container
    public array $runtime_data = [];

    /**
     * Add request data
     *
     * @param array $data
     *
     * @return $this
     */
    public function addData(array $data): self
    {
        $this->runtime_data['data'] ??= [];
        $this->runtime_data['data'] += $data;

        unset($data);
        return $this;
    }

    /**
     * Add request header
     *
     * @param array $header
     *
     * @return $this
     */
    public function addHeader(array $header): self
    {
        $this->runtime_data['header'] ??= [];
        $this->runtime_data['header'] += $header;

        unset($header);
        return $this;
    }

    /**
     * Add upload file
     *
     * @param string $key
     * @param string $filename
     * @param string $mime_type
     * @param string $posted_filename
     *
     * @return $this
     */
    public function addFile(string $key, string $filename, string $mime_type = '', string $posted_filename = ''): self
    {
        if (is_file($filename)) {
            $this->runtime_data['file'][$key] = curl_file_create($filename, $mime_type, $posted_filename);
        }

        unset($key, $filename, $mime_type, $posted_filename);
        return $this;
    }

    /**
     * Add cookie data
     *
     * @param array $cookie
     *
     * @return $this
     */
    public function addCookie(array $cookie): self
    {
        $this->runtime_data['cookie'] ??= '';

        foreach ($cookie as $key => $val) {
            if ('' !== $this->runtime_data['cookie']) {
                $this->runtime_data['cookie'] .= '; ';
            }

            $this->runtime_data['cookie'] .= $key . '=' . $val;
        }

        unset($cookie, $key, $val);
        return $this;
    }

    /**
     * Add custom cURL options
     *
     * @param array $curl_opt
     *
     * @return $this
     */
    public function addOptions(array $curl_opt): self
    {
        $this->runtime_data['options'] ??= [];
        $this->runtime_data['options'] += $curl_opt;

        unset($curl_opt);
        return $this;
    }

    /**
     * Set HTTP method
     *
     * @param string $http_method
     *
     * @return $this
     */
    public function setHttpMethod(string $http_method): self
    {
        $this->runtime_data['http_method'] = &$http_method;

        unset($http_method);
        return $this;
    }

    /**
     * Set content type
     *
     * @param string $content_type
     *
     * @return $this
     */
    public function setContentType(string $content_type): self
    {
        $this->runtime_data['http_content_type'] = &$content_type;

        unset($content_type);
        return $this;
    }

    /**
     * Set referer URL
     *
     * @param string $referer
     *
     * @return $this
     */
    public function setReferer(string $referer): self
    {
        $this->runtime_data['referer'] = &$referer;

        unset($referer);
        return $this;
    }

    /**
     * Set User-Agent string
     *
     * @param string $user_agent
     *
     * @return $this
     */
    public function setUserAgent(string $user_agent): self
    {
        $this->runtime_data['user_agent'] = &$user_agent;

        unset($user_agent);
        return $this;
    }

    /**
     * Set max follow depths
     *
     * @param int $max_follow
     *
     * @return $this
     */
    public function setMaxFollow(int $max_follow): self
    {
        $this->runtime_data['max_follow'] = &$max_follow;

        unset($max_follow);
        return $this;
    }

    /**
     * Set HTTP accept types
     *
     * @param string $accept_type
     *
     * @return $this
     */
    public function setAcceptType(string $accept_type): self
    {
        $this->runtime_data['accept_type'] = &$accept_type;

        unset($accept_type);
        return $this;
    }

    /**
     * Set ETag value
     *
     * @param string $etag
     *
     * @return $this
     */
    public function setETag(string $etag): self
    {
        $this->runtime_data['etag'] = &$etag;

        unset($etag);
        return $this;
    }

    /**
     * Set modified since value
     *
     * @param string $last_modified
     *
     * @return $this
     */
    public function setLastModified(string $last_modified): self
    {
        $this->runtime_data['last_modified'] = &$last_modified;

        unset($last_modified);
        return $this;
    }

    /**
     * Set proxy
     *
     * @param string $proxy
     * @param string $proxy_passwd
     *
     * @return $this
     */
    public function setProxy(string $proxy, string $proxy_passwd): self
    {
        $this->runtime_data['proxy']        = &$proxy;
        $this->runtime_data['proxy_passwd'] = &$proxy_passwd;

        unset($proxy, $proxy_passwd);
        return $this;
    }

    /**
     * Fetch with body content
     *
     * @param bool $with_body
     *
     * @return $this
     */
    public function withBody(bool $with_body): self
    {
        $this->runtime_data['with_body'] = &$with_body;

        unset($with_body);
        return $this;
    }

    /**
     * Fetch response body from URL
     *
     * @param string $url
     *
     * @return string
     */
    public function fetch(string $url): string
    {
        //Get URL units
        $url_unit = $this->buildUrlUnit($url);

        //Build runtime data
        $runtime_data = $this->buildRuntimeData($url_unit);

        //Build cURL options
        $curl_options = $this->buildCurlOptions($runtime_data);

        //Initial cURL
        $curl_handle = curl_init($url);

        //Set cURL options
        curl_setopt_array($curl_handle, $curl_options);

        //Get raw response
        $response = curl_exec($curl_handle);

        //Get cURL info
        $this->curl_info  = curl_getinfo($curl_handle);
        $this->curl_error = curl_error($curl_handle);

        //Close cURL handle
        curl_close($curl_handle);

        //Parse HTTP response
        if (false !== $response) {
            $this->parseHttpResponse($response);
        }

        unset($url, $url_unit, $runtime_data, $curl_options, $curl_handle, $response);
        return $this->http_body;
    }

    /**
     * Get HTTP response code
     *
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->curl_info['http_code'];
    }

    /**
     * Get HTTP download size
     *
     * @return float
     */
    public function getDownSize(): float
    {
        return $this->curl_info['size_download'];
    }

    /**
     * Get HTTP body size
     *
     * @return float
     */
    public function getBodySize(): float
    {
        return $this->curl_info['download_content_length'];
    }

    /**
     * Get HTTP total transfer time (second)
     *
     * @return float
     */
    public function getTotalTime(): float
    {
        return $this->curl_info['total_time'];
    }

    /**
     * Get HTTP body data
     *
     * @return string
     */
    public function getHttpBody(): string
    {
        return $this->http_body;
    }

    /**
     * Get HTTP cURL error
     *
     * @return string
     */
    public function getHttpError(): string
    {
        return $this->curl_error;
    }

    /**
     * Get parsed HTTP Header data
     *
     * @return array
     */
    public function getHttpHeader(): array
    {
        return $this->http_header;
    }

    /**
     * Get parsed HTTP Cookie data
     *
     * @return array
     */
    public function getHttpCookie(): array
    {
        return $this->http_cookie;
    }

    /**
     * Build URL units
     *
     * @param string $url
     *
     * @return array
     */
    private function buildUrlUnit(string $url): array
    {
        //Parse URL
        $url_unit = parse_url($url);

        //Check main components
        if (false === $url_unit || !isset($url_unit['scheme']) || !isset($url_unit['host'])) {
            return [];
        }

        //Prepare URL unit
        if (!isset($url_unit['path'])) {
            $url_unit['path'] = '/';
        }

        //Build query string
        $url_unit['query'] = isset($url_unit['query']) ? '?' . $url_unit['query'] : '';

        if (!isset($url_unit['port'])) {
            $url_unit['port'] = 'https' === $url_unit['scheme'] ? 443 : 80;
        }

        unset($url);
        return $url_unit;
    }

    /**
     * Build runtime data
     *
     * @param array $url_unit
     *
     * @return array
     */
    private function buildRuntimeData(array $url_unit): array
    {
        //Merge custom options
        if (isset($this->runtime_data['options'])) {
            $this->runtime_data = $this->runtime_data['options'] + $this->runtime_data;
            unset($this->runtime_data['options']);
        }

        //Merge upload file data
        if (isset($this->runtime_data['file'])) {
            $this->runtime_data['data'] ??= [];
            $this->runtime_data['data'] += $this->runtime_data['file'];

            $this->runtime_data['http_content_type'] = self::CONTENT_TYPE_FORM_DATA;
        }

        //Merge default
        $this->runtime_data += self::CURL_DEFAULT;

        //Merge URL data
        $this->runtime_data['url_unit'] = &$url_unit;

        //Uppercase HTTP method
        $this->runtime_data['http_method'] = strtoupper($this->runtime_data['http_method']);

        //Merge with header data
        $runtime_data = $this->mergeHttpHeader($url_unit, $this->runtime_data);

        //Reset runtime data
        $this->runtime_data = [];

        unset($url_unit);
        return $runtime_data;
    }

    /**
     * Build cURL options
     *
     * @param array $runtime_data
     *
     * @return array
     */
    private function buildCurlOptions(array $runtime_data): array
    {
        $curl_opt = [];

        if (isset($runtime_data['cookie'])) {
            $curl_opt[CURLOPT_COOKIE] = &$runtime_data['cookie'];
        }

        if (isset($runtime_data['referer'])) {
            $curl_opt[CURLOPT_REFERER] = &$runtime_data['referer'];
        }

        if (isset($runtime_data['max_follow']) && 0 < $runtime_data['max_follow']) {
            $curl_opt[CURLOPT_FOLLOWLOCATION] = true;
            $curl_opt[CURLOPT_MAXREDIRS]      = &$runtime_data['max_follow'];
        }

        if (isset($runtime_data['proxy'])) {
            $curl_opt[CURLOPT_PROXY] = &$runtime_data['proxy'];

            if (isset($runtime_data['proxy_passwd'])) {
                $curl_opt[CURLOPT_PROXYUSERPWD] = &$runtime_data['proxy_passwd'];
            }
        }

        if (isset($runtime_data['data'])) {
            if ('GET' === $runtime_data['http_method']) {
                $runtime_data['http_method'] = 'POST';
            }

            switch ($runtime_data['http_content_type']) {
                case self::CONTENT_TYPE_JSON:
                    $curl_opt[CURLOPT_POSTFIELDS] = json_encode($runtime_data['data'], JSON_FORMAT);
                    break;

                case self::CONTENT_TYPE_XML:
                    $curl_opt[CURLOPT_POSTFIELDS] = IOUnit::new()->toXml($runtime_data['data']);
                    break;

                case self::CONTENT_TYPE_URL_ENCODED:
                    $curl_opt[CURLOPT_POSTFIELDS] = http_build_query($runtime_data['data']);
                    break;

                default:
                    $curl_opt[CURLOPT_POSTFIELDS] = &$runtime_data['data'];
                    break;
            }
        }

        $curl_opt[CURLOPT_PORT]           = &$runtime_data['url_unit']['port'];
        $curl_opt[CURLOPT_TIMEOUT]        = $runtime_data['timeout'] ?? 60;
        $curl_opt[CURLOPT_NOSIGNAL]       = true;
        $curl_opt[CURLOPT_AUTOREFERER]    = true;
        $curl_opt[CURLOPT_COOKIESESSION]  = true;
        $curl_opt[CURLOPT_RETURNTRANSFER] = true;
        $curl_opt[CURLOPT_SSL_VERIFYHOST] = $runtime_data['ssl_verifyhost'] ?? 2;
        $curl_opt[CURLOPT_SSL_VERIFYPEER] = $runtime_data['ssl_verifypeer'] ?? false;
        $curl_opt[CURLOPT_HTTPHEADER]     = &$runtime_data['header'];
        $curl_opt[CURLOPT_ENCODING]       = &$runtime_data['accept_encoding'];
        $curl_opt[CURLOPT_USERAGENT]      = &$runtime_data['user_agent'];
        $curl_opt[CURLOPT_CUSTOMREQUEST]  = &$runtime_data['http_method'];
        $curl_opt[CURLOPT_NOBODY]         = !$runtime_data['with_body'];
        $curl_opt[CURLOPT_HEADER]         = true;

        unset($runtime_data);
        return $curl_opt;
    }

    /**
     * Merge runtime data with HTTP header
     *
     * @param array $url_unit
     * @param array $runtime_data
     *
     * @return array
     */
    private function mergeHttpHeader(array $url_unit, array $runtime_data): array
    {
        $header_unit = ['Host' => $url_unit['host'] . ':' . $url_unit['port']];

        if (isset($runtime_data['header'])) {
            $header_unit += $runtime_data['header'];
        }

        if (isset($runtime_data['cookie'])) {
            $header_unit['Cookie'] = &$runtime_data['cookie'];
        }

        if (isset($runtime_data['etag'])) {
            $header_unit['If-None-Match'] = &$runtime_data['etag'];
        }

        if (isset($runtime_data['last_modified'])) {
            $header_unit['If-Modified-Since'] = &$runtime_data['last_modified'];
        }

        $header_unit += [
            'Accept'          => &$runtime_data['accept_type'],
            'Accept-Charset'  => &$runtime_data['accept_charset'],
            'Accept-Encoding' => &$runtime_data['accept_encoding'],
            'Accept-Language' => &$runtime_data['accept_language'],
            'Content-Type'    => &$runtime_data['http_content_type'],
            'User-Agent'      => &$runtime_data['user_agent'],
            'Connection'      => &$runtime_data['http_connection']
        ];

        $runtime_data['header'] = [$runtime_data['http_method'] . ' ' . $url_unit['path'] . $url_unit['query'] . ' ' . $runtime_data['http_ver']];

        foreach ($header_unit as $key => $val) {
            $runtime_data['header'][] = ($key . ': ' . $val);
        }

        unset($url_unit, $header_unit, $key, $val);
        return $runtime_data;
    }

    /**
     * Parse HTTP response into parts
     *
     * @param string $response
     */
    private function parseHttpResponse(string $response): void
    {
        [$header_data, $body_data] = explode("\r\n\r\n", $response, 2);

        $this->raw_header = &$header_data;
        $this->http_body  = &$body_data;

        //Reset appendable variables
        $this->raw_cookie  = '';
        $this->http_header = [];
        $this->http_cookie = [];

        $data_list = explode("\r\n", $header_data);

        //Parse Header
        foreach ($data_list as $value) {
            if (false === ($pos = strpos($value, ':'))) {
                continue;
            }

            $key = strtolower(substr($value, 0, $pos));
            $val = substr($value, $pos + 2);

            $this->http_header[$key] = $val;

            if ('set-cookie' === $key) {
                if ('' !== $this->raw_cookie) {
                    $this->raw_cookie .= '; ';
                }

                $this->raw_cookie .= rtrim($val, ';');
            }
        }

        $data_list = explode('; ', $this->raw_cookie);

        //Parse Cookie
        foreach ($data_list as $value) {
            if (false === ($pos = strpos($value, '='))) {
                continue;
            }

            $key = substr($value, 0, $pos);
            $val = substr($value, $pos + 1);

            $this->http_cookie[$key] = $val;
        }

        unset($response, $header_data, $body_data, $data_list, $value, $pos, $key, $val);
    }
}