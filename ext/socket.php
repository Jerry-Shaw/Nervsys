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
    public $sock = null;

    //Timeout (in seconds)
    public $timeout = 60;

    //Socket type
    private $type = '';

    /**
     * Get Socket param data
     *
     * @param string $proto
     * @param string $host
     *
     * @return array
     */
    private function param(string $proto, string $host): array
    {
        $param = [];

        $param['domain']   = false === strpos($host, ':') ? AF_INET : AF_INET6;
        $param['type']     = 'udp' === $proto ? SOCK_DGRAM : SOCK_STREAM;
        $param['protocol'] = getprotobyname($proto);

        $this->type = &$proto;

        unset($proto, $host);
        return $param;
    }

    /**
     * Create Server
     *
     * @param string $proto
     * @param string $host
     * @param int    $port
     * @param bool   $block
     *
     * @throws \Exception
     */
    public function server(string $proto, string $host, int $port, bool $block = false): void
    {
        $param  = $this->param($proto, $host);
        $socket = socket_create($param['domain'], $param['type'], $param['protocol']);

        if (false === $socket) {
            throw new \Exception('Server ERROR!', E_USER_ERROR);
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        if (!socket_bind($socket, $host, $port)) {
            throw new \Exception('Bind failed: ' . socket_strerror(socket_last_error($socket)), E_USER_ERROR);
        }

        if ('udp' !== $proto && !socket_listen($socket)) {
            throw new \Exception('Listen failed: ' . socket_strerror(socket_last_error($socket)), E_USER_ERROR);
        }

        $block ? socket_set_block($socket) : socket_set_nonblock($socket);

        $this->sock = &$socket;

        unset($proto, $host, $port, $block, $param, $socket);
    }

    /**
     * Create Client
     *
     * @param string $proto
     * @param string $host
     * @param int    $port
     * @param bool   $block
     * @param bool   $broadcast
     *
     * @throws \Exception
     */
    public function client(string $proto, string $host, int $port, bool $block = false, bool $broadcast = false): void
    {
        $param  = $this->param($proto, $host);
        $socket = socket_create($param['domain'], $param['type'], $param['protocol']);

        if (false === $socket) {
            throw new \Exception('Client ERROR!', E_USER_ERROR);
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        if ($broadcast) {
            socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        }

        if ('udp' !== $proto && !socket_connect($socket, $host, $port)) {
            throw new \Exception('Connect failed: ' . socket_strerror(socket_last_error($socket)), E_USER_ERROR);
        }

        $block ? socket_set_block($socket) : socket_set_nonblock($socket);

        $this->sock = &$socket;

        unset($proto, $host, $port, $block, $broadcast, $param, $socket);
    }

    /**
     * Watch Connection
     *
     * @param array    $read
     * @param array    $write
     * @param int|null $timeout
     *
     * @return int
     */
    public function watch(array &$read, array &$write, int $timeout = null): int
    {
        $except = [];
        $read[] = $this->sock;

        $watch = socket_select($read, $write, $except, $timeout);

        if (false === $watch) {
            $read = $write = [];
        }

        unset($timeout, $except);
        return (int)$watch;
    }

    /**
     * Accept Client
     *
     * @param $client
     *
     * @return bool
     */
    public function accept(&$client): bool
    {
        $client = socket_accept($this->sock);

        return false !== $client;
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
    public function read($socket, int $size = 4096, int $flags = 0): string
    {
        'udp' === $this->type
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

        $send = 'udp' === $this->type
            ? socket_sendto($socket, $data, $size, $flags, $host, $port)
            : socket_send($socket, $data, $size, $flags);

        unset($socket, $data, $host, $port, $flags);
        return $size === $send;
    }

    /**
     * Close socket
     *
     * @param $socket
     */
    public function close($socket): void
    {
        socket_close($socket);

        unset($socket, $exist);
    }
}