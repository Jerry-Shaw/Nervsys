<?php

/**
 * HTTP Request Extension
 *
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;
use Nervsys\Core\Lib\IOData;

class libHttp extends Factory
{
    // Pre-defined content types
    const CONTENT_TYPE_XML         = 'application/xml; charset=utf-8';
    const CONTENT_TYPE_JSON        = 'application/json; charset=utf-8';
    const CONTENT_TYPE_FORM_DATA   = 'multipart/form-data';
    const CONTENT_TYPE_URL_ENCODED = 'application/x-www-form-urlencoded; charset=UTF-8';

    // Default configuration (base configuration)
    const CURL_DEFAULT = [
        'http_ver'          => 'HTTP/2',
        'http_method'       => 'GET',
        'http_connection'   => 'keep-alive',
        'http_content_type' => self::CONTENT_TYPE_URL_ENCODED,
        'ssl_verifyhost'    => 2,
        'ssl_verifypeer'    => true,
        'accept_charset'    => 'UTF-8,*;q=0',
        'accept_encoding'   => 'gzip, deflate, identity,*;q=0',
        'accept_language'   => 'zh-CN,zh;q=0.9,en-US;q=0.8,en;q=0.7',
        'accept_type'       => 'application/json;q=0.9,application/xml;q=0.8,text/plain;q=0.7,text/html;q=0.6,*/*;q=0.5',
        'user_agent'        => 'Mozilla/5.0 (Nervsys ' . NS_VER . '; ' . NS_NAME . ')',
        'with_body'         => true,
        'timeout'           => 60
    ];

    // Persistent user configuration (string keys that are not direct cURL options)
    protected array $user_config = [];

    // Persistent cURL options (int keys)
    protected array $curl_options = [];

    // Temporary data (cleared after each request)
    protected array $requestData = [];
    protected array $requestFile = [];

    // Raw and parsed data (results)
    public string $raw_header  = '';
    public string $raw_cookie  = '';
    public string $http_body   = '';
    public array  $http_header = [];
    public array  $http_cookie = [];

    // cURL info & error
    public array  $curl_info  = [];
    public string $curl_error = '';

    /**
     * Constructor – set User-Agent and timeout as defaults
     *
     * @param string $user_agent
     * @param int    $timeout
     */
    public function __construct(string $user_agent = '', int $timeout = 0)
    {
        if ('' !== $user_agent) {
            $this->curl_options[CURLOPT_USERAGENT] = $user_agent;
        }

        if (0 < $timeout) {
            $this->curl_options[CURLOPT_TIMEOUT] = $timeout;
        }

        unset($user_agent, $timeout);
    }

    /**
     * Add request data (temporary)
     */
    public function addData(array $data): static
    {
        $this->requestData = array_merge($this->requestData, $data);

        unset($data);
        return $this;
    }

    /**
     * Add upload file (temporary)
     */
    public function addFile(string $key, string $filename, string $mime_type = '', string $posted_filename = ''): static
    {
        if (is_file($filename)) {
            $this->requestFile[$key] = curl_file_create($filename, $mime_type, $posted_filename);
        }

        unset($key, $filename, $mime_type, $posted_filename);
        return $this;
    }

    /**
     * Add request header
     */
    public function addHeader(array $header): static
    {
        $this->user_config['header'] = array_merge($this->user_config['header'] ?? [], $header);

        unset($header);
        return $this;
    }

    /**
     * Add cookie data (appends to existing cookie string)
     */
    public function addCookie(array $cookie): static
    {
        $this->user_config['cookie'] ??= '';

        foreach ($cookie as $key => $val) {
            if ('' !== $this->user_config['cookie']) {
                $this->user_config['cookie'] .= '; ';
            }

            $this->user_config['cookie'] .= $key . '=' . $val;
        }

        unset($cookie, $key, $val);
        return $this;
    }

    /**
     * Add custom cURL options (only integer keys allowed)
     * String keys are ignored (use dedicated methods like setUserAgent, setTimeout, etc.)
     *
     * @param array $curl_opt_pair
     *
     * @return $this
     */
    public function addOptions(array $curl_opt_pair): static
    {
        foreach ($curl_opt_pair as $key => $value) {
            if (is_int($key)) {
                $this->curl_options[$key] = $value;
            }
        }

        unset($curl_opt_pair, $key, $value);
        return $this;
    }

    /**
     * Remove cURL options (int keys only)
     *
     * @param int ...$curl_opts
     *
     * @return $this
     */
    public function removeOptions(int ...$curl_opts): static
    {
        foreach ($curl_opts as $opt) {
            unset($this->curl_options[$opt]);
        }

        unset($curl_opts, $opt);
        return $this;
    }

    /**
     * Reset all persistent configuration (both user_config and curl_options)
     */
    public function resetOptions(): static
    {
        $this->user_config  = [];
        $this->curl_options = [];

        return $this;
    }

    /**
     * Set a stream callback (cURL option)
     */
    public function setStreamCallback(callable $callback): static
    {
        $this->curl_options[CURLOPT_WRITEFUNCTION] = $callback;

        unset($callback);
        return $this;
    }

    /**
     * Remove current stream callback
     */
    public function removeStreamCallback(): static
    {
        unset($this->curl_options[CURLOPT_WRITEFUNCTION]);
        return $this;
    }

    /**
     * Set cookie string directly (overwrites any previously set cookie)
     */
    public function setCookie(string $cookie): static
    {
        $this->curl_options[CURLOPT_COOKIE] = $cookie;

        unset($cookie);
        return $this;
    }

    /**
     * Set HTTP method (CUSTOMREQUEST and also update user_config for request line)
     */
    public function setHttpMethod(string $http_method): static
    {
        $this->curl_options[CURLOPT_CUSTOMREQUEST] = $http_method;
        $this->user_config['http_method']          = $http_method;

        unset($http_method);
        return $this;
    }

    /**
     * Set content type (used in header and body encoding)
     */
    public function setContentType(string $content_type): static
    {
        $this->user_config['http_content_type'] = $content_type;

        unset($content_type);
        return $this;
    }

    /**
     * Set Accept-Encoding (direct cURL option)
     */
    public function setAcceptEncoding(string $accept_encoding): static
    {
        $this->curl_options[CURLOPT_ENCODING] = $accept_encoding;

        unset($accept_encoding);
        return $this;
    }

    /**
     * Set timeout (direct cURL option)
     */
    public function setTimeout(int $timeout): static
    {
        $this->curl_options[CURLOPT_TIMEOUT] = $timeout;

        unset($timeout);
        return $this;
    }

    /**
     * Set referer (direct cURL option)
     */
    public function setReferer(string $referer): static
    {
        $this->curl_options[CURLOPT_REFERER] = $referer;

        unset($referer);
        return $this;
    }

    /**
     * Set User-Agent (direct cURL option)
     */
    public function setUserAgent(string $user_agent): static
    {
        $this->curl_options[CURLOPT_USERAGENT] = $user_agent;

        unset($user_agent);
        return $this;
    }

    /**
     * Set max follow redirects (requires also enabling FOLLOWLOCATION)
     * Stored in user_config because it needs special handling.
     */
    public function setMaxFollow(int $max_follow): static
    {
        $this->user_config['max_follow'] = $max_follow;

        unset($max_follow);
        return $this;
    }

    /**
     * Set Accept header (used in HTTP headers)
     */
    public function setAcceptType(string $accept_type): static
    {
        $this->user_config['accept_type'] = $accept_type;

        unset($accept_type);
        return $this;
    }

    /**
     * Set ETag (used in If-None-Match header)
     */
    public function setETag(string $etag): static
    {
        $this->user_config['etag'] = $etag;

        unset($etag);
        return $this;
    }

    /**
     * Set Last-Modified (used in If-Modified-Since header)
     */
    public function setLastModified(string $last_modified): static
    {
        $this->user_config['last_modified'] = $last_modified;

        unset($last_modified);
        return $this;
    }

    /**
     * Set SSL verify host (direct cURL option)
     */
    public function setSslVerifyHost(int $ssl_verifyhost): static
    {
        $this->curl_options[CURLOPT_SSL_VERIFYHOST] = $ssl_verifyhost;

        unset($ssl_verifyhost);
        return $this;
    }

    /**
     * Set SSL verify peer (direct cURL option)
     */
    public function setSslVerifyPeer(bool $ssl_verifypeer): static
    {
        $this->curl_options[CURLOPT_SSL_VERIFYPEER] = $ssl_verifypeer;

        unset($ssl_verifypeer);
        return $this;
    }

    /**
     * Set proxy (direct cURL option for proxy and proxy userpass)
     */
    public function setProxy(string $proxy, string $proxy_passwd): static
    {
        $this->curl_options[CURLOPT_PROXY]        = $proxy;
        $this->curl_options[CURLOPT_PROXYUSERPWD] = $proxy_passwd;

        unset($proxy, $proxy_passwd);
        return $this;
    }

    /**
     * Enable/Disable fetching body (direct cURL option, CURLOPT_NOBODY is opposite)
     */
    public function withBody(bool $with_body): static
    {
        $this->curl_options[CURLOPT_NOBODY] = !$with_body;

        unset($with_body);
        return $this;
    }

    /**
     * Fetch response body from URL
     *
     * @param string $url
     * @param string $to_file
     * @param bool   $reset_options
     *
     * @return string
     * @throws \ReflectionException
     */
    public function fetch(string $url, string $to_file = '', bool $reset_options = false): string
    {
        // Build URL unit
        $url_unit = $this->buildUrlUnit($url);

        // Merge configuration for this request (only user_config + defaults, no curl_options)
        $request_config = $this->buildRequestConfig($url_unit);

        // Build cURL options array from request_config and persistent curl_options
        $curl_options = $this->buildCurlOptions($request_config, $this->curl_options, '' === $to_file);

        // Reset persistent config if requested
        if ($reset_options) {
            $this->resetOptions();
        }

        // Initialize cURL
        $curl_handle = curl_init($url);
        $file_handle = null;

        if ('' !== $to_file) {
            $file_handle = fopen($to_file, 'wb');

            $curl_options[CURLOPT_FILE] = $file_handle;
        }

        curl_setopt_array($curl_handle, $curl_options);

        $response         = curl_exec($curl_handle);
        $this->curl_info  = curl_getinfo($curl_handle);
        $this->curl_error = curl_error($curl_handle);

        if (null !== $file_handle) {
            fflush($file_handle);
            fclose($file_handle);
            unset($file_handle);
        }

        if (is_string($response)) {
            $this->parseHttpResponse($response);
        }

        // Determine return value
        if (isset($curl_options[CURLOPT_WRITEFUNCTION])) {
            $output = '';
        } elseif ('' === $to_file) {
            $output = $this->http_body;
        } else {
            $output = $to_file;
        }

        // Clear temporary data
        $this->requestData = [];
        $this->requestFile = [];

        unset($url, $to_file, $reset_options, $url_unit, $request_config, $curl_options, $curl_handle, $response);
        return $output;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->curl_info['http_code'];
    }

    /**
     * @return float
     */
    public function getDownSize(): float
    {
        return $this->curl_info['size_download'];
    }

    /**
     * @return float
     */
    public function getBodySize(): float
    {
        return $this->curl_info['download_content_length'];
    }

    /**
     * @return float
     */
    public function getTotalTime(): float
    {
        return $this->curl_info['total_time'];
    }

    /**
     * @return string
     */
    public function getHttpBody(): string
    {
        return $this->http_body;
    }

    /**
     * @return string
     */
    public function getHttpError(): string
    {
        return $this->curl_error;
    }

    /**
     * @return array
     */
    public function getHttpHeader(): array
    {
        return $this->http_header;
    }

    /**
     * @return array
     */
    public function getHttpCookie(): array
    {
        return $this->http_cookie;
    }

    /**
     * @param string $cookie
     *
     * @return array
     */
    public function parseRawCookie(string $cookie): array
    {
        $cookie_data = [];
        $cookie_list = str_contains($cookie, '; ') ? explode('; ', $cookie) : [$cookie];

        foreach ($cookie_list as $value) {
            $pos = strpos($value, '=');

            if (false !== $pos) {
                $cookie_data[substr($value, 0, $pos)] = substr($value, $pos + 1);
            }
        }

        unset($cookie, $cookie_list, $value, $pos);
        return $cookie_data;
    }

    /**
     * @param string $url
     *
     * @return array
     */
    private function buildUrlUnit(string $url): array
    {
        $url_unit = parse_url($url);

        if (false === $url_unit || !isset($url_unit['scheme'], $url_unit['host'])) {
            return [];
        }

        $url_unit['path']  = $url_unit['path'] ?? '/';
        $url_unit['query'] = isset($url_unit['query']) ? '?' . $url_unit['query'] : '';

        unset($url);
        return $url_unit;
    }

    /**
     * Build request configuration from defaults and user_config (no curl_options)
     *
     * @param array $url_unit
     *
     * @return array
     */
    private function buildRequestConfig(array $url_unit): array
    {
        // Start with defaults
        $config = self::CURL_DEFAULT;

        // Merge persistent user configuration (string keys that are not direct cURL options)
        foreach ($this->user_config as $key => $value) {
            $config[$key] = $value;
        }

        // Merge temporary data (data + file)
        $config['data'] = array_merge($this->requestData, $this->requestFile);

        if (!empty($this->requestFile)) {
            $config['http_content_type'] = self::CONTENT_TYPE_FORM_DATA;
        }

        $config['url_unit']    = $url_unit;
        $config['http_method'] = strtoupper($config['http_method']);

        // Build HTTP headers (uses mergeHeaderConfig logic)
        $config = $this->mergeHeaderConfig($url_unit, $config);

        unset($url_unit, $key, $value);
        return $config;
    }

    /**
     * Build cURL options from request config and persistent curl options
     *
     * @param array $request_config
     * @param array $curl_options
     * @param bool  $with_header
     *
     * @return array
     * @throws \ReflectionException
     */
    private function buildCurlOptions(array $request_config, array $curl_options, bool $with_header): array
    {
        // Start with mandatory options
        $opt = [
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_AUTOREFERER    => true,
            CURLOPT_COOKIESESSION  => true,
            CURLOPT_FOLLOWLOCATION => false,
        ];

        // Default User-Agent
        if (isset($request_config['user_agent'])) {
            $opt[CURLOPT_USERAGENT] = $request_config['user_agent'];
        }

        // Cookie may be set via addCookie (stored in $request_config)
        if (isset($request_config['cookie'])) {
            $opt[CURLOPT_COOKIE] = $request_config['cookie'];
        }

        // Max follow redirects handling (requires FOLLOWLOCATION)
        if (isset($request_config['max_follow']) && 0 < $request_config['max_follow']) {
            $opt[CURLOPT_FOLLOWLOCATION] = true;
            $opt[CURLOPT_MAXREDIRS]      = $request_config['max_follow'];
        }

        // SSL Options
        if (isset($request_config['ssl_verifyhost'])) {
            $opt[CURLOPT_SSL_VERIFYHOST] = $request_config['ssl_verifyhost'];
        }

        if (isset($request_config['ssl_verifypeer'])) {
            $opt[CURLOPT_SSL_VERIFYPEER] = $request_config['ssl_verifypeer'];

            // Set CA for SSL verify peer (windows only)
            if (true === $request_config['ssl_verifypeer'] && 'Windows' === PHP_OS_FAMILY) {
                $opt[CURLOPT_SSL_OPTIONS] = CURLSSLOPT_NATIVE_CA;
            }
        }

        // Request body
        if (!empty($request_config['data'])) {
            if ('GET' === $request_config['http_method']) {
                $request_config['http_method'] = 'POST';
            }

            if (false !== stripos($request_config['http_content_type'], 'urlencoded')) {
                $opt[CURLOPT_POSTFIELDS] = http_build_query($request_config['data']);
            } elseif (false !== stripos($request_config['http_content_type'], 'json')) {
                $opt[CURLOPT_POSTFIELDS] = json_encode($request_config['data'], JSON_FORMAT);
            } elseif (false !== stripos($request_config['http_content_type'], 'xml')) {
                $opt[CURLOPT_POSTFIELDS] = IOData::new()->toXml($request_config['data']);
            } else {
                $opt[CURLOPT_POSTFIELDS] = $request_config['data'];
            }
        }

        // Other curl options that come from request_config
        $opt[CURLOPT_HEADER]        = $with_header;
        $opt[CURLOPT_HTTPHEADER]    = $request_config['header'];
        $opt[CURLOPT_PORT]          = $request_config['url_unit']['port'] ?? ('https' === $request_config['url_unit']['scheme'] ? 443 : 80);
        $opt[CURLOPT_CUSTOMREQUEST] = $request_config['http_method'];

        // Merge persistent cURL options (int keys) – these have highest priority
        foreach ($curl_options as $key => $value) {
            $opt[$key] = $value;
        }

        // Handle WRITEFUNCTION vs RETURNTRANSFER
        if (isset($opt[CURLOPT_WRITEFUNCTION])) {
            $opt[CURLOPT_HEADER] = false;
            unset($opt[CURLOPT_RETURNTRANSFER]);
        } elseif (!isset($opt[CURLOPT_RETURNTRANSFER])) {
            $opt[CURLOPT_RETURNTRANSFER] = true;
        }

        unset($request_config, $curl_options, $with_header, $key, $value);
        return $opt;
    }

    /**
     * @param array $url_unit
     * @param array $config
     *
     * @return array
     */
    private function mergeHeaderConfig(array $url_unit, array $config): array
    {
        $header_unit = [
            'Host' => $url_unit['host'] . (isset($url_unit['port']) ? ':' . $url_unit['port'] : '')
        ];

        if (isset($config['header'])) {
            $header_unit += $config['header'];
        }

        if (isset($config['etag'])) {
            $header_unit['If-None-Match'] = $config['etag'];
        }

        if (isset($config['last_modified'])) {
            $header_unit['If-Modified-Since'] = $config['last_modified'];
        }

        $header_unit += [
            'Accept'          => $config['accept_type'],
            'Accept-Charset'  => $config['accept_charset'],
            'Accept-Language' => $config['accept_language'],
            'Content-Type'    => $config['http_content_type'],
            'Connection'      => $config['http_connection']
        ];

        $headers = [];

        foreach ($header_unit as $key => $val) {
            $headers[] = $key . ': ' . $val;
        }

        $config['header'] = $headers;

        unset($url_unit, $header_unit, $headers, $key, $val);
        return $config;
    }

    /**
     * @param string $response
     *
     * @return void
     */
    private function parseHttpResponse(string $response): void
    {
        $this->raw_cookie  = '';
        $this->http_header = [];
        $this->http_cookie = [];

        [$this->raw_header, $this->http_body] = explode("\r\n\r\n", $response, 2);

        $data_list = explode("\r\n", $this->raw_header);

        foreach ($data_list as $value) {
            $pos = strpos($value, ':');

            if (false === $pos) {
                continue;
            }

            $key = strtolower(substr($value, 0, $pos));
            $val = substr($value, $pos + 2);

            $this->http_header[$key] = $val;

            if ('set-cookie' === $key) {
                if ('' !== $this->raw_cookie) {
                    $this->raw_cookie .= '; ';
                }

                $val_pos = strpos($val, '; ');

                if (false !== $val_pos) {
                    $val = substr($val, 0, $val_pos);
                }

                $this->raw_cookie .= rtrim($val, ';');
                $kv_pos           = strpos($val, '=');

                if (false !== $kv_pos) {
                    $this->http_cookie[substr($val, 0, $kv_pos)] = substr($val, $kv_pos + 1);
                }
            }
        }

        unset($response, $data_list, $value, $pos, $key, $val, $val_pos, $kv_pos);
    }
}