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

use core\parser\data;

use core\handler\factory;

class http extends factory
{
    //URL key
    private $key = 0;

    //URL list
    private $url = [];

    //Default values
    const HTTP            = 'HTTP/2.0';                                               //HTTP Version
    const ACCEPT          = 'text/plain,text/html,text/xml,application/json,*;q=0';   //Accept types
    const CONNECTION      = 'keep-alive';                                             //Connection type
    const USER_AGENT      = 'Mozilla/5.0 (Compatible; NervSys/' . VER . ')';          //User Agent string
    const ACCEPT_CHARSET  = 'UTF-8,*;q=0';                                            //Accept charset
    const ACCEPT_ENCODING = 'gzip,deflate,identity,*;q=0';                            //Accept encoding
    const ACCEPT_LANGUAGE = 'en-US,en,zh-CN,zh,*;q=0';                                //Accept language

    //Pre-defined content types
    const CONTENT_TYPE_XML     = 'application/xml; charset=UTF-8';
    const CONTENT_TYPE_JSON    = 'application/json; charset=UTF-8';
    const CONTENT_TYPE_DATA    = 'multipart/form-data';
    const CONTENT_TYPE_ENCODED = 'application/x-www-form-urlencoded';

    /**
     * http constructor.
     *
     * @param string $api
     */
    public function __construct(string $api = '')
    {
        if ('' !== $api) {
            $this->url[$this->key]['url'] = &$api;
        }

        unset($api);
    }

    /**
     * Add URL
     *
     * @param string $url
     *
     * @return object
     */
    public function url(string $url): object
    {
        if (!empty($this->url)) {
            ++$this->key;
        }

        $this->url[$this->key]['url'] = &$url;

        unset($url);
        return $this;
    }

    /**
     * Add data
     *
     * @param array $data
     *
     * @return object
     */
    public function data(array $data): object
    {
        $this->url[$this->key]['data'] = &$data;

        unset($data);
        return $this;
    }

    /**
     * Add file
     *
     * @param string $key
     * @param string $file
     *
     * @return object
     */
    public function file(string $key, string $file): object
    {
        //Create cURL files
        if (file_exists($file)) {
            $this->url[$this->key]['file'][$key] = new \CURLFile($file);
        }

        unset($key, $file);
        return $this;
    }

    /**
     * Set method
     *
     * @param string $method
     *
     * @return object
     */
    public function method(string $method): object
    {
        $method = strtoupper($method);

        if (in_array($method, ['GET', 'POST', 'HEAD', 'PUT', 'DELETE', 'OPTIONS', 'CONNECT', 'TRACE', 'PATCH'], true)) {
            $this->url[$this->key]['method'] = &$method;
        }

        unset($method);
        return $this;
    }

    /**
     * Set header
     *
     * @param array $header
     *
     * @return object
     */
    public function header(array $header): object
    {
        $this->url[$this->key]['header'] = &$header;

        unset($header);
        return $this;
    }

    /**
     * Set Cookie
     *
     * @param string $cookie
     *
     * @return object
     */
    public function cookie(string $cookie): object
    {
        $this->url[$this->key]['Cookie'] = &$cookie;

        unset($cookie);
        return $this;
    }

    /**
     * Set referer
     *
     * @param string $referer
     *
     * @return object
     */
    public function referer(string $referer): object
    {
        $this->url[$this->key]['referer'] = &$referer;

        unset($referer);
        return $this;
    }

    /**
     * Set HTTP ETag
     *
     * @param string $ETag
     *
     * @return object
     */
    public function http_ETag(string $ETag): object
    {
        $this->url[$this->key]['ETag'] = &$ETag;

        unset($ETag);
        return $this;
    }

    /**
     * Set HTTP last modified
     *
     * @param string $Modified
     *
     * @return object
     */
    public function http_Modified(string $Modified): object
    {
        $this->url[$this->key]['Modified'] = &$Modified;

        unset($Modified);
        return $this;
    }

    /**
     * Set SSL key path
     *
     * @param string $key_path
     *
     * @return object
     */
    public function ssl_key(string $key_path): object
    {
        $this->url[$this->key]['ssl_key'] = &$key_path;

        unset($key_path);
        return $this;
    }

    /**
     * Set SSL cert path
     *
     * @param string $cert_path
     *
     * @return object
     */
    public function ssl_cert(string $cert_path): object
    {
        $this->url[$this->key]['ssl_cert'] = &$cert_path;

        unset($cert_path);
        return $this;
    }

    /**
     * Set username:password
     *
     * @param string $username
     * @param string $password
     *
     * @return object
     */
    public function user_pwd(string $username, string $password): object
    {
        $this->url[$this->key]['user_pwd'] = $username . ':' . $password;

        unset($username, $password);
        return $this;
    }

    /**
     * Set with body option
     *
     * @param bool $with_body
     *
     * @return object
     */
    public function with_body(bool $with_body): object
    {
        $this->url[$this->key]['with_body'] = &$with_body;

        unset($with_body);
        return $this;
    }

    /**
     * Set with header option
     *
     * @param bool $with_header
     *
     * @return object
     */
    public function with_header(bool $with_header): object
    {
        $this->url[$this->key]['with_header'] = &$with_header;

        unset($with_header);
        return $this;
    }

    /**
     * Set max follow
     *
     * @param int $max_follow
     *
     * @return object
     */
    public function max_follow(int $max_follow): object
    {
        $this->url[$this->key]['max_follow'] = &$max_follow;

        unset($max_follow);
        return $this;
    }

    /**
     * Set User-Agent
     *
     * @param string $user_agent
     *
     * @return object
     */
    public function user_agent(string $user_agent): object
    {
        $this->url[$this->key]['user_agent'] = &$user_agent;

        unset($user_agent);
        return $this;
    }

    /**
     * Set Content-Type
     *
     * @param string $content_type
     *
     * @return object
     */
    public function content_type(string $content_type): object
    {
        $this->url[$this->key]['content_type'] = &$content_type;

        unset($content_type);
        return $this;
    }

    /**
     * Fetch an URL
     *
     * @return string
     * @throws \Exception
     */
    public function fetch(): string
    {
        if (empty($this->url)) {
            throw new \Exception('No URL found! At least one URL is required to process cURL.', E_USER_NOTICE);
        }

        $item = reset($this->url);

        $unit = $this->get_unit($item['url']);

        if (empty($unit)) {
            throw new \Exception('URL [' . $item['url'] . '] error! "SCHEME" and "HOST" MUST be contained.', E_USER_WARNING);
        }

        $item += $unit;

        unset($unit);

        $this->prep_params($item);
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
        if (empty($this->url)) {
            throw new \Exception('No URL found! At least one URL is required to process cURL.', E_USER_NOTICE);
        }

        //Multi CURL
        $res  = [];
        $curl = curl_multi_init();

        //Add CURL
        foreach ($this->url as $item) {
            $unit = $this->get_unit($item['url']);

            if (empty($unit)) {
                continue;
            }

            $item += $unit;

            unset($unit);

            $this->prep_params($item);
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
    private function prep_params(array &$item): void
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
            'Accept'          => self::ACCEPT,
            'Accept-Charset'  => self::ACCEPT_CHARSET,
            'Accept-Encoding' => self::ACCEPT_ENCODING,
            'Accept-Language' => self::ACCEPT_LANGUAGE,
            'Content-Type'    => $item['content_type'],
            'User-Agent'      => $item['user_agent'] ?? self::USER_AGENT,
            'Connection'      => self::CONNECTION
        ];

        //Reset values
        $item['user_agent']      = &$header['User-Agent'];
        $item['accept_encoding'] = &$header['Accept-Encoding'];

        //Build HTTP header
        $item['header'] = [$item['method'] . ' ' . $item['path'] . $item['query'] . ' ' . self::HTTP];

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