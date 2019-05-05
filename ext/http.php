<?php

/**
 * HTTP Request Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

use core\parser\data;

use core\handler\factory;

class http extends factory
{
    //Pre-defined content types
    const CONTENT_TYPE_XML     = 'application/xml; charset=UTF-8';
    const CONTENT_TYPE_JSON    = 'application/json; charset=UTF-8';
    const CONTENT_TYPE_DATA    = 'multipart/form-data';
    const CONTENT_TYPE_ENCODED = 'application/x-www-form-urlencoded';

    protected $http       = 'HTTP/2.0';                                             //HTTP Version
    protected $accept     = 'text/plain,text/html,text/xml,application/json,*;q=0'; //Accept types
    protected $user_agent = 'Mozilla/5.0 (Compatible; NervSys/' . VER . ')';        //User Agent string
    protected $connection = 'keep-alive';                                           //Connection type

    protected $accept_charset  = 'UTF-8,*;q=0';                 //Accept charset
    protected $accept_encoding = 'gzip,deflate,identity,*;q=0'; //Accept encoding
    protected $accept_language = 'en-US,en,zh-CN,zh,*;q=0';     //Accept language

    //Job list
    private $jobs = [];

    /**
     * Add cURL job
     *
     * @param array $job
     * url:          string,       full URL address
     * data:         array,        data to send to URL
     * file:         array|string, full path of local files to send to URL
     * method:       string,       HTTP request method to request URL
     * header:       array,        custom request headers
     * Cookie:       string,       request COOKIE content
     * referer:      string,       referer URL
     * ETag:         string,       URL ETag
     * Modified:     string,       URL last Modified
     * ssl_key:      string,       SSL KEY file path
     * ssl_cert:     string,       SSL Certificate file path
     * user_pwd:     string,       authority request, format is "username:password"
     * with_body:    bool,         fetch with body or not
     * with_header:  bool,         fetch with header or not
     * max_follow:   int,          max follows when URL is redirected
     * user_agent:   string,       custom request User-Agent send to URL
     * content_type: string,       request content type, see "Pre-defined content types" above
     *
     * @return $this
     * @throws \Exception
     */
    public function add(array $job = []): object
    {
        //Check URL
        if (!isset($job['url'])) {
            throw new \Exception('Missing "url" parameter!', E_USER_WARNING);
        }

        //Process attached files
        if (isset($job['file'])) {
            $file = [];

            //Compatible with array and string
            if (is_string($job['file'])) {
                $job['file'] = [$job['file']];
            }

            foreach ($job['file'] as $key => $val) {
                if (file_exists($val)) {
                    $file['file_' . $key] = new \CURLFile($val);
                }
            }

            $job['file'] = &$file;
            unset($file, $key, $val);
        }

        //Check method
        if (isset($job['method'])) {
            $job['method'] = strtoupper($job['method']);

            if (!in_array($job['method'], ['GET', 'POST', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'CONNECT', 'TRACE', 'PATCH'], true)) {
                unset($job['method']);
            }
        }

        $this->jobs[] = &$job;

        unset($job);
        return $this;
    }

    /**
     * Fetch one URL
     *
     * @return string
     * @throws \Exception
     */
    public function fetch(): string
    {
        if (empty($this->jobs)) {
            throw new \Exception('No URL found! At least one URL is required to process cURL.', E_USER_NOTICE);
        }

        $item = current($this->jobs);

        $unit = $this->get_unit($item['url']);

        if (empty($unit)) {
            throw new \Exception('URL [' . $item['url'] . '] error! "SCHEME" and "HOST" MUST be contained.', E_USER_WARNING);
        }

        $item += $unit;

        unset($unit);

        $this->prep_param($item);
        $this->prep_header($item);
        $this->prep_curl($item);

        //Get response
        $res = (string)curl_exec($item['curl']);

        //Close cURL handle
        curl_close($item['curl']);

        unset($item);
        return $res;
    }

    /**
     * Fetch all URLs
     *
     * @return array
     * @throws \Exception
     */
    public function fetch_all(): array
    {
        if (empty($this->jobs)) {
            throw new \Exception('No URL found! At least one URL is required to process cURL.', E_USER_NOTICE);
        }

        //Multi CURL
        $res  = [];
        $curl = curl_multi_init();

        //Add CURL
        foreach ($this->jobs as $item) {
            $unit = $this->get_unit($item['url']);

            if (empty($unit)) {
                continue;
            }

            $item += $unit;

            unset($unit);

            $this->prep_param($item);
            $this->prep_header($item);
            $this->prep_curl($item);

            $res[] = $item;
            curl_multi_add_handle($curl, $item['curl']);
        }

        if (empty($res)) {
            throw new \Exception('No valid URL! "SCHEME" and "HOST" MUST be contained.', E_USER_WARNING);
        }

        //execute handles
        while (CURLM_OK === curl_multi_exec($curl, $running) && 0 < $running) ;

        //Collect response
        foreach ($res as $key => $item) {
            //Get response
            $res[$key]['res'] = (string)curl_multi_getcontent($item['curl']);
            //Remove handle
            curl_multi_remove_handle($curl, $item['curl']);
            //Remove curl resource
            unset($res[$key]['curl']);
        }

        //Close cURL handle
        curl_multi_close($curl);

        unset($curl, $item, $key);
        return $res;
    }

    /**
     * Get URL units
     *
     * @param string $url
     *
     * @return array
     */
    private function get_unit(string $url): array
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
     * Prepare parameter
     *
     * @param array $item
     */
    private function prep_param(array &$item): void
    {
        //Prepare Content-Type
        if (!isset($item['content_type'])) {
            $item['content_type'] = self::CONTENT_TYPE_JSON;
        }

        //Prepare request method
        if (!isset($item['method'])) {
            $item['method'] = isset($item['data']) || isset($item['file']) ? 'POST' : 'GET';
        }

        //Merge data & file
        if (isset($item['file'])) {
            $item['data'] = isset($item['data']) ? $item['data'] + $item['file'] : $item['file'];
        }

        unset($item['file']);
    }

    /**
     * Prepare header
     *
     * @param array $item
     */
    private function prep_header(array &$item): void
    {
        //Build header
        $header = ['Host' => $item['host'] . ':' . $item['port']];

        if (isset($item['header'])) {
            $header += $item['header'];
            unset($item['header']);
        }

        if (isset($item['Cookie'])) {
            $header += ['Cookie' => $item['Cookie']];
        }

        if (isset($item['ETag'])) {
            $header += ['If-None-Match' => $item['ETag']];
            unset($item['ETag']);
        }

        if (isset($item['Modified'])) {
            $header += ['If-Modified-Since' => $item['Modified']];
            unset($item['Modified']);
        }

        $header += [
            'Accept'          => $this->accept,
            'Accept-Charset'  => $this->accept_charset,
            'Accept-Encoding' => $this->accept_encoding,
            'Accept-Language' => $this->accept_language,
            'Content-Type'    => $item['content_type'],
            'User-Agent'      => $item['user_agent'] ?? $this->user_agent,
            'Connection'      => $this->connection
        ];

        //Reset values
        $item['user_agent']      = &$header['User-Agent'];
        $item['accept_encoding'] = &$header['Accept-Encoding'];

        //Build HTTP header
        $item['header'] = [$item['method'] . ' ' . $item['path'] . $item['query'] . ' ' . $this->http];

        foreach ($header as $key => $val) {
            $item['header'][] = $key . ': ' . $val;
        }

        unset($header, $key, $val);
    }

    /**
     * Prepare cURL
     *
     * @param array $item
     */
    private function prep_curl(array &$item): void
    {
        //Initialize
        $opt  = [];
        $curl = curl_init();

        //Build options
        $opt[CURLOPT_URL]            = $item['url'];
        $opt[CURLOPT_PORT]           = $item['port'];
        $opt[CURLOPT_TIMEOUT]        = 60;
        $opt[CURLOPT_NOSIGNAL]       = true;
        $opt[CURLOPT_AUTOREFERER]    = true;
        $opt[CURLOPT_COOKIESESSION]  = true;
        $opt[CURLOPT_RETURNTRANSFER] = true;
        $opt[CURLOPT_SSL_VERIFYHOST] = 2;
        $opt[CURLOPT_SSL_VERIFYPEER] = false;
        $opt[CURLOPT_HTTPHEADER]     = $item['header'];
        $opt[CURLOPT_ENCODING]       = $item['accept_encoding'];
        $opt[CURLOPT_USERAGENT]      = $item['user_agent'];
        $opt[CURLOPT_CUSTOMREQUEST]  = $item['method'];

        if ('POST' === $item['method']) {
            $opt[CURLOPT_POST] = true;
        }

        if (isset($item['Cookie'])) {
            $opt[CURLOPT_COOKIE] = &$item['Cookie'];
        }

        if (isset($item['with_body']) && !$item['with_body']) {
            $opt[CURLOPT_NOBODY] = true;
        }

        if (isset($item['with_header']) && $item['with_header']) {
            $opt[CURLOPT_HEADER] = true;
        }

        if (isset($item['referer'])) {
            $opt[CURLOPT_REFERER] = &$item['referer'];
        }

        if (isset($item['ssl_key'])) {
            $opt[CURLOPT_SSLKEY] = &$item['ssl_key'];
        }

        if (isset($item['ssl_cert'])) {
            $opt[CURLOPT_SSLCERT] = &$item['ssl_cert'];
        }

        if (isset($item['user_pwd'])) {
            $opt[CURLOPT_USERPWD] = &$item['user_pwd'];
        }

        if (isset($item['max_follow']) && 0 < $item['max_follow']) {
            $opt[CURLOPT_FOLLOWLOCATION] = true;
            $opt[CURLOPT_MAXREDIRS]      = &$item['max_follow'];
        }

        if (isset($item['proxy'])) {
            $opt[CURLOPT_PROXY] = &$item['proxy'];

            if (isset($item['proxy_user_pwd'])) {
                $opt[CURLOPT_PROXYUSERPWD] = &$item['proxy_user_pwd'];
            }
        }

        if (isset($item['data'])) {
            switch ($item['content_type']) {
                case self::CONTENT_TYPE_JSON:
                    $opt[CURLOPT_POSTFIELDS] = json_encode($item['data']);
                    break;

                case self::CONTENT_TYPE_XML:
                    $opt[CURLOPT_POSTFIELDS] = data::build_xml($item['data']);
                    break;

                case self::CONTENT_TYPE_ENCODED:
                    $opt[CURLOPT_POSTFIELDS] = http_build_query($item['data']);
                    break;

                default:
                    $opt[CURLOPT_POSTFIELDS] = $item['data'];
                    break;
            }
        }

        //Set cURL options
        curl_setopt_array($curl, $opt);

        //Clean item values
        foreach ($item as $key => $val) {
            if ('url' !== $key) {
                unset($item[$key]);
            }
        }

        //Add curl resource
        $item['curl'] = &$curl;
        unset($opt, $curl, $key, $val);
    }
}