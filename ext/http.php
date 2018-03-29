<?php

/**
 * HTTP Request Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2017 秋水之冰 <27206617@qq.com>
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
    //Request URL
    public static $url = null;

    //Protocol Version
    public static $ver = '2.0';

    //Request Data
    public static $data = [];

    //File Path
    public static $file = [];

    //ETag
    public static $ETag = '';

    //Cookie
    public static $Cookie = '';

    //Header value
    public static $Header = [];

    //Request Method
    public static $Method = 'GET';

    //Referer
    public static $Referer = '';

    //Last-Modified
    public static $Modified = '';

    //SSL KEY
    public static $ssl_key = '';

    //SSL CERT
    public static $ssl_cert = '';

    //Authority
    public static $user_pwd = '';

    //Max follow level
    public static $max_follow = 0;

    //Return with body
    public static $with_body = true;

    //Return with header
    public static $with_header = false;

    //Send via "Request Payload"
    public static $send_payload = false;

    //HTTP Accept
    public static $accept = 'text/plain,text/html,text/xml,application/json,*;q=0';

    //User-Agent
    public static $user_agent = 'Mozilla/5.0 (Compatible; NervSys API ' . NS_VER . '; Powered by NervSys!)';

    //Content-Type
    private static $content_type = 'application/json; charset=utf-8';

    //CURL Resource
    private static $curl = [];

    /**
     * Check requested URLs
     *
     * @return bool
     */
    private static function chk_url(): bool
    {
        return (is_string(self::$url) || is_array(self::$url)) && !empty(self::$url) ? true : false;
    }

    /**
     * Prepare unit for URL
     *
     * @param string $url
     *
     * @return array
     */
    private static function get_unit(string $url): array
    {
        //Parse URL
        $unit = parse_url($url);

        //Check main components
        if (false === $unit || !isset($unit['scheme']) || !isset($unit['host'])) return [];

        //Prepare URL unit
        if (!isset($unit['path'])) $unit['path'] = '/';
        $unit['query'] = !isset($unit['query']) ? '' : '?' . $unit['query'];
        if (!isset($unit['port'])) $unit['port'] = 'https' === $unit['scheme'] ? 443 : 80;

        unset($url);
        return $unit;
    }

    /**
     * Prepare header for URL
     *
     * @param array $unit
     *
     * @return array
     */
    private static function get_header(array $unit): array
    {
        //Prepare HTTP Header
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

        if ('' !== self::$ETag) $header[] = 'If-None-Match: ' . self::$ETag;
        if ('' !== self::$Cookie) $header[] = 'Cookie: ' . self::$Cookie;
        if ('' !== self::$Modified) $header[] = 'If-Modified-Since: ' . self::$Modified;

        if (empty(self::$Header)) return $header;

        //Prepare other Header content
        foreach (self::$Header as $key => $value) $header[] = $key . ': ' . $value;

        unset($key, $value);
        return $header;
    }

    /**
     * CURL ready
     *
     * @param string $url
     * @param int    $port
     * @param array  $header
     */
    private static function curl_ready(string $url, int $port, array $header): void
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

        if (!self::$with_body) $opt[CURLOPT_NOBODY] = true;
        if (self::$with_header) $opt[CURLOPT_HEADER] = true;
        if ('' !== self::$Cookie) $opt[CURLOPT_COOKIE] = self::$Cookie;
        if ('' !== self::$Referer) $opt[CURLOPT_REFERER] = self::$Referer;
        if ('' !== self::$ssl_key) $opt[CURLOPT_SSLKEY] = self::$ssl_key;
        if ('' !== self::$ssl_cert) $opt[CURLOPT_SSLCERT] = self::$ssl_cert;
        if ('' !== self::$user_pwd) $opt[CURLOPT_USERPWD] = self::$user_pwd;
        if ('POST' === self::$Method) $opt[CURLOPT_POST] = true;
        if (!empty(self::$data)) $opt[CURLOPT_POSTFIELDS] = !self::$send_payload ? (empty(self::$file) ? http_build_query(self::$data) : self::$data) : json_encode(self::$data, JSON_OPT);

        //Follow settings
        if (0 < self::$max_follow) {
            $opt[CURLOPT_FOLLOWLOCATION] = true;
            $opt[CURLOPT_MAXREDIRS] = self::$max_follow;
        }

        //Set CURL options
        curl_setopt_array($curl, $opt);

        //Merge CURL
        self::$curl[$url] = &$curl;
        unset($url, $port, $header, $opt, $curl);
    }

    /**
     * CURL run
     *
     * @return array
     */
    private static function curl_run(): array
    {
        //No CURL resource
        if (empty(self::$curl)) return [];

        //Run CURL
        if (1 === count(self::$curl)) {
            //Single CURL
            $curl = current(self::$curl);
            $response = [key(self::$curl) => (string)curl_exec($curl)];
            curl_close($curl);
        } else {
            //Multi CURL
            $curl = curl_multi_init();

            //Add handles
            foreach (self::$curl as $url => $res) curl_multi_add_handle($curl, $res);

            //execute handles
            while (CURLM_OK === curl_multi_exec($curl, $running) && 0 < $running) ;

            //Merge response
            $response = [];
            foreach (self::$curl as $url => $res) {
                $response[$url] = (string)curl_multi_getcontent($res);
                //Remove handles
                curl_multi_remove_handle($curl, $res);
            }

            //close handle
            curl_multi_close($curl);
            unset($url, $res);
        }

        unset($curl);

        //Free CURL list
        self::$curl = [];

        return $response;
    }

    /**
     * Run CURL
     *
     * @return array
     */
    public static function request(): array
    {
        //Check URL
        if (!self::chk_url()) {
            debug(__CLASS__, 'No URL entry or URL ERROR!');
            return [];
        }

        //Correct Method
        if ('GET' === self::$Method && !empty(self::$data)) self::$Method = 'POST';

        //Merge URL
        $list = is_string(self::$url) ? [self::$url] : self::$url;

        //Choose Content-Type
        if (!self::$send_payload) self::$content_type = 'application/x-www-form-urlencoded';

        //Prepare CURL
        foreach ($list as $url) {
            //No URL
            if ('' === $url) continue;
            //Get URL unit
            $unit = self::get_unit($url);
            if (empty($unit)) continue;
            //Get CURL ready
            self::curl_ready($url, $unit['port'], self::get_header($unit));
        }

        //Execute CURL
        unset($list, $url, $unit);
        return self::curl_run();
    }

    /**
     * Run CURLFile
     *
     * @return array
     */
    public static function upload(): array
    {
        //Check URL
        if (!self::chk_url()) {
            debug(__CLASS__, 'No URL entry or URL ERROR!');
            return [];
        }

        //Check Method
        if (!in_array(self::$Method, ['POST', 'PUT'], true)) {
            debug(__CLASS__, 'Method NOT allowed!');
            return [];
        }

        //Validate files
        foreach (self::$file as $key => $item) {
            if (!is_file($item)) {
                debug(__CLASS__, '[' . $item . '] NOT found!');
                unset(self::$file[$key]);
                continue;
            }
            //Attach file to data
            self::$data[$key] = new \CURLFile($item);
        }

        //Check files
        if (empty(self::$file)) {
            debug(__CLASS__, 'No file to upload!');
            return [];
        }

        //Merge URL
        $list = is_string(self::$url) ? [self::$url] : self::$url;

        //Choose Content-Type
        if (!self::$send_payload) self::$content_type = 'multipart/form-data';

        //Prepare CURL
        foreach ($list as $url) {
            //No URL
            if ('' === $url) continue;
            //Get URL unit
            $unit = self::get_unit($url);
            if (empty($unit)) continue;
            //Get CURL ready
            self::curl_ready($url, $unit['port'], self::get_header($unit));
        }

        //Execute CURL
        unset($key, $item, $list, $url, $unit);
        return self::curl_run();
    }
}