<?php

/**
 * HTTP Request Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

namespace ext;

class http
{
    //URL (string / array)
    public static $url = null;

    //HTTP Version
    public static $ver = '2.0';

    //Request Data
    public static $data = [];

    //Upload files
    public static $file = [];

    //Request type
    public static $payload = true;

    //HTTP options
    public static $ETag         = '';       //ETag
    public static $Cookie       = '';       //Cookie
    public static $Header       = [];       //Header
    public static $Method       = 'GET';    //Method
    public static $Referer      = '';       //Referer
    public static $Modified     = '';       //Last-Modified
    public static $accept       = 'text/plain,text/html,text/xml,application/json,*;q=0';   //Accept
    public static $user_agent   = 'Mozilla/5.0 (Compatible; NervSys/' . NS_VER . ')';       //User Agent
    public static $content_type = 'application/json; charset=utf-8';                        //Content-type

    //SSL certificates
    public static $ssl_key  = '';
    public static $ssl_cert = '';

    //Authorization
    public static $user_pwd = '';

    //cURL options
    public static $max_follow  = 0;
    public static $with_body   = true;
    public static $with_header = false;

    //cURL Resource pool
    private static $curl = [];

    /**
     * Request
     *
     * @param string $url
     * @param array  $data
     * @param array  $file
     *
     * @return array
     * @throws \Exception
     */
    public static function request(string $url = '', array $data = [], array $file = []): array
    {
        //Prepare
        $list_url = self::prep_url($url);
        $list_data = self::prep_data($data, $file);

        //Prepare cURL
        foreach ($list_url as $target) {
            //Prepare URL unit
            $unit = self::prep_unit($target);

            if (empty($unit)) {
                continue;
            }

            //Add cURL
            self::curl_add($target, $unit['port'], $list_data, self::prep_header($unit));
        }

        unset($url, $data, $list_url, $list_data, $target, $unit);
        return self::curl_run();
    }

    /**
     * Prepare URL list
     *
     * @param string $url
     *
     * @return array
     * @throws \Exception
     */
    private static function prep_url(string $url): array
    {
        $list = '' === $url ? (is_string(self::$url) ? [self::$url] : (is_array(self::$url) ? self::$url : [])) : [$url];

        if (empty($list)) {
            throw new \Exception('NO URLs in request list!');
        }

        unset($url);
        return $list;
    }

    /**
     * Prepare data & file
     *
     * @param array $data
     * @param array $file
     *
     * @return array
     */
    private static function prep_data(array $data, array $file = []): array
    {
        $data = empty($data) ? self::$data : $data;

        if (!empty($data) && !self::$payload) {
            self::$content_type = 'application/x-www-form-urlencoded';
        }

        $file = empty($file) ? self::$file : $file;

        if (!empty($file)) {
            //Create cURL files
            foreach ($file as $key => $item) {
                if (file_exists($item)) {
                    $file[$key] = new \CURLFile($item);
                } else {
                    unset($file[$key]);
                }
            }

            unset($key, $item);

            if (!self::$payload) {
                self::$content_type = 'multipart/form-data';
            }
        }

        if ((!empty($data) || !empty($file)) && !in_array(self::$Method, ['PUT', 'POST'], true)) {
            self::$Method = 'POST';
        }

        self::$data = self::$file = [];
        $list = ['data' => &$data, 'file' => &$file];

        unset($data, $file);
        return $list;
    }

    /**
     * Prepare URL units
     *
     * @param string $url
     *
     * @return array
     */
    private static function prep_unit(string $url): array
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
     * Prepare header
     *
     * @param array $unit
     *
     * @return array
     */
    private static function prep_header(array $unit): array
    {
        $header = [
            self::$Method . ' ' . $unit['path'] . $unit['query'] . ' HTTP/' . self::$ver,
            'Host: ' . $unit['host'] . ':' . $unit['port'],
            'Accept: ' . self::$accept,
            'Accept-Charset: UTF-8,*;q=0',
            'Accept-Encoding: identity,*;q=0',
            'Accept-Language: en-US,en,zh-CN,zh,*;q=0',
            'Content-Type: ' . self::$content_type,
            'User-Agent: ' . self::$user_agent,
            'Connection: keep-alive'
        ];

        unset($unit);

        if ('' !== self::$ETag) {
            $header[] = 'If-None-Match: ' . self::$ETag;
        }

        if ('' !== self::$Cookie) {
            $header[] = 'Cookie: ' . self::$Cookie;
        }

        if ('' !== self::$Modified) {
            $header[] = 'If-Modified-Since: ' . self::$Modified;
        }

        if (empty(self::$Header)) {
            return $header;
        }

        foreach (self::$Header as $key => $value) {
            $header[] = $key . ': ' . $value;
        }

        unset($key, $value);
        return $header;
    }

    /**
     * Add cURL
     *
     * @param string $url
     * @param int    $port
     * @param array  $data
     * @param array  $header
     */
    private static function curl_add(string $url, int $port, array $data, array $header): void
    {
        //Initialize
        $opt = [];
        $curl = curl_init();

        //Build options
        $opt[CURLOPT_URL] = $url;
        $opt[CURLOPT_PORT] = $port;
        $opt[CURLOPT_TIMEOUT] = 60;
        $opt[CURLOPT_NOSIGNAL] = true;
        $opt[CURLOPT_AUTOREFERER] = true;
        $opt[CURLOPT_COOKIESESSION] = true;
        $opt[CURLOPT_RETURNTRANSFER] = true;
        $opt[CURLOPT_SSL_VERIFYHOST] = 2;
        $opt[CURLOPT_SSL_VERIFYPEER] = false;
        $opt[CURLOPT_HTTPHEADER] = $header;
        $opt[CURLOPT_ENCODING] = 'identity,*;q=0';
        $opt[CURLOPT_USERAGENT] = self::$user_agent;
        $opt[CURLOPT_CUSTOMREQUEST] = self::$Method;

        if (!self::$with_body) {
            $opt[CURLOPT_NOBODY] = true;
        }

        if (self::$with_header) {
            $opt[CURLOPT_HEADER] = true;
        }

        if ('' !== self::$Cookie) {
            $opt[CURLOPT_COOKIE] = self::$Cookie;
        }

        if ('' !== self::$Referer) {
            $opt[CURLOPT_REFERER] = self::$Referer;
        }

        if ('' !== self::$ssl_key) {
            $opt[CURLOPT_SSLKEY] = self::$ssl_key;
        }

        if ('' !== self::$ssl_cert) {
            $opt[CURLOPT_SSLCERT] = self::$ssl_cert;
        }

        if ('' !== self::$user_pwd) {
            $opt[CURLOPT_USERPWD] = self::$user_pwd;
        }

        if ('POST' === self::$Method) {
            $opt[CURLOPT_POST] = true;
        }

        if (0 < self::$max_follow) {
            $opt[CURLOPT_FOLLOWLOCATION] = true;
            $opt[CURLOPT_MAXREDIRS] = self::$max_follow;
        }

        if (!empty($data['data']) || !empty($data['file'])) {
            $post_data = array_merge($data['data'], $data['file']);

            $opt[CURLOPT_POSTFIELDS] = !self::$payload
                ? (empty($data['file']) ? http_build_query($post_data) : $post_data)
                : json_encode($post_data);

            unset($post_data);
        }

        //Set cURL options
        curl_setopt_array($curl, $opt);

        //Add cURL
        self::$curl[$url] = &$curl;

        unset($url, $port, $data, $header, $opt, $curl);
    }

    /**
     * Run cURL
     *
     * @return array
     */
    private static function curl_run(): array
    {
        if (1 === count(self::$curl)) {
            //Single cURL
            $curl = current(self::$curl);
            $response = [key(self::$curl) => (string)curl_exec($curl)];
            curl_close($curl);
        } else {
            //Multi CURL
            $curl = curl_multi_init();

            //Add handles
            foreach (self::$curl as $res) {
                curl_multi_add_handle($curl, $res);
            }

            //execute handles
            while (CURLM_OK === curl_multi_exec($curl, $running) && 0 < $running) ;

            //Get response
            $response = [];

            foreach (self::$curl as $url => $res) {
                $response[$url] = (string)curl_multi_getcontent($res);

                //Remove handles
                curl_multi_remove_handle($curl, $res);
            }

            //close handle
            curl_multi_close($curl);

            unset($res, $url);
        }

        unset($curl);

        //Free cURL list
        self::$curl = [];

        return $response;
    }
}