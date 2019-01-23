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

    //Start Options
    private $run_as  = 'server';
    private $address = 'tcp://0.0.0.0:65535';

    //Runtime Options
    private $msg     = '';
    private $block   = 0;
    private $timeout = 60;
    private $options = [];

    /**
     * socket constructor.
     *
     * @param string $run_as server/client/broadcast
     */
    public function __construct(string $run_as)
    {
        $this->run_as = &$run_as;
        unset($run_as);
    }

    /**
     * Set timeout/timewait
     *
     * @param int $second timeout for server/client; timewait for broadcast
     *
     * @return $this
     */
    public function timeout(int $second): object
    {
        $this->timeout = &$second;

        unset($second);
        return $this;
    }

    /**
     * Bind to address
     *
     * @param string $address
     *
     * @return $this
     */
    public function bind(string $address): object
    {
        $this->address = &$address;

        unset($address);
        return $this;
    }

    /**
     * Set message for broadcast
     *
     * @param string $msg
     *
     * @return $this
     */
    public function msg(string $msg): object
    {
        $this->msg = &$msg;

        unset($msg);
        return $this;
    }

    /**
     * Set context options for server/client
     *
     * @param string $wrapper
     * @param array  $options
     *
     * @return $this
     */
    public function set(string $wrapper, array $options): object
    {
        $this->options[$wrapper] = &$options;

        unset($wrapper, $options);
        return $this;
    }

    /**
     * Create stream socket
     *
     * @param bool $block
     *
     * @return $this
     */
    public function create(bool $block = false): object
    {
        //Create server/client/broadcast
        $this->{'start_' . $this->run_as}($block);

        unset($block);
        return $this;
    }

    /**
     * Listen connections
     *
     * @param array $client
     *
     * @return array
     */
    public function listen(array $client = []): array
    {
        $write = $except = [];

        $client[hash('sha1', uniqid(mt_rand(), true))] = $this->source;

        $select = stream_select($client, $write, $except, $this->timeout);

        if (false === $select) {
            $client = $write = $except = [];
        }

        unset($write, $except, $select);
        return $client;
    }

    /**
     * Accept new client
     *
     * @param array $read
     * @param array $clients
     */
    public function accept(array &$read, array &$clients): void
    {
        if (false !== $key = array_search($this->source, $read, true)) {
            $clients[$key] = stream_socket_accept($this->source);
            stream_set_blocking($clients[$key], $this->block);
            unset($read[$key]);
        }

        unset($key);
    }

    /**
     * Read message
     *
     * @param        $stream
     * @param int    $size
     * @param int    $flags
     * @param string $address
     *
     * @return string
     */
    public function read($stream, int $size = 65535, int $flags = 0, string &$address = ''): string
    {
        $data = stream_socket_recvfrom($stream, $size, $flags, $address);

        unset($stream, $size, $flags);
        return $data;
    }

    /**
     * Send message
     *
     * @param        $stream
     * @param string $data
     * @param int    $flags
     * @param string $address
     *
     * @return int
     */
    public function send($stream, string $data, int $flags = 0, string $address = ''): int
    {
        $send = stream_socket_sendto($stream, $data, $flags, $address);

        unset($stream, $data, $flags, $address);
        return $send;
    }

    /**
     * Close socket
     *
     * @param $stream
     */
    public function close($stream): void
    {
        stream_socket_shutdown($stream, STREAM_SHUT_RDWR);;
        unset($stream);
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
        //WebSocket key name & key mask
        $key_name = 'Sec-WebSocket-Key';
        $key_mask = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

        //Get key position
        if (false === $key_pos = strpos($header, $key_name)) {
            return '';
        }

        //Move key position
        $key_pos += strlen($key_name) + 2;

        //Get WebSocket key & rehash
        $key = substr($header, $key_pos, strpos($header, "\r\n", $key_pos) - $key_pos);
        $key = hash('sha1', $key . $key_mask, true);

        //Generate response
        $response = 'HTTP/1.1 101 Switching Protocols' . "\r\n"
            . 'Upgrade: websocket' . "\r\n"
            . 'Connection: Upgrade' . "\r\n"
            . 'Sec-WebSocket-Accept: ' . base64_encode($key) . "\r\n\r\n";

        unset($header, $key_name, $key_mask, $key_pos, $key);
        return $response;
    }

    /**
     * Decode WebSocket message
     *
     * @param string $buff
     *
     * @return string
     */
    public function ws_decode(string $buff): string
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
    public function ws_encode(string $msg): string
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
     * Start server
     *
     * @param bool $block
     *
     * @throws \Exception
     */
    private function start_server(bool $block): void
    {
        //Create context
        $context = stream_context_create($this->options);

        //Get flags
        $flags = false === strpos($this->address, 'udp://')
            ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
            : STREAM_SERVER_BIND;

        //Create server
        $this->source = stream_socket_server($this->address, $errno, $errstr, $flags, $context);

        //Check error
        if (0 < $errno) {
            throw new \Exception($errstr, E_USER_ERROR);
        }

        //Set block mode
        $this->block = !$block ? 0 : 1;
        stream_set_blocking($this->source, $this->block);

        unset($block, $context, $flags, $errno, $errstr);
    }

    /**
     * Start Client
     *
     * @param bool $block
     *
     * @throws \Exception
     */
    private function start_client(bool $block): void
    {
        //Create context
        $context = stream_context_create($this->options);

        //Get flags
        $flags = false === strpos($this->address, 'udp://')
            ? STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT
            : STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT;

        //Create client
        $this->source = stream_socket_client($this->address, $errno, $errstr, $this->timeout, $flags, $context);

        //Check error
        if (0 < $errno) {
            throw new \Exception($errstr, E_USER_ERROR);
        }

        //Set block mode
        $this->block = !$block ? 0 : 1;
        stream_set_blocking($this->source, $this->block);

        unset($block, $context, $flags, $errno, $errstr);
    }

    /**
     * Start broadcast
     *
     * @param bool $block
     *
     * @throws \Exception
     */
    private function start_broadcast(bool $block): void
    {
        //Parse address
        $unit = parse_url($this->address);

        //Check host & port
        if (!isset($unit['host']) || !isset($unit['port'])) {
            throw new \Exception('Broadcast address ERROR!', E_USER_ERROR);
        }

        //Create socket
        if (false === $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)) {
            throw new \Exception('Broadcast ERROR!', E_USER_ERROR);
        }

        //Set options
        socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

        //Set block mode
        $block ? socket_set_block($socket) : socket_set_nonblock($socket);

        //Broadcast message
        while (true) {
            socket_sendto($socket, $this->msg, strlen($this->msg), 0, $unit['host'], $unit['port']);
            sleep($this->timeout > 0 ? $this->timeout : 5);
        }

        socket_close($socket);

        unset($block, $unit, $socket);
    }
}