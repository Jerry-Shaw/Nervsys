<?php

/**
 * Socket Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2024 秋水之冰 <27206617@qq.com>
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

    public string $address   = '';
    public string $master_id = '';
    public string $sock_type = '';
    public string $heartbeat = "\n";

    public bool $block_mode = false;
    public bool $debug_mode = false;

    public array $callbacks = [
        'onConnect'    => null,  //callback(string $socket_id): string
        'onHandshake'  => null,  //callback(string $ws_proto): bool, true to allow, otherwise reject.
        'onHeartbeat'  => null,  //callback(string $socket_id): string, heartbeat message send to $socket_id
        'onMessage'    => null,  //callback(string $socket_id, string $message): void
        'onSend'       => null,  //callback(string $socket_id): array[string], message list send to $socket_id, [msg1, msg2, msg3, ...]
        'onSendFailed' => null,  //callback(string $socket_id, string $message): void
        'onClose'      => null   //callback(string $socket_id): void
    ];

    public array $options   = [];
    public array $read_at   = [0, 500000, 60, 200];
    public array $reconnect = [3, 10]; //retry_times: -1 means always try to connect, 0 means don't reconnect after disconnected

    public array $handshakes  = [];
    public array $activities  = [];
    public array $connections = [];
    public array $master_sock = [];
    public array $data_frames = [];

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
     * @param int $retry_times
     * @param int $wait_seconds
     *
     * @return $this
     */
    public function setReconnectOptions(int $retry_times, int $wait_seconds): self
    {
        $this->reconnect = [&$retry_times, &$wait_seconds];

        unset($retry_times, $wait_seconds);
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
    public function setReadOptions(int $seconds, int|null $microseconds = null, int $alive_seconds = 60, int $fragment_num = 200): self
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
     * @param string   $event
     * @param callable $callback
     *
     * @return $this
     * @throws \Exception
     */
    public function setEventListener(string $event, callable $callback): self
    {
        if (!array_key_exists($event, $this->callbacks)) {
            throw new \Exception('"' . $event . '" NOT accept!', E_USER_ERROR);
        }

        $this->callbacks[$event] = &$callback;

        unset($event, $callback);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onConnect(callable $callback_func): self
    {
        $this->callbacks['onConnect'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onHandshake(callable $callback_func): self
    {
        $this->callbacks['onHandshake'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onHeartbeat(callable $callback_func): self
    {
        $this->callbacks['onHeartbeat'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onMessage(callable $callback_func): self
    {
        $this->callbacks['onMessage'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onSend(callable $callback_func): self
    {
        $this->callbacks['onSend'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onSendFailed(callable $callback_func): self
    {
        $this->callbacks['onSendFailed'] = &$callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onClose(callable $callback_func): self
    {
        $this->callbacks['onClose'] = &$callback_func;

        unset($callback_func);
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
        $this->address = &$address;
        $this->createServer($address);

        if ('udp' !== $this->sock_type) {
            $this->fiberMgr->async([$this, 'serverOnConnect'], [$websocket]);
        } else {
            $this->connections = $this->master_sock;
        }

        $this->fiberMgr->async([$this, 'serverOnMessage'], [$websocket]);
        $this->fiberMgr->async([$this, 'serverOnHeartbeat'], [$websocket]);
        $this->fiberMgr->async([$this, 'serverOnSend'], [$websocket]);

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
            if (!stream_context_set_params($context, ['options' => $this->options])) {
                throw new \Exception('Failed to set context options!', E_USER_ERROR);
            }
        }

        $this->sock_type = strtolower(parse_url($address, PHP_URL_SCHEME));

        $flags = 'udp' != $this->sock_type
            ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN
            : STREAM_SERVER_BIND;

        $master_socket = stream_socket_server($address, $errno, $errstr, $flags, $context);

        if (false === $master_socket) {
            throw new \Exception('Server failed to start! ' . $errstr . '(' . $errno . ')', E_USER_ERROR);
        }

        $this->master_id   = get_resource_id($master_socket);
        $this->master_sock = [$this->master_id => &$master_socket];

        $this->debug('Server started! Listen to ' . $address . '. ID: #' . $this->master_id);

        unset($address, $context, $flags, $master_socket);
    }

    /**
     * @param bool $websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function serverOnConnect(bool $websocket = false): void
    {
        $write = $except = [];

        while (true) {
            $socket = $this->master_sock;

            if (0 === stream_select($socket, $write, $except, $this->read_at[0], $this->read_at[1])) {
                \Fiber::suspend();
                continue;
            }

            try {
                $client = stream_socket_accept(current($socket), 1);
            } catch (\Throwable $throwable) {
                $this->debug('Accept connection failed: ' . $throwable->getMessage());
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
                continue;
            }

            if (false === $client) {
                $this->debug('Failed to accept client!');
                continue;
            }

            stream_set_blocking($client, $this->block_mode);

            $socket_id = get_resource_id($client);

            if ($websocket) {
                $this->handshakes[$socket_id] = false;
            }

            $now_time = time();

            $this->activities[$socket_id]  = [$now_time, $now_time];
            $this->connections[$socket_id] = $client;

            $this->debug('Client connected: #' . $socket_id);

            if (is_callable($this->callbacks['onConnect'])) {
                try {
                    $response = call_user_func($this->callbacks['onConnect'], $socket_id);

                    if (!$websocket && is_string($response) && '' !== $response) {
                        $this->sendMessage($socket_id, $response);
                    }
                } catch (\Throwable $throwable) {
                    $this->debug('serverOnConnect callback ERROR: ' . $throwable->getMessage());
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
    public function serverOnMessage(bool $websocket = false): void
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
                    $message = $this->readMessage($socket_id);

                    if ($websocket) {
                        if (isset($this->handshakes[$socket_id])) {
                            $this->wsSendHandshake($socket_id, $message);
                            continue;
                        }

                        $message = $this->wsGetMessage($socket_id, $message);
                    }
                } catch (\Throwable $throwable) {
                    $this->debug('serverOnMessage debug: ' . $throwable->getMessage());
                    unset($throwable);
                    continue;
                }

                $this->debug('Read message from #' . $socket_id . ': ' . $message);

                if (is_callable($this->callbacks['onMessage'])) {
                    try {
                        call_user_func($this->callbacks['onMessage'], $socket_id, $message);
                    } catch (\Throwable $throwable) {
                        $this->debug('serverOnMessage callback ERROR: ' . $throwable->getMessage());
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
    public function serverOnHeartbeat(bool $websocket = false): void
    {
        $alive_sec = &$this->read_at[2];
        $watch_sec = (int)($alive_sec / 1.5);

        while (true) {
            $count    = 0;
            $now_time = time();

            foreach ($this->activities as $socket_id => $active_times) {
                if (++$count > $this->read_at[3]) {
                    $count = 0;
                    \Fiber::suspend();
                }

                if ($now_time - max(...$active_times) < $watch_sec) {
                    continue;
                }

                if ($now_time - $active_times[0] > $alive_sec) {
                    $this->debug('Client heartbeat lost: #' . $socket_id);
                    $this->closeSocket($socket_id);
                    continue;
                }

                $this->activities[$socket_id][1] = $now_time;

                if ($websocket) {
                    $this->wsPing($socket_id);
                    $this->debug('Send heartbeat to websocket: #' . $socket_id);
                } else {
                    if (!is_callable($this->callbacks['onHeartbeat'])) {
                        $heartbeat = $this->heartbeat;
                    } else {
                        try {
                            $heartbeat = call_user_func($this->callbacks['onHeartbeat'], $socket_id);
                        } catch (\Throwable $throwable) {
                            $heartbeat = $this->heartbeat;
                            $this->debug('serverOnHeartbeat callback ERROR: ' . $throwable->getMessage());
                            $this->error->exceptionHandler($throwable, false, false);
                            unset($throwable);
                        }
                    }

                    $this->sendMessage($socket_id, $heartbeat);
                    $this->debug('Send heartbeat to client: #' . $socket_id);
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
    public function serverOnSend(bool $websocket = false): void
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
                    $this->debug('serverOnSend callback ERROR: ' . $throwable->getMessage());
                    $this->error->exceptionHandler($throwable, false, false);
                    unset($throwable);
                    continue;
                }

                foreach ($msg_list as $raw_msg) {
                    if ($this->sendMessage($socket_id, $websocket ? $this->wsEncode($raw_msg) : $raw_msg)) {
                        $this->debug('Send message: ' . $raw_msg . ' to #' . $socket_id);
                        usleep($this->read_at[1]);
                    } else {
                        if (is_callable($this->callbacks['onSendFailed'])) {
                            try {
                                call_user_func($this->callbacks['onSendFailed'], $socket_id, $raw_msg);
                            } catch (\Throwable $throwable) {
                                $this->debug('serverOnSendFailed callback ERROR: ' . $throwable->getMessage());
                                $this->error->exceptionHandler($throwable, false, false);
                                unset($throwable);
                                continue;
                            }
                        } else {
                            break;
                        }
                    }
                }
            }

            \Fiber::suspend();
        }
    }

    /**
     * @param string $address
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function connectTo(string $address): void
    {
        $this->address = &$address;
        $this->createClient($address);

        $this->fiberMgr->async([$this, 'clientOnMessage']);
        $this->fiberMgr->async([$this, 'clientOnHeartbeat']);
        $this->fiberMgr->async([$this, 'clientOnSend']);

        $this->fiberMgr->commit();
    }

    /**
     * @param string $address
     *
     * @return void
     * @throws \Exception
     */
    public function createClient(string $address): void
    {
        $context = stream_context_create();

        if (!empty($this->options)) {
            if (!stream_context_set_params($context, ['options' => $this->options])) {
                throw new \Exception('Failed to set context options!', E_USER_ERROR);
            }
        }

        $flags = 'udp' != parse_url($address, PHP_URL_SCHEME)
            ? STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT
            : STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;

        $master_socket = stream_socket_client($address, $errno, $errstr, 60, $flags, $context);

        if (false === $master_socket) {
            throw new \Exception('Failed to connect! ' . $errstr . '(' . $errno . ')', E_USER_ERROR);
        }

        stream_set_blocking($master_socket, $this->block_mode);

        $now_time          = time();
        $this->master_id   = get_resource_id($master_socket);
        $this->master_sock = [$this->master_id => $master_socket];
        $this->connections = [$this->master_id => $master_socket];
        $this->activities  = [$this->master_id => [$now_time, $now_time]];

        $this->debug('Client started! Connect to ' . $address . '. ID: #' . $this->master_id);

        unset($address, $context, $flags, $master_socket, $now_time);
    }

    /**
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function clientOnMessage(): void
    {
        $write = $except = [];

        while (true) {
            $servers = $this->master_sock;

            if (0 === stream_select($servers, $write, $except, $this->read_at[0], $this->read_at[1])) {
                \Fiber::suspend();
                continue;
            }

            try {
                $message = $this->readMessage($this->master_id);
            } catch (\Throwable $throwable) {
                $this->debug('Read message failed: ' . $throwable->getMessage());
                $this->clientReconnect();
                unset($throwable);
                \Fiber::suspend();
                continue;
            }

            $this->debug('Read message from server: ' . $message);

            if (is_callable($this->callbacks['onMessage'])) {
                try {
                    call_user_func($this->callbacks['onMessage'], $this->master_id, $message);
                } catch (\Throwable $throwable) {
                    $this->debug('clientOnMessage callback ERROR: ' . $throwable->getMessage());
                    $this->error->exceptionHandler($throwable, false, false);
                    unset($throwable);
                }
            }
        }
    }

    /**
     * @return void
     */
    public function clientOnHeartbeat(): void
    {
        $alive_sec = round($this->read_at[2] / 2);

        if (!is_callable($this->callbacks['onHeartbeat'])) {
            $heartbeat = $this->heartbeat;
        } else {
            try {
                $heartbeat = call_user_func($this->callbacks['onHeartbeat'], $this->master_id);
            } catch (\Throwable $throwable) {
                $heartbeat = $this->heartbeat;
                $this->debug('clientOnHeartbeat callback ERROR: ' . $throwable->getMessage());
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }
        }

        while (true) {
            $now_time  = time();
            $last_time = $this->activities[$this->master_id][1] ?? 0;

            if ($now_time - $last_time < $alive_sec) {
                \Fiber::suspend();
                continue;
            }

            if ($this->sendMessage($this->master_id, $heartbeat)) {
                $this->activities[$this->master_id][1] = $now_time;
                $this->debug('Send heartbeat to server');
            } else {
                $this->clientReconnect();
            }
        }
    }

    /**
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function clientOnSend(): void
    {
        if (!is_callable($this->callbacks['onSend'])) {
            return;
        }

        while (true) {
            try {
                $msg_list = call_user_func($this->callbacks['onSend'], $this->master_id);

                foreach ($msg_list as $raw_msg) {
                    if ($this->sendMessage($this->master_id, $raw_msg)) {
                        $this->debug('Send message to server: ' . $raw_msg);
                        usleep($this->read_at[1]);
                    } else {
                        $this->clientReconnect();

                        if (is_callable($this->callbacks['onSendFailed'])) {
                            try {
                                call_user_func($this->callbacks['onSendFailed'], $this->master_id, $raw_msg);
                            } catch (\Throwable $throwable) {
                                $this->debug('clientOnSendFailed callback ERROR: ' . $throwable->getMessage());
                                $this->error->exceptionHandler($throwable, false, false);
                                unset($throwable);
                            }
                        }
                    }
                }
            } catch (\Throwable $throwable) {
                $this->debug('clientOnSend callback ERROR: ' . $throwable->getMessage());
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }

            \Fiber::suspend();
        }
    }

    /**
     * @return void
     */
    public function clientReconnect(): void
    {
        switch ($this->reconnect[0]) {
            case 0:
                $this->debug('Reconnect failed, quit!');
                exit();

            case -1:
                while (true) {
                    $this->debug('Reconnecting...');

                    sleep($this->reconnect[1]);

                    try {
                        $this->createClient($this->address);
                        return;
                    } catch (\Throwable $throwable) {
                        $this->debug('Reconnect failed: ' . $throwable->getMessage());
                        unset($throwable);
                    }
                }

            default:
                for ($i = 1; $i <= $this->reconnect[0]; $i++) {
                    $this->debug('Reconnecting...');

                    sleep($this->reconnect[1]);

                    try {
                        $this->createClient($this->address);
                        return;
                    } catch (\Throwable $throwable) {
                        $this->debug('Reconnect failed (' . $i . '/' . $this->reconnect[0] . '): ' . $throwable->getMessage());
                        unset($throwable);
                    }
                }

                $this->debug('Reconnect failed ' . $this->reconnect[0] . ' times, quit!');
                exit();
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
     * @param string $socket_id
     *
     * @return string
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function readMessage(string $socket_id): string
    {
        try {
            if ('udp' !== $this->sock_type) {
                $message = fgetc($this->connections[$socket_id]);

                if (false === $message) {
                    throw new \Exception('Read ERROR!', E_USER_NOTICE);
                }

                while ('' !== ($fragment = fread($this->connections[$socket_id], 8192))) {
                    $message .= $fragment;
                }

                $this->activities[$socket_id][0] = time();
            } else {
                $message = stream_socket_recvfrom($this->connections[$socket_id], 65536);
            }
        } catch (\Throwable) {
            $this->closeSocket($socket_id);
            throw new \Exception('Read ERROR!', E_USER_NOTICE);
        }

        unset($socket_id, $fragment);
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
            if ('udp' !== $this->sock_type) {
                if (false === ($send = fwrite($this->connections[$socket_id], $message))) {
                    throw new \Exception($socket_id . ' lost connection!', E_USER_NOTICE);
                }
            } else {
                $send = stream_socket_sendto($this->connections[$socket_id], $message);
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

        $this->debug('Connection closed: #' . $socket_id);

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
        try {
            $ws_codes = $this->wsGetFrameCodes($message);
        } catch (\Throwable $throwable) {
            $this->closeSocket($socket_id);
            throw new \Exception('Failed to read frame data: ' . $throwable->getMessage(), E_USER_NOTICE);
        }

        if (1 !== $ws_codes['masked']) {
            $this->closeSocket($socket_id);
            throw new \Exception('Data unmasked! Close connection!', E_USER_NOTICE);
        }

        switch ($ws_codes['opcode']) {
            case 0x0:
                if (0 === $ws_codes['fin']) {
                    if (!isset($this->data_frames[$socket_id]) || empty($this->data_frames[$socket_id])) {
                        unset($this->data_frames[$socket_id]);
                        throw new \Exception('Continuation frame lost!', E_USER_NOTICE);
                    }

                    $this->data_frames[$socket_id]['data'] .= substr($message, $ws_codes['data_offset'], $ws_codes['data_length']);

                    throw new \Exception('Received continuation frame!', E_USER_NOTICE);
                } else {
                    $message = $this->data_frames[$socket_id]['data'] . $message;

                    $ws_codes['data_mask']   = $this->data_frames[$socket_id]['mask'];
                    $ws_codes['data_offset'] = 0;
                    $ws_codes['data_length'] = strlen($message);

                    unset($this->data_frames[$socket_id]);

                    $this->debug('onMessage debug: All continuation frames received!');
                }
                break;
            case 0x1:
            case 0x2:
                if (0 === $ws_codes['fin']) {
                    $this->data_frames[$socket_id] = [];

                    $this->data_frames[$socket_id]['mask'] = $ws_codes['data_mask'];
                    $this->data_frames[$socket_id]['data'] = substr($message, $ws_codes['data_offset'], $ws_codes['data_length']);

                    throw new \Exception('Received first continuation frame!', E_USER_NOTICE);
                }
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

        try {
            $message = $this->wsDecode($message, $ws_codes['data_mask'], $ws_codes['data_offset'], $ws_codes['data_length']);
        } catch (\Throwable $throwable) {
            $this->closeSocket($socket_id);
            throw new \Exception('Failed to decode frame data: ' . $throwable->getMessage(), E_USER_NOTICE);
        }

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
        try {
            $handshake = true;

            $this->debug('Received handshake message from: #' . $socket_id . '.' . "\r\n" . $message);

            $ws_key   = $this->wsGetHeaderKey($message);
            $ws_proto = $this->wsGetHeaderProto($message);

            if (is_callable($this->callbacks['onHandshake'])) {
                $handshake = (bool)call_user_func($this->callbacks['onHandshake'], $socket_id, $ws_proto);
            }

            if ($handshake) {
                $this->debug('Accept handshake from: #' . $socket_id);
                $this->sendMessage($socket_id, $this->wsBuildHandshake($ws_key, $ws_proto));
                unset($this->handshakes[$socket_id]);
                return;
            }
        } catch (\Throwable $throwable) {
            $this->debug('webSocket onHandshake ERROR: ' . $throwable->getMessage());
            unset($throwable);
        }

        $this->debug('Refuse handshake from: #' . $socket_id);
        $this->sendMessage($socket_id, 'Http/1.1 406 Not Acceptable' . "\r\n\r\n");
        $this->closeSocket($socket_id);

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
     * @param string $buffer
     *
     * @return array
     */
    public function wsGetFrameCodes(string $buffer): array
    {
        $codes = [];
        $char  = ord($buffer[0]);

        $codes['fin']    = $char >> 7;
        $codes['opcode'] = $char & 0x0F;
        $codes['masked'] = ord($buffer[1]) >> 7;

        $payload_length          = (ord($buffer[1]) & 0x7F);
        $codes['payload_length'] = $payload_length;

        switch ($payload_length) {
            case 126:
                $codes['data_offset'] = 8;
                $codes['data_length'] = ((ord($buffer[2]) & 0xFF) << 8) | (ord($buffer[3]) & 0xFF);
                $codes['data_mask']   = substr($buffer, 4, 4);
                break;

            case 127:
                $codes['data_offset'] = 14;
                $codes['data_length'] = (ord($buffer[2]) << 56)
                    | (ord($buffer[3]) << 48)
                    | (ord($buffer[4]) << 40)
                    | (ord($buffer[5]) << 32)
                    | (ord($buffer[6]) << 24)
                    | (ord($buffer[7]) << 16)
                    | (ord($buffer[8]) << 8)
                    | (ord($buffer[7]) << 0);
                $codes['data_mask']   = substr($buffer, 10, 4);
                break;

            default:
                $codes['data_offset'] = 6;
                $codes['data_length'] = $payload_length;
                $codes['data_mask']   = substr($buffer, 2, 4);
                break;
        }

        unset($buffer, $char, $payload_length);
        return $codes;
    }

    /**
     * @param string $buffer
     * @param string $mask
     * @param int    $offset
     * @param int    $length
     *
     * @return string
     */
    public function wsDecode(string $buffer, string $mask, int $offset, int $length): string
    {
        $message   = '';
        $data_body = substr($buffer, $offset, $length);
        $length    = strlen($data_body);

        for ($i = 0; $i < $length; ++$i) {
            $message .= $data_body[$i] ^ $mask[$i % 4];
        }

        unset($buffer, $mask, $offset, $length, $data_body, $i);
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