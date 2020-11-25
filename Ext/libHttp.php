<?php

/**
 * HTTP Request Extension
 *
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
    const CONTENT_TYPE_XML         = 'application/xml; charset=UTF-8';
    const CONTENT_TYPE_JSON        = 'application/json; charset=UTF-8';
    const CONTENT_TYPE_FORM_DATA   = 'multipart/form-data';
    const CONTENT_TYPE_URL_ENCODED = 'application/x-www-form-urlencoded';

    //Job info
    public array  $data   = [];
    public array  $file   = [];
    public array  $header = [];

    public string $url        = '';
    public string $etag       = '';
    public string $cookie     = '';
    public string $referer    = '';
    public string $modified   = '';
    public string $curl_error = '';

    public string $proxy        = '';
    public string $proxy_passwd = '';

    public int $max_follow    = 0;
    public int $response_code = 0;

    public string $http_ver   = 'HTTP/2';                                               //HTTP Version
    public string $method     = 'GET';                                                  //Request method
    public string $user_agent = 'Mozilla/5.0 (Compatible; NervSys/' . NS_VER . ')';     //User Agent string
    public string $connection = 'keep-alive';                                           //Connection type

    public string $content_type    = self::CONTENT_TYPE_URL_ENCODED;    //Content type
    public string $accept_charset  = 'UTF-8,*;q=0';                     //Accept charset
    public string $accept_encoding = 'gzip,deflate,identity,*;q=0';     //Accept encoding
    public string $accept_language = 'en-US,en,zh-CN,zh,*;q=0';         //Accept language

    public string $accept_type = 'application/json;q=0.9,application/xml;q=0.8,text/plain;q=0.7,text/html;q=0.6,*/*;q=0.5'; //Accept types

    /**
     * libHttp constructor.
     *
     * @param string $url
     */
    public function __construct(string $url = '')
    {
        $this->url = &$url;
        unset($url);
    }

    /**
     * Add request data
     *
     * @param array ...$data
     *
     * @return $this
     */
    public function addData(array ...$data): self
    {
        foreach ($data as $item) {
            $this->data += $item;
        }

        unset($data, $item);
        return $this;
    }

    /**
     * Add upload file
     *
     * @param array ...$file
     *
     * @return $this
     */
    public function addFile(array ...$file): self
    {
        foreach ($file as $key => $val) {
            if (file_exists($val)) {
                $this->file[$key] = new \CURLFile($val);
            }
        }

        unset($file, $key, $val);
        return $this;
    }

    /**
     * Add header data
     *
     * @param array $header
     *
     * @return $this
     */
    public function addHeader(array $header): self
    {
        $this->header += $header;

        unset($header);
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
        foreach ($cookie as $key => $val) {
            if ('' !== $this->cookie) {
                $this->cookie .= '; ';
            }

            $this->cookie .= $key . '=' . $val;
        }

        unset($cookie, $key, $val);
        return $this;
    }

    /**
     * Set method
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method = 'POST'): self
    {
        $this->method = strtoupper($method);

        unset($method);
        return $this;
    }

    /**
     * Set content type
     *
     * @param string $content_type
     *
     * @return $this
     */
    public function setContentType(string $content_type = self::CONTENT_TYPE_URL_ENCODED): self
    {
        $this->content_type = &$content_type;

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
        $this->referer = &$referer;

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
        $this->user_agent = &$user_agent;

        unset($user_agent);
        return $this;
    }

    /**
     * Set max follows
     *
     * @param int $max_follow
     *
     * @return $this
     */
    public function setMaxFollow(int $max_follow): self
    {
        $this->max_follow = &$max_follow;

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
        $this->accept_type = &$accept_type;

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
        $this->etag = &$etag;

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
        $this->modified = &$last_modified;

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
        $this->proxy        = &$proxy;
        $this->proxy_passwd = &$proxy_passwd;

        unset($proxy, $proxy_passwd);
        return $this;
    }

    /**
     * Fetch response data
     *
     * @param bool $with_body
     * @param bool $with_header
     *
     * @return string
     * @throws \Exception
     */
    public function fetch(bool $with_body = true, bool $with_header = false): string
    {
        if ('' === $this->url) {
            throw new \Exception('URL not set!', E_USER_NOTICE);
        }

        //Prepare data
        if (!empty($this->file)) {
            $this->data         += $this->file;
            $this->content_type = self::CONTENT_TYPE_FORM_DATA;
        }

        //Set method
        if (!empty($this->data)) {
            $this->method = 'POST';
        }

        //Get URL units
        $url_unit = $this->getUrlUnit($this->url);

        //Get cURL headers
        $header = $this->getHeader($url_unit);

        //Initialize
        $opt  = [];
        $curl = curl_init();

        //Build options
        $opt[CURLOPT_URL]            = $this->url;
        $opt[CURLOPT_PORT]           = &$url_unit['port'];
        $opt[CURLOPT_TIMEOUT]        = 60;
        $opt[CURLOPT_NOSIGNAL]       = true;
        $opt[CURLOPT_AUTOREFERER]    = true;
        $opt[CURLOPT_COOKIESESSION]  = true;
        $opt[CURLOPT_RETURNTRANSFER] = true;
        $opt[CURLOPT_SSL_VERIFYHOST] = 2;
        $opt[CURLOPT_SSL_VERIFYPEER] = false;
        $opt[CURLOPT_HTTPHEADER]     = &$header;
        $opt[CURLOPT_ENCODING]       = $this->accept_encoding;
        $opt[CURLOPT_USERAGENT]      = $this->user_agent;
        $opt[CURLOPT_CUSTOMREQUEST]  = strtoupper($this->method);
        $opt[CURLOPT_POST]           = ('POST' === $this->method);
        $opt[CURLOPT_NOBODY]         = !$with_body;
        $opt[CURLOPT_HEADER]         = &$with_header;

        if ('' !== $this->cookie) {
            $opt[CURLOPT_COOKIE] = $this->cookie;
        }

        if ('' !== $this->referer) {
            $opt[CURLOPT_REFERER] = $this->referer;
        }

        if (0 < $this->max_follow) {
            $opt[CURLOPT_FOLLOWLOCATION] = true;
            $opt[CURLOPT_MAXREDIRS]      = $this->max_follow;
        }

        if ('' !== $this->proxy) {
            $opt[CURLOPT_PROXY] = $this->proxy;

            if ('' !== $this->proxy_passwd) {
                $opt[CURLOPT_PROXYUSERPWD] = $this->proxy_passwd;
            }
        }

        if (!empty($this->data)) {
            switch ($this->content_type) {
                case self::CONTENT_TYPE_JSON:
                    $opt[CURLOPT_POSTFIELDS] = json_encode($this->data);
                    break;

                case self::CONTENT_TYPE_XML:
                    $opt[CURLOPT_POSTFIELDS] = IOUnit::new()->toXml($this->data);
                    break;

                case self::CONTENT_TYPE_URL_ENCODED:
                    $opt[CURLOPT_POSTFIELDS] = http_build_query($this->data);
                    break;

                default:
                    $opt[CURLOPT_POSTFIELDS] = &$this->data;
                    break;
            }
        }

        //Set cURL options
        curl_setopt_array($curl, $opt);

        //Get response
        $response = curl_exec($curl);

        //Collect HTTP CODE or ERROR
        false !== $response
            ? $this->response_code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE)
            : $this->curl_error = curl_error($curl);

        //Close cURL handle
        curl_close($curl);

        unset($opt, $curl, $key, $val);
        return (string)$response;
    }

    /**
     * Get last HTTP response code
     *
     * @return string
     */
    public function getHttpCode(): string
    {
        return $this->response_code;
    }

    /**
     * Get last HTTP curl error
     *
     * @return string
     */
    public function getLastError(): string
    {
        return $this->curl_error;
    }

    /**
     * Extract URL units
     *
     * @param string $url
     *
     * @return array
     */
    private function getUrlUnit(string $url): array
    {
        //Parse URL
        $unit = parse_url($url);

        //Check main components
        if (false === $unit || !isset($unit['scheme']) || !isset($unit['host'])) {
            return [];
        }

        //Prepare URL unit
        if (!isset($unit['path'])) {
            $unit['path'] = '/';
        }

        $unit['query'] = isset($unit['query']) ? '?' . $unit['query'] : '';

        if (!isset($unit['port'])) {
            $unit['port'] = 'https' === $unit['scheme'] ? 443 : 80;
        }

        unset($url);
        return $unit;
    }

    /**
     * get request header
     *
     * @param array $url_unit
     *
     * @return array
     */
    private function getHeader(array $url_unit): array
    {
        $header_list = ['Host' => $url_unit['host'] . ':' . $url_unit['port']];

        if (!empty($this->header)) {
            $header_list += $this->header;
        }

        if ('' !== $this->cookie) {
            $header_list['Cookie'] = $this->cookie;
        }

        if ('' !== $this->etag) {
            $header_list['If-None-Match'] = $this->etag;
        }

        if ('' !== $this->modified) {
            $header_list['If-Modified-Since'] = $this->modified;
        }

        $header_list += [
            'Accept'          => $this->accept_type,
            'Accept-Charset'  => $this->accept_charset,
            'Accept-Encoding' => $this->accept_encoding,
            'Accept-Language' => $this->accept_language,
            'Content-Type'    => $this->content_type,
            'User-Agent'      => $this->user_agent,
            'Connection'      => $this->connection
        ];

        $headers = [$this->method . ' ' . $url_unit['path'] . $url_unit['query'] . ' ' . $this->http_ver];

        foreach ($header_list as $key => $val) {
            $headers[] = $key . ': ' . $val;
        }

        unset($url_unit, $header_list, $key, $val);
        return $headers;
    }

    /**
     * Destroy from Factory
     */
    public function __destruct()
    {
        $this->destroy();
    }
}