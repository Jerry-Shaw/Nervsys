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
    public $proto = 'tcp';
    public $host  = '0.0.0.0';
    public $port  = 65535;

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
        $domain   = false !== filter_var($this->host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? AF_INET6 : AF_INET;
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
     * Listen connections
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
     * Accept new client
     *
     * @param array $clients
     *
     * @return array
     */
    public function accept(array &$clients): array
    {
        $list = $clients;

        if (false === $key = array_search($this->source, $clients, true)) {
            return $list;
        }

        $list[$key] = socket_accept($this->source);

        unset($clients[$key], $key);
        return $list;
    }

    /**
     * Read message
     *
     * @param        $socket
     * @param string $msg
     * @param int    $size
     * @param string $from
     * @param int    $port
     * @param int    $flags
     *
     * @return int
     */
    public function read($socket, string &$msg, int $size = 65535, string $from = '', int $port = 0, int $flags = 0): int
    {
        $result = 'udp' === $this->proto
            ? socket_recvfrom($socket, $msg, $size, $flags, $from, $port)
            : socket_recv($socket, $msg, $size, $flags);

        if (false === $result) {
            $msg    = '';
            $result = -1;
        }

        unset($socket, $size, $flags);
        return $result;
    }

    /**
     * Send message
     *
     * @param        $socket
     * @param string $msg
     * @param string $host
     * @param int    $port
     * @param int    $flags
     *
     * @return bool
     */
    public function send($socket, string $msg, string $host = '', int $port = 0, int $flags = 0): bool
    {
        $size = strlen($msg);
        $send = 'udp' === $this->proto
            ? socket_sendto($socket, $msg, $size, $flags, $host, $port)
            : socket_send($socket, $msg, $size, $flags);

        $result = $size === $send;

        unset($socket, $msg, $host, $port, $flags, $size, $send);
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
     * Get basic WebSocket header
     *
     * @param string $buff
     *
     * @return array
     */
    public function ws_get_codes(string $buff): array
    {
        $data = [];
        $char = $buff[0];

        //Get FIN & OpCode
        $data['final']  = ord($char) & 0x80;
        $data['opcode'] = ord($char) & 0x0F;

        unset($buff, $char);
        return $data;
    }

    /**
     * Generate handshake response for WebSocket
     *
     * @param string $header
     *
     * @return string
     */
    public function ws_handshake(string $header): string
    {
        //WebSocket key name
        $key_name = 'Sec-WebSocket-Key';

        //Support Sec-WebSocket-Version: 13
        $key_hash = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

        //Get key position
        if (false === $key_pos = strpos($header, $key_name)) {
            return '';
        }

        //Move key position
        $key_pos += strlen($key_name) + 2;

        //Get WebSocket key & rehash
        $key = substr($header, $key_pos, strpos($header, "\r\n", $key_pos) - $key_pos);
        $key = hash('sha1', $key . $key_hash, true);

        //Generate response
        $response = 'HTTP/1.1 101 Switching Protocols' . "\r\n"
            . 'Upgrade: websocket' . "\r\n"
            . 'Connection: Upgrade' . "\r\n"
            . 'Sec-WebSocket-Accept: ' . base64_encode($key) . "\r\n\r\n";

        unset($header, $key_name, $key_hash, $key_pos, $key);
        return $response;
    }

    /**
     * Decode WebSocket message
     *
     * @param string $buff
     *
     * @return string
     */
    function ws_decode(string $buff): string
    {
        switch (ord($buff[1]) & 0x7F) {
            case 126:
                $mask = substr($buff, 4, 4);
                $data = substr($buff, 8);
                break;

            case 127:
                $mask = substr($buff, 10, 4);
                $data = substr($buff, 14);
                break;

            default:
                $mask = substr($buff, 2, 4);
                $data = substr($buff, 6);
                break;
        }

        $msg = '';
        $len = strlen($data);

        for ($i = 0; $i < $len; ++$i) {
            $msg .= $data[$i] ^ $mask[$i % 4];
        }

        unset($buff, $mask, $data, $len, $i);
        return $msg;
    }

    /**
     * Encode WebSocket message
     *
     * @param string $msg
     *
     * @return string
     */
    function ws_encode(string $msg): string
    {
        $buff = '';
        $seg  = str_split($msg, 125);

        foreach ($seg as $val) {
            $buff .= chr(0x81) . chr(strlen($val)) . $val;
        }

        unset($msg, $seg, $val);
        return $buff;
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