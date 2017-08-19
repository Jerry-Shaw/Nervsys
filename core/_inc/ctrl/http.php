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

class http {
    //Request URL
    public static $url;

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

    //Max follow level
    public static $max_follow = 0;

    //Return with body
    public static $with_body = true;

    //Return with header
    public static $with_header = false;

    //HTTP Accept
    public static $accept = 'text/plain,text/html,text/xml,application/json,*;q=0';

    //User-Agent
    public static $user_agent = 'Mozilla/5.0 (Compatible; NervSys API 3.0; Granted by NervSys)';

    //Request Method
    private static $method = 'GET';

    //CURL Resource
    private static $curl = [];

    /**
     * Prepare unit for URL
     *
     * @param string $url
     *
     * @return array
     */
    private static function url_unit(string $url): array {
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
     * @param string $url
     * @param array  $unit
     *
     * @return array
     */
    private static function url_header(string $url, array $unit): array {
        //Prepare HTTP Header
        $header = [
            self::$method . ' ' . $unit['path'] . $unit['query'] . ' HTTP/' . self::$ver,
            'Host: ' . $unit['host'] . ':' . $unit['port'],
            'Accept: ' . self::$accept,
            'Accept-Charset: UTF-8,*;q=0',
            'Accept-Encoding: identity,*;q=0',
            'Accept-Language: en-US,en,zh-CN,zh,*;q=0',
            'Connection: keep-alive',
            'User-Agent: ' . self::$user_agent
        ];
        if ('' !== self::$key) $header[] = 'KEY: ' . self::$key;
        if ('' !== self::$ETag) $header[] = 'If-None-Match: ' . self::$ETag;
        if ('' !== self::$Cookie) $header[] = 'Cookie: ' . self::$Cookie;
        if ('' !== self::$Modified) $header[] = 'If-Modified-Since: ' . self::$Modified;
        unset($url, $unit);
        return $header;
    }

    /**
     * CURL ready
     *
     * @param string $url
     * @param int    $port
     * @param array  $header
     */
    private static function curl_ready(string $url, int $port, array $header): void {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_PORT, $port);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_AUTOREFERER, true);
        curl_setopt($curl, CURLOPT_COOKIESESSION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt($curl, CURLOPT_ENCODING, 'identity,*;q=0');
        if (!self::$with_body) curl_setopt($curl, CURLOPT_NOBODY, true);
        if (self::$with_header) curl_setopt($curl, CURLOPT_HEADER, true);
        if ('' !== self::$Cookie) curl_setopt($curl, CURLOPT_COOKIE, self::$Cookie);
        if ('' !== self::$ssl_key) curl_setopt($curl, CURLOPT_SSLKEY, self::$ssl_key);
        if ('' !== self::$ssl_cert) curl_setopt($curl, CURLOPT_SSLCERT, self::$ssl_cert);
        //Follow settings
        if (0 < self::$max_follow) {
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, self::$max_follow);
        }
        //POST settings
        if ('POST' === self::$method) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, empty(self::$file) ? http_build_query(self::$data) : self::$data);
        }
        //Merge CURL
        self::$curl[$url] = &$curl;
        unset($url, $port, $header, $curl);
    }

    /**
     * CURL run
     *
     * @return array
     */
    private static function curl_run(): array {
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
    public static function request(): array {
        //Check URL
        if (empty(self::$url)) return [];
        //Detect method
        if (!empty(self::$data)) self::$method = 'POST';
        //Merge URL
        $list = is_string(self::$url) ? [self::$url] : self::$url;
        //Prepare CURL
        foreach ($list as $url) {
            //No URL
            if ('' === $url) continue;
            //Get URL unit
            $unit = self::url_unit($url);
            if (empty($unit)) continue;
            //Get CURL ready
            self::curl_ready($url, $unit['port'], self::url_header($url, $unit));
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
    public static function upload(): array {
        //Check URL
        if (empty(self::$url)) return [];
        //Set method to POST
        self::$method = 'POST';
        //Validate files
        $files = [];
        foreach (self::$file as $key => $item) if (is_file($item)) $files[$key] = new \CURLFile($item);
        //Check files
        if (empty($files)) return [];
        //Attach files
        self::$data = array_merge(self::$data, $files);
        unset($files, $key, $item);
        //Merge URL
        $list = is_string(self::$url) ? [self::$url] : self::$url;
        //Prepare CURL
        foreach ($list as $url) {
            //No URL
            if ('' === $url) continue;
            //Get URL unit
            $unit = self::url_unit($url);
            if (empty($unit)) continue;
            //Get URL header
            $header = self::url_header($url, $unit);
            //Add "Content-Type"
            $header[] = 'Content-Type: multipart/form-data';
            //Get CURL ready
            self::curl_ready($url, $unit['port'], $header);
        }
        //Execute CURL
        unset($list, $url, $unit, $header);
        return self::curl_run();
    }
}