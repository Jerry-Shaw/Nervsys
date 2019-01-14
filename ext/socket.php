<?php

/**
 * Socket Extension
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

use core\handler\factory;

class socket extends factory
{
    //Socket resource
    public $socket = null;

    /**
     * Network config
     *
     * ws://0.0.0.0:8080
     * tcp://127.0.0.1:6000
     * udp://0.0.0.0:6000
     * http://127.0.0.1:80
     * bcst://0.0.0.0:8000
     *
     * @var string
     */
    private $proto = 'tcp';//ws/tcp/udp/http/bcst
    private $host  = '0.0.0.0';
    private $port  = 65535;

    //Run as role
    private $run_as = 'server';

    //Runtime values
    private $type    = 'tcp';
    private $timeout = ['sec' => 60, 'usec' => 0];

    /**
     * socket constructor.
     *
     * @param string $run_as
     * @param string $address
     */
    public function __construct(string $run_as, string $address)
    {
        //Check ENV
        if (!parent::$is_cli) {
            exit('Socket only runs under CLI!');
        }

        //Parse address
        $parts = parse_url($address);

        $this->type = &$parts['scheme'];
        $this->host = &$parts['host'];
        $this->port = &$parts['port'];

        $this->run_as = 'bcst' === $parts['scheme'] ? 'client' : $run_as;

        if (in_array($parts['scheme'], ['ws', 'http'], true)) {
            $this->proto = 'tcp';
        } elseif ('bcst' === $parts['scheme']) {
            $this->proto = 'udp';
        } else {
            $this->proto = &$parts['scheme'];
        }

        unset($run_as, $address, $parts);
    }

    /**
     * Set timeout
     *
     * @param int $sec
     * @param int $usec
     *
     * @return $this
     */
    public function timeout(int $sec, int $usec = 0): object
    {
        $this->timeout = ['sec' => &$sec, 'usec' => &$usec];

        unset($sec, $usec);
        return $this;
    }

    /**
     * Create socket
     *
     * @param bool $block
     *
     * @return $this
     * @throws \Exception
     */
    public function create(bool $block = false): object
    {
        //Build domain & protocol
        $domain   = false !== filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? AF_INET : AF_INET6;
        $type     = 'udp' === $this->proto ? SOCK_DGRAM : SOCK_STREAM;
        $protocol = getprotobyname($this->proto);

        //Create socket
        $this->socket = socket_create($domain, $type, $protocol);

        if (false === $this->socket) {
            throw new \Exception(ucfirst($this->run_as) . ' create ERROR!', E_USER_ERROR);
        }

        //Set options
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, $this->timeout);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, $this->timeout);

        //Process run_as
        $this->{'process_' . $this->run_as}();

        //Set block
        $block ? socket_set_block($this->socket) : socket_set_nonblock($this->socket);

        unset($block, $domain, $type, $protocol);
        return $this;
    }

    /**
     * Process server
     *
     * @throws \Exception
     */
    private function process_server(): void
    {
        //Bind address
        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new \Exception('Bind failed: ' . socket_strerror(socket_last_error($this->socket)), E_USER_ERROR);
        }

        //Listen TCP
        if ('udp' !== $this->proto && !socket_listen($this->socket)) {
            throw new \Exception('Listen failed: ' . socket_strerror(socket_last_error($this->socket)), E_USER_ERROR);
        }
    }

    /**
     * Process server
     *
     * @throws \Exception
     */
    private function process_client(): void
    {
        //Broadcasting
        if ('bcst' === $this->type) {
            socket_set_option($this->socket, SOL_SOCKET, SO_BROADCAST, 1);
        }

        //Connect server
        if ('udp' !== $this->proto && !socket_connect($this->socket, $this->host, $this->port)) {
            throw new \Exception('Connect failed: ' . socket_strerror(socket_last_error($this->socket)), E_USER_ERROR);
        }
    }

    /**
     * Watch Connection
     *
     * @param array $read
     *
     * @return array
     */
    public function watch(array $read = []): array
    {
        $write  = $except = [];
        $read[] = $this->socket;

        $select = socket_select($read, $write, $except, $this->timeout['sec'], $this->timeout['usec']);

        if (false === $select) {
            $read = $write = $except = [];
        }

        unset($write, $except, $select);
        return $read;
    }

    /**
     * Accept Client
     *
     * @param array $read
     */
    public function accept(array &$read): void
    {
        if (false === $key = array_search($this->socket, $read, true)) {
            return;
        }

        $read[$key] = socket_accept($this->socket);
        unset($key);
    }

    /**
     * Read message
     *
     * @param     $socket
     * @param int $size
     * @param int $flags
     *
     * @return string
     */
    public function read($socket, int $size = 65536, int $flags = 0): string
    {
        'udp' === $this->proto
            ? socket_recvfrom($socket, $msg, $size, $flags, $from, $port)
            : socket_recv($socket, $msg, $size, $flags);

        unset($socket, $size, $flags);
        return trim((string)$msg);
    }

    /**
     * Send data
     *
     * @param        $socket
     * @param string $data
     * @param string $host
     * @param int    $port
     * @param int    $flags
     *
     * @return bool
     */
    public function send($socket, string $data, string $host = '', int $port = 0, int $flags = 0): bool
    {
        $data .= PHP_EOL;
        $size = strlen($data);

        $send = $size === (
            'udp' === $this->proto
                ? socket_sendto($socket, $data, $size, $flags, $host, $port)
                : socket_send($socket, $data, $size, $flags)
            );

        unset($socket, $data, $host, $port, $flags, $size);
        return $send;
    }

    /**
     * Close socket
     *
     * @param $socket
     */
    public function close($socket): void
    {
        socket_close($socket);
        unset($socket);
    }
}