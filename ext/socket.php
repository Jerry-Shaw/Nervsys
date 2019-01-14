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
    //Socket source
    public $source = null;

    //Network config
    private $proto = 'tcp';
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
     *
     * server, ws://0.0.0.0:8080
     * client, tcp://127.0.0.1:6000
     * client, udp://0.0.0.0:6000
     * server, http://127.0.0.1:80
     * server, bcst://0.0.0.0:8000
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
        $this->source = socket_create($domain, $type, $protocol);

        if (false === $this->source) {
            throw new \Exception(ucfirst($this->run_as) . ' create ERROR!', E_USER_ERROR);
        }

        //Set options
        socket_set_option($this->source, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($this->source, SOL_SOCKET, SO_SNDTIMEO, $this->timeout);
        socket_set_option($this->source, SOL_SOCKET, SO_RCVTIMEO, $this->timeout);

        //Process run_as
        $this->{'process_' . $this->run_as}();

        //Set block
        $block ? socket_set_block($this->source) : socket_set_nonblock($this->source);

        unset($block, $domain, $type, $protocol);
        return $this;
    }

    /**
     * Listen Connections
     *
     * @param array $clients
     *
     * @return array
     */
    public function listen(array $clients = []): array
    {
        $write     = $except = [];
        $clients[] = $this->source;

        $select = socket_select($clients, $write, $except, $this->timeout['sec'], $this->timeout['usec']);

        if (false === $select) {
            $clients = $write = $except = [];
        }

        unset($write, $except, $select);
        return $clients;
    }

    /**
     * Accept Client
     *
     * @param array $clients
     */
    public function accept(array &$clients): void
    {
        if (false === $key = array_search($this->source, $clients, true)) {
            return;
        }

        $clients[$key] = socket_accept($this->source);
        unset($key);
    }

    /**
     * Read message
     *
     * @param        $socket
     * @param int    $size
     * @param string $from
     * @param int    $port
     * @param int    $flags
     *
     * @return string
     */
    public function read($socket, int $size = 65535, string $from = '', int $port = 0, int $flags = 0): string
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
        $size = strlen($data);
        $send = 'udp' === $this->proto
            ? socket_sendto($socket, $data, $size, $flags, $host, $port)
            : socket_send($socket, $data, $size, $flags);

        $result = $size === $send;

        unset($socket, $data, $host, $port, $flags, $size, $send);
        return $result;
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

    /**
     * Process server
     *
     * @throws \Exception
     */
    private function process_server(): void
    {
        //Bind address
        if (!socket_bind($this->source, $this->host, $this->port)) {
            throw new \Exception('Bind failed: ' . socket_strerror(socket_last_error($this->source)), E_USER_ERROR);
        }

        //Listen TCP
        if ('udp' !== $this->proto && !socket_listen($this->source)) {
            throw new \Exception('Listen failed: ' . socket_strerror(socket_last_error($this->source)), E_USER_ERROR);
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
            socket_set_option($this->source, SOL_SOCKET, SO_BROADCAST, 1);
        }

        //Connect server
        if ('udp' !== $this->proto && !socket_connect($this->source, $this->host, $this->port)) {
            throw new \Exception('Connect failed: ' . socket_strerror(socket_last_error($this->source)), E_USER_ERROR);
        }
    }
}