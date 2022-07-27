<?php

/**
 * Socket Manager library
 *
 * Copyright 2016-2022 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2022 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Core\Mgr;

use Nervsys\Core\Factory;

class SocketMgr extends Factory
{
    public OSMgr    $OSMgr;
    public FiberMgr $fiberMgr;

    public int $wait_timeout   = 3;       // seconds
    public int $alive_timeout  = 30;      // seconds
    public int $select_timeout = 200000;  // microseconds

    public bool $set_block    = false;
    public bool $debug_mode   = false;
    public bool $is_websocket = false;

    public string $main_id = '';

    public array $socket_main     = [];
    public array $socket_reads    = [];
    public array $socket_clients  = [];
    public array $socket_actives  = [];
    public array $context_options = [];

    private array $event_fn = [
        'onAccept'      => null,
        'onWsHandshake' => null,
        'onHeartbeat'   => null,
        'onMessage'     => null,
        'onSend'        => null,
        'onClose'       => null
    ];

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->OSMgr    = OSMgr::new();
        $this->fiberMgr = FiberMgr::new();
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function onConnect(callable $callable): self
    {
        $this->event_fn[__FUNCTION__] = &$callable;

        unset($callable);
        return $this;
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function onWsHandshake(callable $callable): self
    {
        $this->event_fn[__FUNCTION__] = &$callable;

        unset($callable);
        return $this;
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function onHeartbeat(callable $callable): self
    {
        $this->event_fn[__FUNCTION__] = &$callable;

        unset($callable);
        return $this;
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function onMessage(callable $callable): self
    {
        $this->event_fn[__FUNCTION__] = &$callable;

        unset($callable);
        return $this;
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function onSend(callable $callable): self
    {
        $this->event_fn[__FUNCTION__] = &$callable;

        unset($callable);
        return $this;
    }

    /**
     * @param callable $callable
     *
     * @return $this
     */
    public function onClose(callable $callable): self
    {
        $this->event_fn[__FUNCTION__] = &$callable;

        unset($callable);
        return $this;
    }

    /**
     * @param string $wrapper
     * @param array  $options
     *
     * @return $this
     */
    public function setContextOptions(string $wrapper, array $options): self
    {
        $this->context_options[$wrapper] = &$options;

        unset($wrapper, $options);
        return $this;
    }

    /**
     * @param int $seconds
     *
     * @return $this
     */
    public function setAliveTimeout(int $seconds): self
    {
        $this->alive_timeout = &$seconds;

        unset($seconds);
        return $this;
    }

    /**
     * @param int $seconds
     *
     * @return $this
     */
    public function setStreamTimeout(int $seconds): self
    {
        $this->wait_timeout = &$seconds;

        unset($seconds);
        return $this;
    }

    /**
     * @param int $microseconds
     *
     * @return $this
     */
    public function setSelectTimeout(int $microseconds): self
    {
        $this->select_timeout = &$microseconds;

        unset($microseconds);
        return $this;
    }

    /**
     * @param bool $debug
     *
     * @return $this
     */
    public function setDebugMode(bool $debug): self
    {
        $this->debug_mode = &$debug;

        unset($debug);
        return $this;
    }

    /**
     * @return $this
     */
    public function setAsWebsocket(): self
    {
        $this->is_websocket = true;

        return $this;
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function listen(): void
    {
        $write = $except = [];

        while (true) {
            try {
                $this->fiberMgr->async($this->fiberMgr->await([$this, 'heartbeat']));

                $clients = $this->socket_main + $this->socket_clients;
                $changes = (int)stream_select($clients, $write, $except, 0, $this->select_timeout);

                if (0 < $changes) {
                    if (isset($clients[$this->main_id])) {
                        --$changes;
                        unset($clients[$this->main_id]);
                        $this->fiberMgr->async($this->fiberMgr->await([$this, 'accept']));
                    }

                    if (!empty($clients)) {
                        $this->socket_reads += $clients;
                        $this->consoleLog(__FUNCTION__, $changes . ' clients to read.');
                    }
                }

                if (is_callable($this->event_fn['onSend'])) {
                    $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onSend']));
                }
            } catch (\Throwable $throwable) {
                $this->consoleLog('ERROR', $throwable->getMessage());
                unset($throwable);
            }

            unset($clients, $changes);
            \Fiber::suspend();
        }
    }

    /**
     * @return void
     */
    public function accept(): void
    {
        try {
            $accept = stream_socket_accept($this->socket_main[$this->main_id], 0);

            if (false === $accept) {
                throw new \Exception('Connection failed!', E_USER_NOTICE);
            }

            $socket_id = $this->getSocketId();

            stream_set_timeout($accept, $this->wait_timeout);
            stream_set_blocking($accept, $this->set_block);

            $this->socket_actives[$socket_id] = time();
            $this->socket_clients[$socket_id] = &$accept;

            if (is_callable($this->event_fn['onAccept'])) {
                $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onAccept'], [$socket_id]));
            }

            $this->consoleLog(__FUNCTION__, $socket_id . ': Connected! ' . count($this->socket_clients) . ' online.');
        } catch (\Throwable $throwable) {
            $this->consoleLog('ERROR', $throwable->getMessage());
            unset($throwable, $accept, $socket_id);
        }
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function read(): void
    {
        while (!empty($this->socket_reads)) {
            foreach ($this->socket_reads as $socket_id => $client) {
                try {
                    $message = fgets($client, 2);

                    if (false === $message) {
                        $this->consoleLog(__FUNCTION__, $socket_id . ': Read ERROR!');
                        throw new \Exception($socket_id . ': Read ERROR!', E_USER_NOTICE);
                    }

                    while ('' !== ($buff = fread($client, 4096))) {
                        $message .= $buff;
                    }

                    $message = trim($message);

                    unset($this->socket_reads[$socket_id]);
                    $this->socket_actives[$socket_id] = time();

                    if (is_callable($this->event_fn['onMessage'])) {
                        $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onMessage'], [$socket_id, $message]));
                    }
                } catch (\Throwable $throwable) {
                    $this->consoleLog('ERROR', $throwable->getMessage());
                    $this->close($socket_id);
                    unset($throwable);
                }
            }
        }

        \Fiber::suspend();
        $this->read();
    }

    /**
     * @param string $message
     * @param array  $socket_ids
     *
     * @return void
     */
    public function send(string $message, array $socket_ids = []): void
    {
        $clients = $this->socket_main + $this->socket_clients;

        if (!empty($socket_ids)) {
            $clients = array_intersect_key($clients, array_flip($socket_ids));
        }

        foreach ($clients as $socket_id => $client) {
            try {
                if (false === fwrite($client, $message)) {
                    $this->consoleLog(__FUNCTION__, $socket_id . ': Send ERROR!');
                    throw new \Exception($socket_id . ': Send ERROR!', E_USER_NOTICE);
                }

                $this->consoleLog(__FUNCTION__, $socket_id . ': ' . $message);
            } catch (\Throwable $throwable) {
                $this->consoleLog('ERROR', $throwable->getMessage());
                $this->close($socket_id);
                unset($throwable);
            }
        }

        unset($message, $socket_ids, $clients, $socket_id, $client);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        $now_time = time();
        $max_wait = $this->alive_timeout * 2;

        foreach ($this->socket_actives as $socket_id => $active_time) {
            try {
                $idle_time = $now_time - $active_time;

                if ($idle_time < $this->alive_timeout) {
                    continue;
                }

                if ($idle_time > $max_wait) {
                    $this->consoleLog(__FUNCTION__, $socket_id . ': Lost heartbeat connection!');
                    $this->close($socket_id);
                    continue;
                }

                if (is_callable($this->event_fn['onHeartbeat'])) {
                    $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onHeartbeat'], [$socket_id]));
                }
            } catch (\Throwable $throwable) {
                $this->consoleLog('ERROR', $throwable->getMessage());
                unset($throwable);
            }
        }

        unset($now_time, $max_wait, $socket_id, $active_time, $idle_time);
    }

    /**
     * @param string $action
     * @param string $message
     *
     * @return void
     */
    private function consoleLog(string $action, string $message): void
    {
        if ($this->debug_mode) {
            echo '[' . date('Y-m-d H:i:s') . ']: '
                . str_pad(ucfirst($action), 12)
                . strtr($message, ["\r" => '\\r', "\n" => '\\n'])
                . "\n";
        }

        unset($action, $message);
    }

    /**
     * @return string
     */
    private function getSocketId(): string
    {
        $socket_id = hash('md5', uniqid(getmypid() . mt_rand(), true));
        return !isset($this->socket_clients[$socket_id]) ? $socket_id : $this->getSocketId();
    }

    /**
     * @param string $socket_id
     *
     * @return void
     */
    public function close(string $socket_id): void
    {
        try {
            if (is_callable($this->event_fn['onClose'])) {
                $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onClose'], [$socket_id]));
            }

            fclose($this->socket_clients[$socket_id]);
            $this->consoleLog(__FUNCTION__, $socket_id);
        } catch (\Throwable $throwable) {
            $this->consoleLog('ERROR', $throwable->getMessage());
            unset($throwable);
        }

        unset($this->socket_reads[$socket_id], $this->socket_clients[$socket_id], $this->socket_actives[$socket_id], $socket_id);
    }

    /**
     * @param string $address
     *
     * @return $this
     * @throws \Exception
     */
    public function serverOn(string $address): self
    {
        $context = stream_context_create();

        if (!empty($this->context_options)) {
            stream_context_set_params($context, $this->context_options);
        }

        $scheme = parse_url($address, PHP_URL_SCHEME);

        $server = stream_socket_server(
            $address,
            $errno,
            $errstr,
            'udp' != $scheme ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN : STREAM_SERVER_BIND,
            $context
        );

        if (false === $server) {
            $this->consoleLog('ERROR', $errno . ': ' . $errstr);
            throw new \Exception('Server failed to start!', E_USER_ERROR);
        }

        stream_set_timeout($server, $this->wait_timeout);

        $this->main_id     = $this->getSocketId();
        $this->socket_main = [$this->main_id => &$server];

        $this->consoleLog(__FUNCTION__, $address);

        unset($address, $context, $scheme, $server, $errno, $errstr);
        return $this;
    }

    /**
     * @param string $address
     *
     * @return $this
     * @throws \Exception
     */
    public function clientOn(string $address): self
    {
        $context = stream_context_create();

        if (!empty($this->context_options)) {
            stream_context_set_params($context, $this->context_options);
        }

        $scheme = parse_url($address, PHP_URL_SCHEME);

        $client = stream_socket_client(
            $address,
            $errno,
            $errstr,
            $this->wait_timeout,
            'udp' != $scheme ? STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT : STREAM_CLIENT_CONNECT,
            $context
        );

        if (false === $client) {
            $this->consoleLog('ERROR', $errno . ': ' . $errstr);
            throw new \Exception('Client failed to start!', E_USER_ERROR);
        }

        stream_set_timeout($client, $this->wait_timeout);

        $this->main_id     = $this->getSocketId();
        $this->socket_main = [$this->main_id => &$client];

        $this->consoleLog(__FUNCTION__, $address);

        unset($address, $context, $scheme, $client, $errno, $errstr);
        return $this;
    }

    /**
     * WebSocket: Get header codes (fin, opcode, mask)
     *
     * @param string $buff
     *
     * @return int[]
     */
    public function wsGetCodes(string $buff): array
    {
        $char = ord($buff[0]);

        $code = [
            'fin'    => $char >> 7,
            'opcode' => $char & 0x0F,
            'mask'   => ord($buff[1]) >> 7
        ];

        unset($buff, $char);
        return $code;
    }

    /**
     * WebSocket: Get Sec-WebSocket-Key
     *
     * @param string $header
     *
     * @return string
     */
    public function wsGetKey(string $header): string
    {
        //Validate Sec-WebSocket-Key
        $key_pos = strpos($header, 'Sec-WebSocket-Key');

        if (false === $key_pos) {
            unset($header, $key_pos);
            return '';
        }

        //Get WebSocket key & rehash
        $key_pos += 19;
        $key_val = substr($header, $key_pos, strpos($header, "\r\n", $key_pos) - $key_pos);
        $key_val = base64_encode(hash('sha1', $key_val . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));

        unset($header, $key_pos);
        return $key_val;
    }

    /**
     * WebSocket: Get Sec-WebSocket-Protocol
     *
     * @param string $header
     *
     * @return string
     */
    public function wsGetProto(string $header): string
    {
        //Validate Sec-WebSocket-Protocol
        $proto_pos = strpos($header, 'Sec-WebSocket-Protocol');

        if (false === $proto_pos) {
            unset($header, $proto_pos);
            return '';
        }

        //Get Sec-WebSocket-Protocol
        $proto_pos += 24;
        $proto_val = substr($header, $proto_pos, strpos($header, "\r\n", $proto_pos) - $proto_pos);

        unset($header, $proto_pos);
        return $proto_val;
    }

    /**
     * WebSocket: Get Handshake response
     *
     * @param string $ws_key
     * @param string $ws_proto
     *
     * @return string
     */
    public function wsGetHandshake(string $ws_key, string $ws_proto = ''): string
    {
        //Set default protocol
        $ws_protocol = '';

        if ('' !== $ws_proto) {
            //Only response the last protocol value
            if (false !== ($proto_pos = strrpos($ws_proto, ','))) {
                $ws_proto = substr($ws_proto, $proto_pos + 2);
            }

            //Set Sec-WebSocket-Protocol response value
            $ws_protocol = 'Sec-WebSocket-Protocol: ' . $ws_proto . "\r\n";

            unset($proto_pos);
        }

        //Generate handshake response
        $response = 'HTTP/1.1 101 Switching Protocols' . "\r\n"
            . 'Upgrade: websocket' . "\r\n"
            . 'Connection: Upgrade' . "\r\n"
            . 'Sec-WebSocket-Accept: ' . $ws_key . "\r\n"
            . $ws_protocol . "\r\n";

        unset($ws_key, $ws_proto, $ws_protocol);
        return $response;
    }

    /**
     * WebSocket: Decode message
     *
     * @param string $buff
     *
     * @return string
     */
    public function wsDecode(string $buff): string
    {
        $payload_len = (ord($buff[1]) & 0x7F);

        switch ($payload_len) {
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

        $message = '';
        $length  = strlen($data);

        for ($i = 0; $i < $length; ++$i) {
            $message .= $data[$i] ^ $mask[$i % 4];
        }

        unset($buff, $payload_len, $mask, $data, $length, $i);
        return $message;
    }

    /**
     * WebSocket: Encode message
     *
     * @param string $message
     *
     * @return string
     */
    public function wsEncode(string $message): string
    {
        $length = strlen($message);

        if (125 >= $length) {
            $buff = chr(0x81) . chr($length) . $message;
        } elseif (65535 >= $length) {
            $buff = chr(0x81) . chr(126) . pack('n', $length) . $message;
        } else {
            $buff = chr(0x81) . chr(127) . pack('xxxxN', $length) . $message;
        }

        unset($message, $length);
        return $buff;
    }

    /**
     * WebSocket: Send Ping frame
     *
     * @param string $socket_id
     */
    public function wsPing(string $socket_id): void
    {
        $this->send(chr(0x89) . chr(0), [$socket_id]);
        unset($socket_id);
    }

    /**
     * WebSocket: Send Pong frame
     *
     * @param string $socket_id
     */
    public function wsPong(string $socket_id): void
    {
        $this->send(chr(0x8A) . chr(0), [$socket_id]);
        unset($socket_id);
    }
}