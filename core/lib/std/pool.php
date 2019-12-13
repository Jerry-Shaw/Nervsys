<?php

/**
 * NS System Data Pooling controller
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core\lib\std;

/**
 * Class pool
 *
 * @package core\lib
 */
final class pool
{
    /**
     * IP
     *
     * @var string
     */
    public $ip = '';

    /**
     * CMD
     *
     * @var string
     */
    public $cmd = '';

    /**
     * Log
     *
     * @var string
     */
    public $log = '';

    /**
     * Return type (json/xml/io/none)
     *
     * @var string
     */
    public $ret = 'json';

    /**
     * Default conf
     *
     * @var array
     */
    public $conf = [
        'sys'  => [
            'timezone'  => 'UTC',
            'auto_call' => true
        ],
        'log'  => [
            'emergency' => true,
            'alert'     => true,
            'critical'  => true,
            'error'     => true,
            'warning'   => true,
            'notice'    => true,
            'info'      => true,
            'debug'     => true,
            'display'   => true,
            'save_path' => ROOT . DIRECTORY_SEPARATOR . 'logs'
        ],
        'cli'  => [],
        'cors' => [],
        'init' => [],
        'call' => []
    ];

    /**
     * Data
     *
     * @var array
     */
    public $data = [];

    /**
     * Error
     *
     * @var array
     */
    public $error = [];

    /**
     * Result
     *
     * @var array
     */
    public $result = [];

    /**
     * CLI mode
     *
     * @var bool
     */
    public $is_CLI = true;

    /**
     * TLS mode
     *
     * @var bool
     */
    public $is_TLS = true;

    /**
     * CGI command stack
     *
     * @var array
     */
    public $cgi_stack = [];

    /**
     * CLI command stack
     *
     * @var array
     */
    public $cli_stack = [];

    /**
     * CLI param pool
     *
     * @var array
     */
    public $cli_param = [];

    /**
     * Router stack
     *
     * @var array
     */
    public $router_stack = [];

    /**
     * Output handler
     *
     * @var array
     */
    public $output_handler = [];

    /**
     * pool constructor.
     */
    public function __construct()
    {
        //Skip in CLI mode
        if ($this->is_CLI = 'cli' === PHP_SAPI) {
            //Rewrite IP for CLI
            $this->ip = 'Local CLI';
            return;
        }

        //Get TLS mode
        $this->is_TLS = (isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'])
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']);

        //Build full IP records
        $ip_rec = isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? $_SERVER['HTTP_X_FORWARDED_FOR'] . ', ' . $_SERVER['REMOTE_ADDR']
            : $_SERVER['REMOTE_ADDR'];

        //Build IP list
        $ip_list = false !== strpos($ip_rec, ', ')
            ? explode(', ', $ip_rec)
            : [$ip_rec];

        //Get valid client IP
        foreach ($ip_list as $value) {
            if (is_string($addr = filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6))) {
                $this->ip = &$addr;
                break;
            }
        }

        unset($ip_rec, $ip_list, $value, $addr);
    }
}