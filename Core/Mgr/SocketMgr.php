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

    public string $socket_id = '';

    public array $handshakes      = [];
    public array $activities      = [];
    public array $connections     = [];
    public array $context_options = [];

    private array $event_fn = [
        'onConnect'     => null,
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
     * @param string $address
     *
     * @return void
     */
    public function listenTo(string $address): void
    {
        try {
            $context = stream_context_create();

            if (!empty($this->context_options)) {
                stream_context_set_params($context, $this->context_options);
            }

            $flags = 'udp' != parse_url($address, PHP_URL_SCHEME)
                ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
                : STREAM_SERVER_BIND;

            $server = stream_socket_server($address, $errno, $errstr, $flags, $context);

            if (false === $server) {
                throw new \Exception('Server failed to start! ' . $errstr . '(' . $errno . ')', E_USER_ERROR);
            }

            stream_set_timeout($server, $this->wait_timeout);

            $this->socket_id = $this->getSocketId();

            $this->connections[$this->socket_id] = &$server;

            $this->consoleLog(__FUNCTION__, $address);

            unset($address, $context, $flags, $server, $errno, $errstr);

            $this->is_websocket ? $this->websocketStart() : $this->serverStart();
        } catch (\Throwable $throwable) {
            $this->consoleLog('ERROR', $throwable->getMessage());
        }
    }

    /**
     * @param string $address
     *
     * @return void
     */
    public function connectTo(string $address): void
    {
        try {
            $context = stream_context_create();

            if (!empty($this->context_options)) {
                stream_context_set_params($context, $this->context_options);
            }

            $flags = 'udp' != parse_url($address, PHP_URL_SCHEME)
                ? STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT
                : STREAM_CLIENT_CONNECT;

            $client = stream_socket_client($address, $errno, $errstr, $this->wait_timeout, $flags, $context);

            if (false === $client) {
                throw new \Exception('Client failed to connect! ' . $errstr . '(' . $errno . ')', E_USER_ERROR);
            }

            stream_set_timeout($client, $this->wait_timeout);

            $this->socket_id = $this->getSocketId();

            $this->connections[$this->socket_id] = &$client;

            $this->consoleLog(__FUNCTION__, $address);

            unset($address, $context, $flags, $client, $errno, $errstr);

            $this->clientStart();
        } catch (\Throwable $throwable) {
            $this->consoleLog('ERROR', $throwable->getMessage());
        }
    }

    /**
     * @return string
     */
    public function accept(): string
    {
        try {
            $accept = stream_socket_accept($this->connections[$this->socket_id], 1);

            if (false === $accept) {
                throw new \Exception('Connection failed!', E_USER_NOTICE);
            }

            $socket_id = $this->getSocketId();

            stream_set_timeout($accept, $this->wait_timeout);
            stream_set_blocking($accept, $this->set_block);

            $this->activities[$socket_id]  = time();
            $this->connections[$socket_id] = &$accept;

            if (is_callable($this->event_fn['onConnect'])) {
                $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onConnect'], [$socket_id]));
            }

            unset($accept);

            $this->consoleLog(__FUNCTION__, $socket_id . ': Connected! ' . (count($this->connections) - 1) . ' online.');
        } catch (\Throwable $throwable) {
            $this->consoleLog(__FUNCTION__, $throwable->getMessage());
            unset($throwable, $accept);
            $socket_id = '';
        }

        return $socket_id;
    }

    /**
     * @param string $socket_id
     *
     * @return array
     */
    public function readFrom(string $socket_id): array
    {
        if (!isset($this->connections[$socket_id])) {
            unset($this->activities[$socket_id]);
            return [$socket_id, ''];
        }

        try {
            $socket  = &$this->connections[$socket_id];
            $message = fgets($socket, 2);

            if (false === $message) {
                throw new \Exception($socket_id . ': Read ERROR!', E_USER_NOTICE);
            }

            while ('' !== ($buff = fread($socket, 4096))) {
                $message .= $buff;
            }

            $this->activities[$socket_id] = time();

            $this->consoleLog(__FUNCTION__, $socket_id . ': ' . $message);
        } catch (\Throwable $throwable) {
            $this->consoleLog(__FUNCTION__, $throwable->getMessage());
            $this->close($socket_id);
            unset($throwable);
            return [$socket_id, ''];
        }

        unset($clients, $client);
        return [$socket_id, $message];
    }

    /**
     * @param string $socket_id
     * @param string $message
     *
     * @return void
     */
    public function sendTo(string $socket_id, string $message): void
    {
        if (!isset($this->connections[$socket_id])) {
            unset($this->activities[$socket_id], $socket_id);
            return;
        }

        try {
            if (false === fwrite($this->connections[$socket_id], $message)) {
                throw new \Exception($socket_id . ': Send ERROR!', E_USER_NOTICE);
            }

            $this->consoleLog(__FUNCTION__, $socket_id . ': ' . $message);
        } catch (\Throwable $throwable) {
            $this->consoleLog(__FUNCTION__, $throwable->getMessage());
            $this->close($socket_id);
            unset($throwable);
        }

        unset($socket_id, $message);
    }

    /**
     * @return void
     */
    public function heartbeat(): void
    {
        $now_time = time();
        $max_wait = $this->alive_timeout * 2;

        foreach ($this->activities as $socket_id => $active_time) {
            try {
                if (!isset($this->connections[$socket_id])) {
                    unset($this->activities[$socket_id]);
                    continue;
                }

                $idle_time = $now_time - $active_time;

                if ($idle_time < $this->alive_timeout) {
                    continue;
                }

                if ($idle_time > $max_wait) {
                    $this->consoleLog(__FUNCTION__, $socket_id . ': Heartbeat lost!');
                    $this->close($socket_id);
                    continue;
                }

                if (is_callable($this->event_fn['onHeartbeat'])) {
                    $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onHeartbeat'], [$socket_id]));
                }
            } catch (\Throwable $throwable) {
                $this->consoleLog(__FUNCTION__, $throwable->getMessage());
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
    public function consoleLog(string $action, string $message): void
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
    public function getSocketId(): string
    {
        $socket_id = hash('md5', uniqid(getmypid() . mt_rand(), true));
        return !isset($this->connections[$socket_id]) ? $socket_id : $this->getSocketId();
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

            fclose($this->connections[$socket_id]);

            $this->consoleLog(__FUNCTION__, $socket_id);
        } catch (\Throwable $throwable) {
            $this->consoleLog(__FUNCTION__, $throwable->getMessage());
            unset($throwable);
        }

        unset($this->connections[$socket_id], $this->activities[$socket_id], $socket_id);
    }

    /**
     * WebSocket: Get header codes (fin, opcode, mask)
     *
     * @param string $buff
     *
     * @return int[]
     */
    public function wsGetFrameCodes(string $buff): array
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
    public function wsGetHeaderKey(string $header): string
    {
        $key_pos = strpos($header, 'Sec-WebSocket-Key');

        if (false === $key_pos) {
            unset($header, $key_pos);
            return '';
        }

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
    public function wsGetHeaderProto(string $header): string
    {
        $proto_pos = strpos($header, 'Sec-WebSocket-Protocol');

        if (false === $proto_pos) {
            unset($header, $proto_pos);
            return '';
        }

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
    public function wsBuildHandshake(string $ws_key, string $ws_proto = ''): string
    {
        $ws_protocol = '';

        if ('' !== $ws_proto) {
            $proto_pos = strrpos($ws_proto, ',');

            if (false !== $proto_pos) {
                $ws_proto = substr($ws_proto, 0, $proto_pos);
            }

            $ws_protocol = 'Sec-WebSocket-Protocol: ' . $ws_proto . "\r\n";

            unset($proto_pos);
        }

        $response = 'HTTP/1.1 101 Switching Protocols' . "\r\n"
            . 'Upgrade: websocket' . "\r\n"
            . 'Connection: Upgrade' . "\r\n"
            . 'Sec-WebSocket-Accept: ' . $ws_key . "\r\n"
            . $ws_protocol . "\r\n";

        unset($ws_key, $ws_proto, $ws_protocol);
        return $response;
    }

    /**
     * WebSocket: Send handshake response
     *
     * @param string $socket_id
     * @param string $header_msg
     *
     * @return string
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function wsSendHandshake(string $socket_id, string $header_msg): string
    {
        $ws_key   = $this->wsGetHeaderKey($header_msg);
        $ws_proto = $this->wsGetHeaderProto($header_msg);

        if (is_callable($this->event_fn['onWsHandshake'])) {
            $this->fiberMgr->async(
                $this->fiberMgr->await($this->event_fn['onWsHandshake'], [$socket_id, $ws_proto]),
                function (bool $allow_handshake) use ($ws_key, $ws_proto, $socket_id): void
                {
                    if ($allow_handshake) {
                        $this->sendTo($socket_id, $this->wsBuildHandshake($ws_key, $ws_proto));
                    } else {
                        $this->sendTo($socket_id, 'Http/1.1 406 Not Acceptable' . "\r\n\r\n");
                        $this->close($socket_id);
                    }

                    unset($allow_handshake);
                }
            );
        } else {
            $this->sendTo($socket_id, $this->wsBuildHandshake($ws_key, $ws_proto));
        }

        unset($header_msg, $ws_key, $ws_proto);
        return $socket_id;
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
        $this->sendTo($socket_id, chr(0x89) . chr(0));
        unset($socket_id);
    }

    /**
     * WebSocket: Send Pong frame
     *
     * @param string $socket_id
     */
    public function wsPong(string $socket_id): void
    {
        $this->sendTo($socket_id, chr(0x8A) . chr(0));
        unset($socket_id);
    }

    /**
     * @return void
     */
    private function websocketStart(): void
    {
        $write = $except = [];
        $this->onHeartbeat([$this, 'wsPing']);

        while (true) {
            try {
                $clients = $this->connections;

                $this->fiberMgr->async($this->fiberMgr->await([$this, 'heartbeat']));

                if (0 < (int)stream_select($clients, $write, $except, 0, $this->select_timeout)) {
                    if (isset($clients[$this->socket_id])) {
                        $this->fiberMgr->async(
                            $this->fiberMgr->await([$this, 'accept']),
                            function (string $socket_id): void
                            {
                                $this->handshakes[$socket_id] = false;
                            }
                        );

                        unset($clients[$this->socket_id]);
                    }

                    if (!empty($clients)) {
                        foreach ($clients as $socket_id => $client) {
                            $this->fiberMgr->async(
                                $this->fiberMgr->await([$this, 'readFrom'], [$socket_id]),
                                function (string $socket_id, string $message): void
                                {
                                    if (isset($this->handshakes[$socket_id])) {
                                        $this->fiberMgr->async(
                                            $this->fiberMgr->await([$this, 'wsSendHandshake'], [$socket_id, $message]),
                                            function (string $socket_id): void
                                            {
                                                unset($this->handshakes[$socket_id]);
                                            }
                                        );

                                        return;
                                    }

                                    $ws_codes = $this->wsGetFrameCodes($message);

                                    if (0xA === $ws_codes['opcode']) {
                                        return;
                                    }

                                    if (0x9 === $ws_codes['opcode']) {
                                        $this->wsPong($socket_id);
                                        return;
                                    }

                                    if (0x8 === $ws_codes['opcode']) {
                                        $this->close($socket_id);
                                        return;
                                    }

                                    if (is_callable($this->event_fn['onMessage'])) {
                                        $this->fiberMgr->async(
                                            $this->fiberMgr->await($this->event_fn['onMessage'],
                                                [$socket_id, $this->wsDecode($message)]
                                            )
                                        );
                                    }
                                }
                            );
                        }

                        unset($socket_id, $client);
                    }
                }

                if (is_callable($this->event_fn['onSend'])) {
                    $this->fiberMgr->async(
                        $this->fiberMgr->await($this->event_fn['onSend']),
                        function (array $socket_messages): void
                        {
                            while (null !== ($data = array_pop($socket_messages))) {
                                $this->fiberMgr->async(
                                    $this->fiberMgr->await(
                                        [$this, 'sendTo'],
                                        [$data['socket_id'], $this->wsEncode($data['message'])]
                                    )
                                );
                            }
                        }
                    );
                }

                $this->fiberMgr->run();
            } catch (\Throwable $throwable) {
                $this->consoleLog('ERROR', $throwable->getMessage());
                unset($throwable);
            }

            unset($clients);
        }
    }

    /**
     * @return void
     * @throws \Throwable
     */
    private function serverStart(): void
    {
        $write = $except = [];

        while (true) {
            try {
                $clients = $this->connections;

                $this->fiberMgr->async($this->fiberMgr->await([$this, 'heartbeat']));

                if (0 < (int)stream_select($clients, $write, $except, 0, $this->select_timeout)) {
                    if (isset($clients[$this->socket_id])) {
                        $this->fiberMgr->async($this->fiberMgr->await([$this, 'accept']));
                        unset($clients[$this->socket_id]);
                    }

                    if (!empty($clients)) {
                        foreach ($clients as $socket_id => $client) {
                            $this->fiberMgr->async($this->fiberMgr->await([$this, 'readFrom'], [$socket_id]), $this->event_fn['onMessage']);
                        }

                        unset($socket_id, $client);
                    }
                }

                if (is_callable($this->event_fn['onSend'])) {
                    $this->fiberMgr->async(
                        $this->fiberMgr->await($this->event_fn['onSend']),
                        function (array $socket_messages): void
                        {
                            while (null !== ($data = array_pop($socket_messages))) {
                                $this->fiberMgr->async(
                                    $this->fiberMgr->await(
                                        [$this, 'sendTo'],
                                        [$data['socket_id'], $data['message']]
                                    )
                                );
                            }
                        }
                    );
                }

                $this->fiberMgr->run();
            } catch (\Throwable $throwable) {
                $this->consoleLog('ERROR', $throwable->getMessage());
                unset($throwable);
            }

            unset($clients);
        }
    }

    /**
     * @return void
     */
    private function clientStart(): void
    {
        $write = $except = [];

        while (true) {
            try {
                if (empty($this->connections)) {
                    $this->consoleLog('ERROR', 'Connection lost!');
                    break;
                }

                $connection = [$this->connections[$this->socket_id]];

                $this->fiberMgr->async($this->fiberMgr->await([$this, 'heartbeat']));

                if (0 < (int)stream_select($connection, $write, $except, 0, $this->select_timeout)) {
                    $this->fiberMgr->async($this->fiberMgr->await([$this, 'readFrom'], [$this->socket_id]), $this->event_fn['onMessage']);
                }

                if (is_callable($this->event_fn['onSend'])) {
                    $this->fiberMgr->async($this->fiberMgr->await($this->event_fn['onSend']));
                }

                $this->fiberMgr->run();
            } catch (\Throwable $throwable) {
                $this->consoleLog('ERROR', $throwable->getMessage());
                unset($throwable);
            }

            unset($connection);
        }
    }
}