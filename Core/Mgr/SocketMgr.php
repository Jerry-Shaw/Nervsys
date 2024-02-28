<?php

/**
 * Socket Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2023 秋水之冰 <27206617@qq.com>
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
use Nervsys\Core\Lib\Error;

class SocketMgr extends Factory
{
    public Error    $error;
    public FiberMgr $fiberMgr;

    public string $heartbeat = "\n";

    public bool $block_mode = false;
    public bool $debug_mode = false;

    public array $callbacks = [
        'onConnect'   => null,  //callback(string $socket_id): void
        'onHandshake' => null,  //callback(string $ws_proto): bool, true to allow, otherwise reject.
        'onHeartbeat' => null,  //callback(string $socket_id): string, heartbeat message send to $socket_id
        'onMessage'   => null,  //callback(string $socket_id, string $message): void
        'onSend'      => null,  //callback(string $socket_id): array[string], message list send to $socket_id, [msg1, msg2, msg3, ...]
        'onClose'     => null   //callback(string $socket_id): void
    ];

    public array $options = [];
    public array $read_at = [0, 500000, 60, 200];

    public array $handshakes  = [];
    public array $activities  = [];
    public array $connections = [];
    public array $master_sock = [];

    /**
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->error    = Error::new();
        $this->fiberMgr = FiberMgr::new();
    }

    /**
     * @param bool $block_mode
     *
     * @return $this
     */
    public function setBlockMode(bool $block_mode): self
    {
        $this->block_mode = &$block_mode;

        unset($block_mode);
        return $this;
    }

    /**
     * @param bool $debug_mode
     *
     * @return $this
     */
    public function setDebugMode(bool $debug_mode): self
    {
        $this->debug_mode = &$debug_mode;

        unset($debug_mode);
        return $this;
    }

    /**
     * @param int      $seconds
     * @param int|null $microseconds
     * @param int      $alive_seconds
     * @param int      $fragment_num
     *
     * @return $this
     */
    public function setReadOptions(int $seconds, int $microseconds = null, int $alive_seconds = 60, int $fragment_num = 200): self
    {
        $this->read_at = [&$seconds, &$microseconds, &$alive_seconds, &$fragment_num];

        unset($seconds, $microseconds, $alive_seconds, $fragment_num);
        return $this;
    }

    /**
     * @param string $local_cert
     * @param string $local_pk
     * @param string $passphrase
     * @param bool   $self_signed
     * @param string $ssl_transport
     *
     * @return $this
     */
    public function setSSLCert(string $local_cert, string $local_pk = '', string $passphrase = '', bool $self_signed = false, string $ssl_transport = 'ssl'): self
    {
        $options = [
            'local_cert'          => &$local_cert,
            'verify_peer'         => false,
            'ssltransport'        => &$ssl_transport,
            'verify_peer_name'    => false,
            'allow_self_signed'   => &$self_signed,
            'disable_compression' => true
        ];

        if ('' !== $local_pk) {
            $options['local_pk'] = &$local_pk;
        }

        if ('' !== $passphrase) {
            $options['passphrase'] = &$passphrase;
        }

        $this->setContextOptions('ssl', $options);

        unset($local_cert, $local_pk, $passphrase, $self_signed, $ssl_transport, $options);
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
        $this->options[$wrapper] = &$options;

        unset($wrapper, $options);
        return $this;
    }

    /**
     * @param string $heartbeat_char
     *
     * @return $this
     */
    public function setHeartbeatChar(string $heartbeat_char): self
    {
        $this->heartbeat = &$heartbeat_char;

        unset($heartbeat_char);
        return $this;
    }

    /**
     * @param string   $callback_param
     * @param callable $callback_func
     *
     * @return $this
     * @throws \Exception
     */
    public function setCallbackFn(string $callback_param, callable $callback_func): self
    {
        if (!array_key_exists($callback_param, $this->callbacks)) {
            throw new \Exception('"' . $callback_param . '" NOT accept!', E_USER_ERROR);
        }

        $this->callbacks[$callback_param] = &$callback_func;

        unset($callback_param, $callback_func);
        return $this;
    }

    /**
     * @param string $address
     * @param bool   $websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function listenTo(string $address, bool $websocket = false): void
    {
        $this->createServer($address);

        $this->fiberMgr->async([$this, 'onConnect'], [$websocket]);
        $this->fiberMgr->async([$this, 'onMessage'], [$websocket]);
        $this->fiberMgr->async([$this, 'onHeartbeat'], [$websocket]);
        $this->fiberMgr->async([$this, 'onSend'], [$websocket]);

        $this->fiberMgr->commit();
    }

    /**
     * @param string $address
     *
     * @return void
     * @throws \Exception
     */
    public function createServer(string $address): void
    {
        $context = stream_context_create();

        if (!empty($this->options)) {
            stream_context_set_params($context, $this->options);
        }

        $flags = 'udp' != parse_url($address, PHP_URL_SCHEME)
            ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
            : STREAM_SERVER_BIND;

        $master_socket = stream_socket_server($address, $errno, $errstr, $flags, $context);

        if (false === $master_socket) {
            throw new \Exception('Server failed to start! ' . $errstr . '(' . $errno . ')', E_USER_ERROR);
        }

        $this->master_sock = [$this->getSocketId() => &$master_socket];

        unset($address, $context, $flags, $master_socket);
    }

    /**
     * @param bool $websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function onConnect(bool $websocket = false): void
    {
        $write = $except = [];

        while (true) {
            $socket = $this->master_sock;

            if (0 === stream_select($socket, $write, $except, $this->read_at[0], $this->read_at[1])) {
                \Fiber::suspend();
                continue;
            }

            $client = stream_socket_accept(current($socket), 1);

            if (false === $client) {
                $this->debug('Failed to accept client!');
                continue;
            }

            stream_set_blocking($client, $this->block_mode);

            $socket_id = $this->getSocketId();

            if ($websocket) {
                $this->handshakes[$socket_id] = false;
            }

            $this->activities[$socket_id]  = time();
            $this->connections[$socket_id] = $client;

            $this->debug('Client connected: ' . $socket_id);

            if (is_callable($this->callbacks['onConnect'])) {
                try {
                    call_user_func($this->callbacks['onConnect'], $socket_id);
                } catch (\Throwable $throwable) {
                    $this->debug('onConnect callback ERROR: ' . $throwable->getMessage());
                    $this->error->exceptionHandler($throwable, false, false);
                    unset($throwable);
                }
            }
        }
    }

    /**
     * @param bool $websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function onMessage(bool $websocket = false): void
    {
        $write = $except = [];

        while (true) {
            $count   = 0;
            $clients = $this->connections;

            if (empty($clients) || 0 === stream_select($clients, $write, $except, $this->read_at[0], $this->read_at[1])) {
                \Fiber::suspend();
                continue;
            }

            foreach ($clients as $socket_id => $client) {
                if (++$count > $this->read_at[3]) {
                    $count = 0;
                    \Fiber::suspend();
                }

                try {
                    if (!$websocket) {
                        $message = $this->readMessage($socket_id);
                    } else {
                        if (isset($this->handshakes[$socket_id])) {
                            $message = $this->readMessage($socket_id, false);
                            $this->wsSendHandshake($socket_id, $message);
                            continue;
                        }

                        $message = $this->readMessage($socket_id);
                        $message = $this->wsGetMessage($socket_id, $message);
                    }
                } catch (\Throwable $throwable) {
                    $this->debug('onMessage debug: ' . $throwable->getMessage());
                    unset($throwable);
                    continue;
                }

                $this->debug('Read message from ' . $socket_id . ': ' . $message);

                if (is_callable($this->callbacks['onMessage'])) {
                    try {
                        call_user_func($this->callbacks['onMessage'], $socket_id, $message);
                    } catch (\Throwable $throwable) {
                        $this->debug('onMessage callback ERROR: ' . $throwable->getMessage());
                        $this->error->exceptionHandler($throwable, false, false);
                        unset($throwable);
                    }
                }
            }
        }
    }

    /**
     * @param bool $websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function onHeartbeat(bool $websocket = false): void
    {
        $alive_sec = &$this->read_at[2];
        $watch_sec = (int)($alive_sec / 1.5);

        while (true) {
            $count    = 0;
            $now_time = time();

            foreach ($this->activities as $socket_id => $active_time) {
                if (++$count > $this->read_at[3]) {
                    $count = 0;
                    \Fiber::suspend();
                }

                $wait_sec = $now_time - $active_time;

                if ($wait_sec < $watch_sec) {
                    continue;
                }

                if ($wait_sec > $alive_sec) {
                    $this->debug('Heartbeat lost: ' . $socket_id);
                    $this->closeSocket($socket_id);
                    continue;
                }

                if ($websocket) {
                    $this->wsPing($socket_id);
                    $this->debug('Heartbeat to websocket: ' . $socket_id);
                } else {
                    if (!is_callable($this->callbacks['onHeartbeat'])) {
                        $heartbeat = $this->heartbeat;
                    } else {
                        try {
                            $heartbeat = call_user_func($this->callbacks['onHeartbeat'], $socket_id);
                        } catch (\Throwable $throwable) {
                            $heartbeat = $this->heartbeat;
                            $this->debug('onHeartbeat callback ERROR: ' . $throwable->getMessage());
                            $this->error->exceptionHandler($throwable, false, false);
                            unset($throwable);
                        }
                    }

                    $this->sendMessage($socket_id, $heartbeat);
                    $this->debug('Heartbeat to client: ' . $socket_id);
                }
            }

            \Fiber::suspend();
        }
    }

    /**
     * @param bool $websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function onSend(bool $websocket = false): void
    {
        if (!is_callable($this->callbacks['onSend'])) {
            return;
        }

        while (true) {
            $count   = 0;
            $clients = array_diff_key($this->connections, $this->handshakes);

            if (empty($clients)) {
                \Fiber::suspend();
                continue;
            }

            foreach ($clients as $socket_id => $client) {
                if (++$count > $this->read_at[3]) {
                    $count = 0;
                    \Fiber::suspend();
                }

                try {
                    $msg_list = call_user_func($this->callbacks['onSend'], $socket_id);
                } catch (\Throwable $throwable) {
                    $this->debug('onSend callback ERROR: ' . $throwable->getMessage());
                    $this->error->exceptionHandler($throwable, false, false);
                    unset($throwable);
                    continue;
                }

                foreach ($msg_list as $raw_msg) {
                    if ($this->sendMessage($socket_id, $websocket ? $this->wsEncode($raw_msg) : $raw_msg)) {
                        $this->debug('Send Message: ' . $raw_msg . ' to ' . $socket_id);
                    } else {
                        break;
                    }
                }
            }

            \Fiber::suspend();
        }
    }

    /**
     * @param string $message
     *
     * @return void
     */
    public function debug(string $message): void
    {
        if ($this->debug_mode) {
            echo date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL;
        }

        unset($message);
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
     * @param bool   $read_by_line
     *
     * @return string
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function readMessage(string $socket_id, bool $read_by_line = true): string
    {
        try {
            if ($read_by_line) {
                $message = fgets($this->connections[$socket_id]);

                if (false === $message) {
                    throw new \Exception('Read client ERROR!', E_USER_NOTICE);
                }
            } else {
                $message = fread($this->connections[$socket_id], 4096);

                if (false === $message) {
                    throw new \Exception('Read client ERROR!', E_USER_NOTICE);
                }

                while ('' !== ($fragment = fread($this->connections[$socket_id], 4096))) {
                    $message .= $fragment;
                }
            }

            $this->activities[$socket_id] = time();
        } catch (\Throwable) {
            $this->closeSocket($socket_id);
            throw new \Exception('Read client ERROR!', E_USER_NOTICE);
        }

        unset($socket_id, $read_by_line, $fragment);
        return $message;
    }

    /**
     * @param string $socket_id
     * @param string $message
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function sendMessage(string $socket_id, string $message): bool
    {
        try {
            if (false === ($send = fwrite($this->connections[$socket_id], $message))) {
                throw new \Exception($socket_id . ' lost connection!', E_USER_NOTICE);
            }
        } catch (\Throwable $throwable) {
            $this->debug('Send message ERROR: ' . $throwable->getMessage());
            $this->closeSocket($socket_id);
            unset($throwable);
            $send = false;
        }

        unset($socket_id, $message);
        return is_int($send);
    }

    /**
     * @param string $socket_id
     *
     * @return void
     * @throws \ReflectionException
     */
    public function closeSocket(string $socket_id): void
    {
        if (!isset($this->connections[$socket_id])) {
            return;
        }

        try {
            fclose($this->connections[$socket_id]);
        } catch (\Throwable) {
            stream_socket_enable_crypto($this->connections[$socket_id], false);
            stream_socket_shutdown($this->connections[$socket_id], STREAM_SHUT_RDWR);
        }

        unset($this->connections[$socket_id], $this->activities[$socket_id], $this->handshakes[$socket_id]);

        $this->debug('Client closed: ' . $socket_id);

        if (is_callable($this->callbacks['onClose'])) {
            try {
                call_user_func($this->callbacks['onClose'], $socket_id);
            } catch (\Throwable $throwable) {
                $this->debug('onClose callback ERROR: ' . $throwable->getMessage());
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }
        }

        unset($socket_id);
    }

    /**
     * @param string $socket_id
     * @param string $message
     *
     * @return string
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function wsGetMessage(string $socket_id, string $message): string
    {
        $ws_codes = $this->wsGetFrameCodes($message);

        switch ($ws_codes['opcode']) {
            case 0x0:
                break;
            case 0x1:
                break;
            case 0x2:
                break;

            case 0x8:
                $this->closeSocket($socket_id);
                throw new \Exception('Connection closed by Client!', E_USER_NOTICE);
                break;
            case 0x9:
                $this->wsPong($socket_id);
                throw new \Exception('Received Ping frame!', E_USER_NOTICE);
                break;
            case 0xA:
                throw new \Exception('Received Pong frame!', E_USER_NOTICE);
                break;

            case 0x3:
            case 0x4:
            case 0x5:
            case 0x6:
            case 0x7:
            case 0xB:
            case 0xC:
            case 0xD:
            case 0xE:
            case 0xF:
                throw new \Exception('Opcode: ' . $ws_codes['opcode'] . '. Reserved frames!', E_USER_NOTICE);
                break;

            default:
                $this->closeSocket($socket_id);
                throw new \Exception('Opcode ERROR! Close connection!', E_USER_NOTICE);
                break;
        }

        $message = $this->wsDecode($message);

        unset($socket_id, $ws_codes);
        return $message;
    }

    /**
     * @param string $socket_id
     * @param string $message
     *
     * @return void
     * @throws \ReflectionException
     */
    public function wsSendHandshake(string $socket_id, string $message): void
    {
        $handshake = true;

        $ws_key   = $this->wsGetHeaderKey($message);
        $ws_proto = $this->wsGetHeaderProto($message);

        if (is_callable($this->callbacks['onHandshake'])) {
            try {
                $handshake = (bool)call_user_func($this->callbacks['onHandshake'], $socket_id, $ws_proto);
            } catch (\Throwable) {
                $handshake = false;
            }
        }

        if ($handshake) {
            $this->debug('Accept handshake: ' . $message);
            $this->sendMessage($socket_id, $this->wsBuildHandshake($ws_key, $ws_proto));
            unset($this->handshakes[$socket_id]);
        } else {
            $this->debug('Refuse handshake: ' . $message);
            $this->sendMessage($socket_id, 'Http/1.1 406 Not Acceptable' . "\r\n\r\n");
            $this->closeSocket($socket_id);
        }

        unset($socket_id, $message, $handshake, $ws_key, $ws_proto);
    }

    /**
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
     * @param string $buff
     *
     * @return int[]
     */
    public function wsGetFrameCodes(string $buff): array
    {
        $char = ord($buff[0]);

        $codes = [
            'fin'    => $char >> 7,
            'opcode' => $char & 0x0F,
            'mask'   => ord($buff[1]) >> 7
        ];

        unset($buff, $char);
        return $codes;
    }

    /**
     * @param string $buffer
     *
     * @return string
     */
    public function wsDecode(string $buffer): string
    {
        $payload_length = (ord($buffer[1]) & 0x7F);

        switch ($payload_length) {
            case 126:
                $data_length = ((ord($buffer[2]) & 0xFF) << 8) | (ord($buffer[3]) & 0xFF);
                $data_mask   = substr($buffer, 4, 4);
                $data_body   = substr($buffer, 8, $data_length);
                break;

            case 127:
                $data_length = (ord($buffer[2]) << 56)
                    | (ord($buffer[3]) << 48)
                    | (ord($buffer[4]) << 40)
                    | (ord($buffer[5]) << 32)
                    | (ord($buffer[6]) << 24)
                    | (ord($buffer[7]) << 16)
                    | (ord($buffer[8]) << 8)
                    | (ord($buffer[7]) << 0);
                $data_mask   = substr($buffer, 10, 4);
                $data_body   = substr($buffer, 14, $data_length);
                break;

            default:
                $data_mask = substr($buffer, 2, 4);
                $data_body = substr($buffer, 6, $payload_length);
                break;
        }

        $message = '';
        $length  = strlen($data_body);

        for ($i = 0; $i < $length; ++$i) {
            $message .= $data_body[$i] ^ $data_mask[$i % 4];
        }

        unset($buffer, $payload_length, $data_length, $data_mask, $data_body, $length, $i);
        return $message;
    }

    /**
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
     * @param string $socket_id
     *
     * @return void
     * @throws \ReflectionException
     */
    public function wsPing(string $socket_id): void
    {
        $this->sendMessage($socket_id, chr(0x89) . chr(0));
        unset($socket_id);
    }

    /**
     * @param string $socket_id
     *
     * @return void
     * @throws \ReflectionException
     */
    public function wsPong(string $socket_id): void
    {
        $this->sendMessage($socket_id, chr(0x8A) . chr(0));
        unset($socket_id);
    }
}