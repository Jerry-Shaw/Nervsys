<?php

/**
 * HTTP Request Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace core\ctrl;

class http
{
    //Request URL
    public static $url = '';

    //Access Key
    public static $key = '';

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

    //Last-Modified
    public static $Modified = '';

    //SSL KEY
    public static $ssl_key = '';

    //SSL CERT
    public static $ssl_cert = '';

    //Return with body
    public static $with_body = true;

    //Return with header
    public static $with_header = false;

    //HTTP Accept
    public static $accept = 'text/plain,text/html,text/xml,application/json,*;q=0';

    //User-Agent
    public static $user_agent = 'Mozilla/5.0 (Compatible; NervSys Data API 2.0.0; Granted by NervSys Data Center)';

    //URL unit
    private static $unit = [];

    //HTTP Header
    private static $header = [];

    //Request Method
    private static $method = 'GET';

    /**
     * Prepare HTTP Request
     */
    private static function prepare()
    {
        $unit = parse_url(self::$url);
        if (false !== $unit && isset($unit['scheme']) && isset($unit['host'])) {
            //Prepare URL unit
            if (!isset($unit['path'])) $unit['path'] = '/';
            $unit['query'] = !isset($unit['query']) ? '' : '?' . $unit['query'];
            if (!isset($unit['port'])) $unit['port'] = 'https' === $unit['scheme'] ? 443 : 80;
            self::$unit = &$unit;
            //Prepare HTTP Method
            if (!empty(self::$data) || !empty(self::$file)) self::$method = 'POST';
            //Prepare HTTP Header
            $header = [];
            $header[] = self::$method . ' ' . $unit['path'] . $unit['query'] . ' HTTP/' . self::$ver;
            $header[] = 'Host: ' . $unit['host'] . ':' . $unit['port'];
            $header[] = 'Accept: ' . self::$accept;
            $header[] = 'Accept-Charset: UTF-8,*;q=0';
            $header[] = 'Accept-Encoding: identity,*;q=0';
            $header[] = 'Accept-Language: en-US,en,zh-CN,zh,*;q=0';
            $header[] = 'Connection: keep-alive';
            $header[] = 'User-Agent: ' . self::$user_agent;
            if ('' !== self::$key) $header[] = 'KEY: ' . self::$key;
            if ('' !== self::$Cookie) $header[] = 'Cookie: ' . self::$Cookie;
            if ('' !== self::$Modified) $header[] = 'If-Modified-Since: ' . self::$Modified;
            if ('' !== self::$ETag) $header[] = 'If-None-Match: ' . self::$ETag;
            self::$header = &$header;
            unset($header);
        }
        unset($unit);
    }

    /**
     * Run CURL
     *
     * @return string
     */
    public static function request(): string
    {
        if ('' !== self::$url) {
            self::prepare();
            if (!empty(self::$unit) && !empty(self::$header)) {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, self::$url);
                curl_setopt($curl, CURLOPT_PORT, self::$unit['port']);
                curl_setopt($curl, CURLOPT_TIMEOUT, 60);
                curl_setopt($curl, CURLOPT_MAXREDIRS, 0);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_AUTOREFERER, true);
                curl_setopt($curl, CURLOPT_COOKIESESSION, true);
                curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, self::$header);
                curl_setopt($curl, CURLOPT_USERAGENT, self::$user_agent);
                curl_setopt($curl, CURLOPT_ENCODING, 'identity,*;q=0');
                if (!self::$with_body) curl_setopt($curl, CURLOPT_NOBODY, true);
                if (self::$with_header) curl_setopt($curl, CURLOPT_HEADER, true);
                if ('' !== self::$Cookie) curl_setopt($curl, CURLOPT_COOKIE, self::$Cookie);
                if ('' !== self::$ssl_key) curl_setopt($curl, CURLOPT_SSLKEY, self::$ssl_key);
                if ('' !== self::$ssl_cert) curl_setopt($curl, CURLOPT_SSLCERT,  self::$ssl_cert);
                if ('POST' === self::$method) {
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(self::$data));
                }
                $response = curl_exec($curl);
                curl_close($curl);
                unset($curl);
            } else $response = '';
        } else $response = '';
        return (string)$response;
    }

    /**
     * Run CURLFile
     *
     * @return string
     */
    public static function upload(): string
    {
        if ('' !== self::$url && !empty(self::$file)) {
            self::prepare();
            if (!empty(self::$unit) && !empty(self::$header)) {
                $files = [];
                foreach (self::$file as $key => $item) if (is_file($item)) $files[$key] = new \CURLFile($item);
                if (!empty($files)) {
                    self::$data = array_merge(self::$data, $files);
                    self::$header = 'Content-Type: multipart/form-data';
                    $curl = curl_init();
                    curl_setopt($curl, CURLOPT_URL, self::$url);
                    curl_setopt($curl, CURLOPT_PORT, self::$unit['port']);
                    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
                    curl_setopt($curl, CURLOPT_MAXREDIRS, 0);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_AUTOREFERER, true);
                    curl_setopt($curl, CURLOPT_COOKIESESSION, true);
                    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, self::$header);
                    curl_setopt($curl, CURLOPT_USERAGENT, self::$user_agent);
                    curl_setopt($curl, CURLOPT_ENCODING, 'identity,*;q=0');
                    if (!self::$with_body) curl_setopt($curl, CURLOPT_NOBODY, true);
                    if (self::$with_header) curl_setopt($curl, CURLOPT_HEADER, true);
                    if ('' !== self::$Cookie) curl_setopt($curl, CURLOPT_COOKIE, self::$Cookie);
                    if ('' !== self::$ssl_key) curl_setopt($curl, CURLOPT_SSLKEY, self::$ssl_key);
                    if ('' !== self::$ssl_cert) curl_setopt($curl, CURLOPT_SSLCERT,  self::$ssl_cert);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, self::$data);
                    $response = curl_exec($curl);
                    curl_close($curl);
                    unset($curl);
                } else $response = '';
                unset($files, $key, $item);
            } else $response = '';
        } else $response = '';
        return (string)$response;
    }
}