<?php

/**
 * Socket Manager library
 *
 * Copyright 2016-2023 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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
use Random\RandomException;

class SocketMgr extends Factory
{
    public Error    $error;
    public FiberMgr $fiberMgr;

    public string $master_id = '';

    public string $name      = '';
    public string $address   = '';
    public string $sock_type = '';
    public string $heartbeat = "\n";

    public bool $block_mode = false;
    public bool $debug_mode = false;

    public array $callbacks = [
        'onConnect'    => null,  //callback(string $socket_id): string, response message back to client
        'onHandshake'  => null,  //callback(string $socket_id, string $ws_proto): bool, true to allow, otherwise reject.
        'onHeartbeat'  => null,  //callback(string $socket_id): string, heartbeat message send to $socket_id
        'onMessage'    => null,  //callback(string $socket_id, string $message, bool $is_binary): void
        'onSendBinary' => null,  //callback(string $socket_id): array[binary], message list send to $socket_id, [msg1, msg2, msg3, ...]
        'onSendString' => null,  //callback(string $socket_id): array[string], message list send to $socket_id, [msg1, msg2, msg3, ...]
        'onSendFailed' => null,  //callback(string $socket_id, string $message): void
        'onClose'      => null   //callback(string $socket_id): void
    ];

    public array $options     = [];
    public array $handshakes  = [];
    public array $activities  = [];
    public array $connections = [];
    public array $master_sock = [];
    public array $data_frames = [];

    public array $external_stream   = [];
    public array $external_context  = [];
    public array $external_callback = [];

    public array $connect_opt  = [3, 10]; //retry_times: -1 means always try to connect, 0 means don't reconnect after disconnected
    public array $read_timeout = [0, 500000];

    public int $sending_gap     = 0;
    public int $alive_timeout   = 60;
    public int $max_num_in_loop = 2000;

    /**
     * @throws \ReflectionException
     */
    public function __construct(string $name = '')
    {
        $this->name     = $name;
        $this->error    = Error::new();
        $this->fiberMgr = FiberMgr::new();

        unset($name);
    }

    /**
     * @param bool $debug_mode
     *
     * @return $this
     */
    public function setDebugMode(bool $debug_mode): static
    {
        $this->debug_mode = $debug_mode;

        unset($debug_mode);
        return $this;
    }

    /**
     * @param bool $block_mode
     *
     * @return $this
     */
    public function setBlockMode(bool $block_mode): static
    {
        $this->block_mode = $block_mode;

        unset($block_mode);
        return $this;
    }

    /**
     * @param int $retry_times
     * @param int $wait_seconds
     *
     * @return $this
     */
    public function setConnectOptions(int $retry_times, int $wait_seconds): static
    {
        $this->connect_opt = [$retry_times, $wait_seconds];

        unset($retry_times, $wait_seconds);
        return $this;
    }

    /**
     * @param int      $seconds
     * @param int|null $microseconds
     *
     * @return $this
     */
    public function setReadTimeout(int $seconds, int|null $microseconds = null): static
    {
        $this->read_timeout = [$seconds, $microseconds];

        unset($seconds, $microseconds);
        return $this;
    }

    /**
     * @param int $microseconds
     *
     * @return $this
     */
    public function setSendingGap(int $microseconds): static
    {
        $this->sending_gap = $microseconds;

        unset($microseconds);
        return $this;
    }

    /**
     * @param int $seconds
     *
     * @return $this
     */
    public function setAliveTimeout(int $seconds): static
    {
        $this->alive_timeout = $seconds;

        unset($seconds);
        return $this;
    }

    /**
     * @param int $num_in_loop
     *
     * @return $this
     */
    public function setMaxNumInLoop(int $num_in_loop): static
    {
        $this->max_num_in_loop = $num_in_loop;

        unset($num_in_loop);
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
    public function setSSLCert(string $local_cert, string $local_pk = '', string $passphrase = '', bool $self_signed = false, string $ssl_transport = 'ssl'): static
    {
        $options = [
            'local_cert'          => $local_cert,
            'verify_peer'         => false,
            'ssltransport'        => $ssl_transport,
            'verify_peer_name'    => false,
            'allow_self_signed'   => $self_signed,
            'disable_compression' => true
        ];

        if ('' !== $local_pk) {
            $options['local_pk'] = $local_pk;
        }

        if ('' !== $passphrase) {
            $options['passphrase'] = $passphrase;
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
    public function setContextOptions(string $wrapper, array $options): static
    {
        $this->options[$wrapper] = $options;

        unset($wrapper, $options);
        return $this;
    }

    /**
     * @param string $heartbeat_char
     *
     * @return $this
     */
    public function setHeartbeatChar(string $heartbeat_char): static
    {
        $this->heartbeat = $heartbeat_char;

        unset($heartbeat_char);
        return $this;
    }

    /**
     * @param array $proc_context
     * @param array $pipe_callbacks
     *
     * @return $this
     */
    public function addExternalProc(array $proc_context, array $pipe_callbacks): static
    {
        foreach ($proc_context as $key => $item) {
            if (is_resource($item) && isset($pipe_callbacks[$key]) && is_callable($pipe_callbacks[$key])) {
                $ext_id = 'ext_' . get_resource_id($item);

                $this->external_stream[$ext_id]   = $item;
                $this->external_context[$ext_id]  = $proc_context + ['ext_id' => $ext_id];
                $this->external_callback[$ext_id] = $pipe_callbacks[$key];
            }
        }

        unset($proc_context, $pipe_callbacks, $key, $item, $ext_id);
        return $this;
    }

    /**
     * @param string $ext_id
     *
     * @return $this
     */
    public function closeExternalProc(string $ext_id): static
    {
        foreach ($this->external_context[$ext_id] as $key => $item) {
            if (!is_resource($item)) {
                continue;
            }

            $type = get_resource_type($item);

            switch ($type) {
                case 'stream':
                    fclose($this->external_context[$ext_id][$key]);
                    break;
                case 'process':
                    proc_close($this->external_context[$ext_id][$key]);
                    break;
            }
        }

        unset($this->external_stream[$ext_id], $this->external_context[$ext_id], $this->external_callback[$ext_id], $ext_id, $key, $item, $type);
        return $this;
    }

    /**
     * @param string $ext_id
     *
     * @return void
     */
    public function runExternalCallback(string $ext_id): void
    {
        if (is_callable($this->external_callback[$ext_id])) {
            try {
                call_user_func($this->external_callback[$ext_id], $ext_id, $this->external_context[$ext_id]);
            } catch (\Throwable $throwable) {
                $this->debug('External callback ERROR: #' . $ext_id . ' -> ' . $throwable->getMessage());
                unset($throwable);
            }
        }

        if (isset($this->external_stream[$ext_id])
            && is_resource($this->external_stream[$ext_id])
            && feof($this->external_stream[$ext_id])
        ) {
            $this->closeExternalProc($ext_id);
            $this->debug('External stream closed, cleaned: #' . $ext_id);
        }

        unset($ext_id);
    }

    /**
     * @param string   $event
     * @param callable $callback
     *
     * @return $this
     * @throws \Exception
     */
    public function setEventListener(string $event, callable $callback): static
    {
        if (!array_key_exists($event, $this->callbacks)) {
            throw new \Exception('"' . $event . '" NOT accept!', E_USER_ERROR);
        }

        $this->callbacks[$event] = $callback;

        unset($event, $callback);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onConnect(callable $callback_func): static
    {
        $this->callbacks['onConnect'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onHandshake(callable $callback_func): static
    {
        $this->callbacks['onHandshake'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onHeartbeat(callable $callback_func): static
    {
        $this->callbacks['onHeartbeat'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onMessage(callable $callback_func): static
    {
        $this->callbacks['onMessage'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onSendBinary(callable $callback_func): static
    {
        $this->callbacks['onSendBinary'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onSendString(callable $callback_func): static
    {
        $this->callbacks['onSendString'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onSendFailed(callable $callback_func): static
    {
        $this->callbacks['onSendFailed'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param callable $callback_func
     *
     * @return $this
     */
    public function onClose(callable $callback_func): static
    {
        $this->callbacks['onClose'] = $callback_func;

        unset($callback_func);
        return $this;
    }

    /**
     * @param string $address
     * @param bool   $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function listenTo(string $address, bool $is_websocket = false): void
    {
        $this->address = $address;
        $this->createServer($address);

        if ('udp' !== $this->sock_type) {
            $this->fiberMgr->async([$this, 'serverOnTCPChange'], [$is_websocket]);
        } else {
            $this->connections = $this->master_sock;
            $this->fiberMgr->async([$this, 'serverOnUDPMessage']);
        }

        $this->fiberMgr->async([$this, 'serverOnHeartbeat'], [$is_websocket]);
        $this->fiberMgr->async([$this, 'serverOnSend'], [$is_websocket]);

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

        $this->master_id   = 'sock_' . get_resource_id($master_socket);
        $this->master_sock = [$this->master_id => $master_socket];

        $this->debug('Server started! Listen to ' . $address . '. ID: #' . $this->master_id);

        unset($address, $context, $flags, $master_socket);
    }

    /**
     * @param bool $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function serverOnTCPChange(bool $is_websocket = false): void
    {
        $write = $except = [];

        while (true) {
            $count = 0;
            $read  = $this->master_sock + $this->connections + $this->external_stream;
            $read  = $this->getValidResources($read);

            $result = stream_select($read, $write, $except, $this->read_timeout[0], $this->read_timeout[1]);

            if (false === $result) {
                $this->debug('Select error, invalid stream may exist.');
                \Fiber::suspend();
                continue;
            }

            if (0 === $result) {
                \Fiber::suspend();
                continue;
            }

            foreach ($read as $socket_id => $socket) {
                if (++$count > $this->max_num_in_loop) {
                    $count = 0;
                    \Fiber::suspend();
                }

                //Accept new connection
                if ($socket_id === $this->master_id) {
                    try {
                        $client = stream_socket_accept($socket, 1);
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

                    $client_id = 'sock_' . get_resource_id($client);

                    if ($is_websocket) {
                        $this->handshakes[$client_id] = false;
                    }

                    $now_time = time();

                    $this->activities[$client_id]  = [$now_time, $now_time];
                    $this->connections[$client_id] = $client;

                    $this->debug('Client connected: #' . $client_id);

                    if (is_callable($this->callbacks['onConnect'])) {
                        try {
                            $response = call_user_func($this->callbacks['onConnect'], $client_id);

                            if (!$is_websocket && is_string($response) && '' !== $response) {
                                $this->sendMessage($client_id, $response);
                            }
                        } catch (\Throwable $throwable) {
                            $this->debug('serverOnConnect callback ERROR: ' . $throwable->getMessage());
                            $this->error->exceptionHandler($throwable, false, false);
                            unset($throwable);
                        }
                    }

                    continue;
                }

                if (str_starts_with($socket_id, 'sock_')) {
                    // Close disconnected client
                    if (feof($socket)) {
                        $this->debug('Socket EOF detected, closing...');
                        $this->closeSocket($socket_id);
                        continue;
                    }

                    // Process message only if connection exists
                    if (isset($this->connections[$socket_id])) {
                        $this->serverProcessMessage($socket_id, $is_websocket);
                    }
                } else {
                    $this->runExternalCallback($socket_id);
                }
            }
        }
    }

    /**
     * @return void
     * @throws \Throwable
     */
    public function serverOnUDPMessage(): void
    {
        $write = $except = [];

        while (true) {
            $count = 0;
            $read  = $this->connections + $this->external_stream;
            $read  = $this->getValidResources($read);

            $result = stream_select($read, $write, $except, $this->read_timeout[0], $this->read_timeout[1]);

            if (false === $result) {
                $this->debug('Select error, invalid stream may exist.');
                \Fiber::suspend();
                continue;
            }

            if (0 === $result) {
                \Fiber::suspend();
                continue;
            }

            foreach ($read as $socket_id => $client) {
                if (++$count > $this->max_num_in_loop) {
                    $count = 0;
                    \Fiber::suspend();
                }

                if (str_starts_with($socket_id, 'sock_')) {
                    $this->serverProcessMessage($socket_id);
                } else {
                    $this->runExternalCallback($socket_id);
                }
            }
        }
    }

    /**
     * @param bool $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function serverOnHeartbeat(bool $is_websocket = false): void
    {
        $client_timeout = $this->alive_timeout * 1.5;

        while (true) {
            $count    = 0;
            $now_time = time();

            foreach ($this->activities as $socket_id => $active_times) {
                if (++$count > $this->max_num_in_loop) {
                    $count = 0;
                    \Fiber::suspend();
                }

                if ($now_time - $active_times[0] < $this->alive_timeout) {
                    continue;
                }

                if ($now_time - $active_times[1] > $client_timeout) {
                    $this->debug('Client heartbeat lost: #' . $socket_id);
                    $this->closeSocket($socket_id);
                    continue;
                }

                $heartbeat = '';

                $this->activities[$socket_id][0] = $now_time;
                $this->activities[$socket_id][1] = $now_time;

                if ($is_websocket) {
                    $this->wsPing($socket_id);
                    $this->debug('Send websocket heartbeat to client: #' . $socket_id);
                } elseif (!is_callable($this->callbacks['onHeartbeat'])) {
                    $heartbeat = $this->heartbeat;
                }

                if (is_callable($this->callbacks['onHeartbeat'])) {
                    try {
                        $heartbeat = call_user_func($this->callbacks['onHeartbeat'], $socket_id);
                    } catch (\Throwable $throwable) {
                        $this->debug('serverOnHeartbeat callback ERROR: ' . $throwable->getMessage());
                        $this->error->exceptionHandler($throwable, false, false);
                        unset($throwable);
                    }
                }

                if ('' !== $heartbeat) {
                    $this->sendMessage($socket_id, $heartbeat);
                    $this->debug('Send heartbeat message to client: #' . $socket_id);
                }
            }

            \Fiber::suspend();
        }
    }

    /**
     * @param bool $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function serverOnSend(bool $is_websocket = false): void
    {
        if (!is_callable($this->callbacks['onSendBinary']) && !is_callable($this->callbacks['onSendString'])) {
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
                if (++$count > $this->max_num_in_loop) {
                    $count = 0;
                    \Fiber::suspend();
                }

                $msg_list  = [];
                $is_binary = true;

                try {
                    foreach ([$this->callbacks['onSendBinary'], $this->callbacks['onSendString']] as $id => $callback) {
                        if (!is_callable($callback)) {
                            continue;
                        }

                        $is_binary = 0 === $id;
                        $msg_list  = call_user_func($callback, $socket_id);

                        if (is_array($msg_list)) {
                            break;
                        }

                        throw new \ErrorException('onSendBinary/onSendString callback must return message data in array!');
                    }
                } catch (\Throwable $throwable) {
                    $this->debug('serverOnSend callback ERROR: ' . $throwable->getMessage());
                    $this->error->exceptionHandler($throwable, false, false);
                    unset($throwable);
                    continue;
                }

                foreach ($msg_list as $raw_msg) {
                    try {
                        $this->sendMessage($socket_id, $is_websocket ? $this->wsEncode($raw_msg, $is_binary) : $raw_msg);
                        $this->debug('Send message: ' . ($is_binary ? 'Binary Data' : $raw_msg) . ' to #' . $socket_id);

                        if (0 < $this->sending_gap) {
                            usleep($this->sending_gap);
                        }
                    } catch (\Throwable) {
                        if (!is_callable($this->callbacks['onSendFailed'])) {
                            continue;
                        }

                        try {
                            call_user_func($this->callbacks['onSendFailed'], $socket_id, $is_binary ? 'Binary Data' : $raw_msg);
                        } catch (\Throwable $throwable) {
                            $this->debug('serverOnSendFailed callback ERROR: ' . $throwable->getMessage());
                            $this->error->exceptionHandler($throwable, false, false);
                            unset($throwable);
                        }
                    }
                }
            }

            \Fiber::suspend();
        }
    }

    /**
     * @param string $socket_id
     * @param bool   $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     */
    public function serverProcessMessage(string $socket_id, bool $is_websocket = false): void
    {
        try {
            $is_binary = false;
            $message   = $this->readMessage($socket_id, $is_websocket, $is_binary);

            // Skip empty message (e.g., from Ping/Pong frames)
            if ('' === $message && $is_websocket && !isset($this->handshakes[$socket_id])) {
                return;
            }

            if ($is_websocket && isset($this->handshakes[$socket_id])) {
                $this->wsSendHandshake($socket_id, $message);
                return;
            }
        } catch (\Throwable $throwable) {
            $this->debug('serverOnMessage debug: ' . $throwable->getMessage());
            unset($throwable);
            return;
        }

        $log_msg = $is_binary ? '[BINARY DATA]' : $message;
        $this->debug('Read message from #' . $socket_id . ': ' . $log_msg);

        if (is_callable($this->callbacks['onMessage'])) {
            try {
                call_user_func($this->callbacks['onMessage'], $socket_id, $message, $is_binary);
            } catch (\Throwable $throwable) {
                $this->debug('serverOnMessage callback ERROR: ' . $throwable->getMessage());
                $this->error->exceptionHandler($throwable, false, false);

                unset($throwable);
            }
        }

        unset($socket_id, $is_websocket, $message, $is_binary);
    }

    /**
     * Connect to a server (TCP or WebSocket).
     *
     * @param string $address      Address to connect to (e.g., tcp://... or ws://...)
     * @param bool   $is_websocket Whether this is a WebSocket connection
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function connectTo(string $address, bool $is_websocket = false): void
    {
        $this->address = $address;

        if ($is_websocket) {
            $this->createWSClient($address);
        } else {
            $this->createClient($address);
        }

        $this->fiberMgr->async([$this, 'clientOnMessage'], [$is_websocket]);
        $this->fiberMgr->async([$this, 'clientOnHeartbeat'], [$is_websocket]);
        $this->fiberMgr->async([$this, 'clientOnSend'], [$is_websocket]);
        $this->fiberMgr->commit();

        unset($address, $is_websocket);
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
        $this->master_id   = 'sock_' . get_resource_id($master_socket);
        $this->master_sock = [$this->master_id => $master_socket];
        $this->connections = [$this->master_id => $master_socket];
        $this->activities  = [$this->master_id => [$now_time, $now_time]];

        $this->debug('Client started! Connect to ' . $address . '. ID: #' . $this->master_id);

        if (is_callable($this->callbacks['onConnect'])) {
            try {
                $response = call_user_func($this->callbacks['onConnect'], $this->master_id);

                if (is_string($response) && '' !== $response) {
                    $this->sendMessage($this->master_id, $response);
                }

                unset($response);
            } catch (\Throwable $throwable) {
                $this->debug('clientOnConnect callback ERROR: ' . $throwable->getMessage());
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
            }
        }

        unset($address, $context, $flags, $master_socket, $now_time);
    }

    /**
     * Establish a WebSocket client connection (supports ws:// and wss://).
     *
     * @param string $address WebSocket URL
     *
     * @return void
     * @throws \Exception
     */
    public function createWSClient(string $address): void
    {
        $parts = parse_url($address);

        if (!isset($parts['host'])) {
            throw new \Exception('Invalid WebSocket address');
        }

        $scheme = $parts['scheme'] ?? 'ws';
        $host   = $parts['host'];
        $port   = $parts['port'] ?? (('wss' === $scheme) ? 443 : 9222);
        $path   = $parts['path'] ?? '/';

        if (isset($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        $transport = ('wss' === $scheme) ? 'ssl' : 'tcp';
        $context   = stream_context_create();

        if (!empty($this->options)) {
            if (!stream_context_set_params($context, ['options' => $this->options])) {
                throw new \Exception('Failed to set context options!', E_USER_ERROR);
            }
        }

        $socket = stream_socket_client(
            $transport . '://' . $host . ':' . $port,
            $errno,
            $errstr,
            60,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (false === $socket) {
            throw new \Exception('WebSocket connection failed: ' . $errstr . ' (' . $errno . ')');
        }

        stream_set_blocking($socket, true);

        // WebSocket handshake
        $ws_key  = base64_encode(random_bytes(16));
        $request = 'GET ' . $path . ' HTTP/1.1' . "\r\n"
            . 'Host: ' . $host . ':' . $port . "\r\n"
            . 'Upgrade: websocket' . "\r\n"
            . 'Connection: Upgrade' . "\r\n"
            . 'Sec-WebSocket-Key: ' . $ws_key . "\r\n"
            . 'Sec-WebSocket-Version: 13' . "\r\n"
            . "\r\n";

        fwrite($socket, $request);

        $response = '';

        while (false !== ($line = fgets($socket))) {
            $response .= $line;
            if ('' === trim($line)) {
                break;
            }
        }

        stream_set_blocking($socket, $this->block_mode);

        if (!str_contains($response, '101')) {
            fclose($socket);
            throw new \Exception('WebSocket handshake failed');
        }

        $now = time();

        $this->master_id   = 'sock_' . get_resource_id($socket);
        $this->master_sock = [$this->master_id => $socket];
        $this->connections = [$this->master_id => $socket];
        $this->activities  = [$this->master_id => [$now, $now]];
        // Mark as WebSocket client (handshakes = true)
        $this->handshakes[$this->master_id] = true;

        $this->debug('WebSocket client connected to ' . $address . '. ID: #' . $this->master_id);

        unset($address, $parts, $scheme, $host, $port, $path, $transport, $context, $socket, $errno, $errstr, $ws_key, $request, $response, $line, $now);
    }

    /**
     * @param bool $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function clientOnMessage(bool $is_websocket = false): void
    {
        $write = $except = [];

        while (true) {
            $servers = $this->master_sock + $this->external_stream;
            $servers = $this->getValidResources($servers);

            $result = stream_select($servers, $write, $except, $this->read_timeout[0], $this->read_timeout[1]);

            if (false === $result) {
                $this->debug('Server error while reading, reconnecting...');
                $this->clientReconnect();
                continue;
            }

            if (0 === $result) {
                \Fiber::suspend();
                continue;
            }

            foreach ($servers as $socket_id => $socket) {
                if (str_starts_with($socket_id, 'sock_')) {
                    if (feof($socket)) {
                        $this->debug('Socket EOF detected, reconnecting...');
                        unset($this->master_sock[$this->master_id]);
                        $this->closeSocket($this->master_id);
                        $this->clientReconnect();
                        continue;
                    }

                    try {
                        $is_binary = false;
                        $message   = $this->readMessage($this->master_id, $is_websocket, $is_binary);
                    } catch (\Throwable $throwable) {
                        $this->debug('Read message failed: ' . $throwable->getMessage());
                        $this->clientReconnect();
                        unset($throwable);
                        continue;
                    }

                    $this->debug('Read message from server: ' . $message);

                    if (is_callable($this->callbacks['onMessage'])) {
                        try {
                            call_user_func($this->callbacks['onMessage'], $this->master_id, $message, $is_binary);
                        } catch (\Throwable $throwable) {
                            $this->debug('Client message callback ERROR: ' . $throwable->getMessage());
                            $this->error->exceptionHandler($throwable, false, false);
                            unset($throwable);
                        }
                    }
                } else {
                    $this->runExternalCallback($socket_id);
                }
            }

            \Fiber::suspend();
        }
    }

    /**
     * @param bool $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function clientOnHeartbeat(bool $is_websocket = false): void
    {
        $check_time = $this->alive_timeout / 2;

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

            if ($now_time - $last_time < $check_time) {
                \Fiber::suspend();
                continue;
            }

            try {
                if ($is_websocket) {
                    $this->wsPing($this->master_id);
                } else {
                    $this->sendMessage($this->master_id, $heartbeat);
                }

                $this->activities[$this->master_id][1] = $now_time;
                $this->debug('Send heartbeat to server');
            } catch (\Throwable) {
                $this->clientReconnect();
            }
        }
    }

    /**
     * @param bool $is_websocket
     *
     * @return void
     * @throws \ReflectionException
     * @throws \Throwable
     */
    public function clientOnSend(bool $is_websocket = false): void
    {
        if (!is_callable($this->callbacks['onSendBinary']) && !is_callable($this->callbacks['onSendString'])) {
            return;
        }

        while (true) {
            $msg_list = [];

            try {
                foreach ([$this->callbacks['onSendBinary'], $this->callbacks['onSendString']] as $callback) {
                    if (!is_callable($callback)) {
                        continue;
                    }

                    $msg_list = call_user_func($callback, $this->master_id);

                    if (is_array($msg_list)) {
                        break;
                    }

                    throw new \ErrorException('onSendBinary/onSendString callback must return message data in array!');
                }
            } catch (\Throwable $throwable) {
                $this->debug('clientOnSend callback ERROR: ' . $throwable->getMessage());
                $this->error->exceptionHandler($throwable, false, false);
                unset($throwable);
                break;
            }

            foreach ($msg_list as $raw_msg) {
                try {
                    $to_send = $is_websocket ? $this->wsEncode($raw_msg, false, true) : $raw_msg;
                    $this->sendMessage($this->master_id, $to_send);
                    $this->debug('Send message to server: ' . $raw_msg);

                    if (0 < $this->sending_gap) {
                        usleep($this->sending_gap);
                    }
                } catch (\Throwable) {
                    $this->clientReconnect();

                    if (!is_callable($this->callbacks['onSendFailed'])) {
                        continue;
                    }

                    try {
                        call_user_func($this->callbacks['onSendFailed'], $this->master_id, $raw_msg);
                    } catch (\Throwable $throwable) {
                        $this->debug('clientOnSendFailed callback ERROR: ' . $throwable->getMessage());
                        $this->error->exceptionHandler($throwable, false, false);
                        unset($throwable);
                    }
                }
            }

            \Fiber::suspend();
        }
    }

    /**
     * @return void
     */
    public function clientReconnect(): void
    {
        switch ($this->connect_opt[0]) {
            case 0:
                $this->debug('Reconnect failed, quit!');
                exit(0);

            case -1:
                while (true) {
                    $this->debug('Reconnecting...');

                    sleep($this->connect_opt[1]);

                    try {
                        $this->createClient($this->address);
                        return;
                    } catch (\Throwable $throwable) {
                        $this->debug('Reconnect failed: ' . $throwable->getMessage());
                        unset($throwable);
                    }
                }

            default:
                for ($i = 1; $i <= $this->connect_opt[0]; $i++) {
                    $this->debug('Reconnecting...');

                    sleep($this->connect_opt[1]);

                    try {
                        $this->createClient($this->address);
                        return;
                    } catch (\Throwable $throwable) {
                        $this->debug('Reconnect failed (' . $i . '/' . $this->connect_opt[0] . '): ' . $throwable->getMessage());
                        unset($throwable);
                    }
                }

                $this->debug('Reconnect failed ' . $this->connect_opt[0] . ' times, quit!');
                exit(0);
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
     * @param bool   $is_websocket
     * @param bool & $is_binary
     *
     * @return string
     * @throws \ReflectionException
     * @throws \Exception
     */
    public function readMessage(string $socket_id, bool $is_websocket, bool &$is_binary = false): string
    {
        try {
            if ('udp' !== $this->sock_type) {
                if ($is_websocket) {
                    // WebSocket frame mode
                    if (!isset($this->handshakes[$socket_id]) || true === $this->handshakes[$socket_id]) {
                        // Normal message
                        $frame = $this->wsReadFrame($socket_id);

                        if (!$frame['is_complete']) {
                            return '';
                        }

                        $is_binary = (0x2 === $frame['opcode']);
                        $message   = $frame['data'];

                        unset($frame);
                    } else {
                        // Handshake message
                        $message = '';

                        while (false !== ($msg_line = fgets($this->connections[$socket_id]))) {
                            $message .= $msg_line;
                        }

                        $this->activities[$socket_id][1] = time();
                    }
                } else {
                    // TCP mode or WebSocket handshake
                    $message   = '';
                    $is_binary = false;

                    while (false !== $msg_line = fgets($this->connections[$socket_id])) {
                        $message .= $msg_line;
                    }

                    $this->activities[$socket_id][1] = time();

                    unset($msg_line);
                }
            } else {
                $message   = stream_socket_recvfrom($this->connections[$socket_id], 65536) ?: '';
                $is_binary = false;
            }

            if ('' === $message && !$is_websocket) {
                throw new \Exception('No message received', E_NOTICE);
            }
        } catch (\Throwable $throwable) {
            $this->closeSocket($socket_id);
            throw new \Exception('Read ERROR: ' . $throwable->getMessage(), E_USER_NOTICE);
        }

        unset($socket_id, $is_websocket);
        return $message;
    }

    /**
     * @param string $socket_id
     * @param string $message
     *
     * @return void
     * @throws \ReflectionException
     */
    public function sendMessage(string $socket_id, string $message): void
    {
        try {
            if ('udp' !== $this->sock_type) {
                if (false === fwrite($this->connections[$socket_id], $message)) {
                    throw new \Exception($socket_id . ' lost connection!', E_USER_NOTICE);
                }

                $this->activities[$socket_id][1] = time();
            } else {
                stream_socket_sendto($this->connections[$socket_id], $message);
            }
        } catch (\Throwable $throwable) {
            $this->debug('Send message ERROR: ' . $throwable->getMessage());
            $this->closeSocket($socket_id);
            unset($throwable);
        }

        unset($socket_id, $message);
    }

    /**
     * @param string $socket_id
     * @param string $message
     *
     * @return void
     * @throws \ReflectionException
     */
    public function sendWsMessage(string $socket_id, string $message): void
    {
        $this->sendMessage($socket_id, $this->wsEncode($message));

        unset($socket_id, $message);
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

        unset($this->connections[$socket_id], $this->activities[$socket_id], $this->handshakes[$socket_id], $this->data_frames[$socket_id]);

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
     * @param array $resources
     *
     * @return array
     */
    public function getValidResources(array $resources): array
    {
        foreach ($resources as $socket_id => $resource) {
            if (!is_resource($resource)) {
                unset($resources[$socket_id]);

                if (str_starts_with($socket_id, 'ext_')) {
                    unset($this->external_stream[$socket_id], $this->external_context[$socket_id], $this->external_callback[$socket_id]);
                    $this->debug('External stream removed (invalid resource): #' . $socket_id);
                } elseif (str_starts_with($socket_id, 'sock_') && $socket_id !== $this->master_id) {
                    unset($this->connections[$socket_id], $this->activities[$socket_id], $this->handshakes[$socket_id], $this->data_frames[$socket_id]);
                    $this->debug('Client removed (invalid resource): #' . $socket_id);
                }
            } elseif (str_starts_with($socket_id, 'ext_') && feof($resource)) {
                unset($resources[$socket_id], $this->external_stream[$socket_id], $this->external_context[$socket_id], $this->external_callback[$socket_id]);
                $this->debug('External stream removed (EOF reached): #' . $socket_id);
            }
        }

        unset($socket_id, $resource);
        return $resources;
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
     * @param string $socket_id
     *
     * @return array ['data' => string, 'opcode' => int, 'is_complete' => bool]
     * @throws \Exception
     */
    public function wsReadFrame(string $socket_id): array
    {
        // Read frame header
        $header = fread($this->connections[$socket_id], 2);

        if (2 !== strlen($header)) {
            throw new \Exception('Failed to read frame header', E_NOTICE);
        }

        $fin         = (ord($header[0]) >> 7) & 0x01;
        $opcode      = ord($header[0]) & 0x0F;
        $masked      = (ord($header[1]) >> 7) & 0x01;
        $payload_len = ord($header[1]) & 0x7F;

        unset($header);

        if (126 === $payload_len) {
            $extended = fread($this->connections[$socket_id], 2);

            if (2 !== strlen($extended)) {
                throw new \Exception('Failed to read extended length', E_NOTICE);
            }

            $payload_len = unpack('n', $extended)[1];

            unset($extended);
        } elseif (127 === $payload_len) {
            $extended = fread($this->connections[$socket_id], 8);

            if (8 !== strlen($extended)) {
                throw new \Exception('Failed to read extended length', E_NOTICE);
            }

            // Use unpack('J') for 64-bit unsigned big-endian
            $payload_len = unpack('J', $extended)[1];

            unset($extended);
        }

        // Read mask key if present
        if (1 === $masked) {
            $mask_key = fread($this->connections[$socket_id], 4);

            if (4 !== strlen($mask_key)) {
                throw new \Exception('Failed to read mask key', E_NOTICE);
            }
        } else {
            $mask_key = '';
        }

        // Read payload data in chunks
        $payload    = '';
        $remaining  = $payload_len;
        $chunk_size = 65536; // 64KB per chunk

        $write  = $except = [];
        $client = [$this->connections[$socket_id]];

        while (0 < $remaining) {
            $read_size = min($remaining, $chunk_size);
            $chunk     = fread($this->connections[$socket_id], $read_size);

            if (false === $chunk) {
                throw new \Exception('Failed to read payload data', E_NOTICE);
            }

            $len = strlen($chunk);

            if (0 < $len) {
                $payload   .= $chunk;
                $remaining -= $len;

                unset($read_size, $chunk, $len);
                continue;
            }

            // No data available yet, wait for socket to become readable
            $read   = $client;
            $result = stream_select($read, $write, $except, $this->read_timeout[0], $this->read_timeout[1]);

            if (false === $result) {
                throw new \Exception('Read error while waiting for payload data', E_NOTICE);
            }

            if (0 === $result) {
                throw new \Exception('Timeout while waiting for payload data', E_NOTICE);
            }
        }

        // Decode mask if needed
        if (1 === $masked && 0 < $payload_len) {
            for ($i = 0; $i < $payload_len; ++$i) {
                $payload[$i] = $payload[$i] ^ $mask_key[$i % 4];
            }
        }

        $this->activities[$socket_id][1] = time();

        // Handle control frames (Ping/Pong/Close)
        switch ($opcode) {
            case 0x8:  // Close frame
                $this->closeSocket($socket_id);
                throw new \Exception('Connection closed by Client!', E_USER_NOTICE);

            case 0x9:  // Ping frame
                $this->wsPong($socket_id);
                $result = ['data' => '', 'opcode' => $opcode, 'is_complete' => true];
                break;

            case 0xA:  // Pong frame
                $result = ['data' => '', 'opcode' => $opcode, 'is_complete' => true];
                break;

            case 0x0:  // Continuation frame
            case 0x1:  // Text frame
            case 0x2:  // Binary frame
                // Handle fragmentation
                if (0 === $fin) {
                    // Not final frame, store in buffer
                    if (!isset($this->data_frames[$socket_id])) {
                        $this->data_frames[$socket_id] = [
                            'opcode' => $opcode,
                            'data'   => ''
                        ];
                    } elseif (0x0 === $opcode) {
                        // Continuation frame, keep original opcode
                    } else {
                        // New frame before previous finished, reset buffer
                        $this->data_frames[$socket_id] = [
                            'opcode' => $opcode,
                            'data'   => ''
                        ];
                    }

                    $this->data_frames[$socket_id]['data'] .= $payload;
                    $this->debug('Received fragment frame (fin=0), opcode=' . $opcode . ', length=' . $payload_len);

                    $result = ['data' => '', 'opcode' => $opcode, 'is_complete' => false];
                    break;
                }

                // Final frame (fin = 1)
                if (isset($this->data_frames[$socket_id]) && !empty($this->data_frames[$socket_id]['data'])) {
                    // Complete fragmented message
                    $complete_data   = $this->data_frames[$socket_id]['data'] . $payload;
                    $original_opcode = $this->data_frames[$socket_id]['opcode'];

                    if (0x0 === $original_opcode) {
                        $original_opcode = 0x1;
                    }

                    unset($this->data_frames[$socket_id]);

                    $this->debug('All fragments received, reassembled ' . strlen($complete_data) . ' bytes');

                    $result = ['data' => $complete_data, 'opcode' => $original_opcode, 'is_complete' => true];
                    break;
                }

                if (0x0 === $opcode) {
                    // Continuation frame as final but no previous data (protocol error)
                    $this->debug('Warning: Continuation frame without previous data');
                    unset($this->data_frames[$socket_id]);

                    $result = ['data' => '', 'opcode' => $opcode, 'is_complete' => true];
                    break;
                }

                // Single frame (no fragmentation)
                $result = ['data' => $payload, 'opcode' => $opcode, 'is_complete' => true];
                break;

            default:
                $this->debug('Unsupported opcode: ' . $opcode);
                $result = ['data' => '', 'opcode' => $opcode, 'is_complete' => true];
                break;
        }

        unset($socket_id, $masked, $payload_len, $mask_key, $remaining, $chunk_size, $payload, $opcode, $fin);
        return $result;
    }

    /**
     * @param string $message
     * @param bool   $binary
     * @param bool   $mask
     *
     * @return string
     * @throws RandomException
     */
    public function wsEncode(string $message, bool $binary = false, bool $mask = false): string
    {
        $length   = strlen($message);
        $opcode   = $binary ? 0x82 : 0x81;
        $header   = chr($opcode);
        $mask_bit = $mask ? 0x80 : 0x00;

        if ($length <= 125) {
            $header .= chr($length | $mask_bit);
        } elseif ($length <= 65535) {
            $header .= chr(126 | $mask_bit) . pack('n', $length);
        } else {
            $header .= chr(127 | $mask_bit) . pack('J', $length);
        }

        if ($mask) {
            $mask_key = random_bytes(4);
            $header   .= $mask_key;
            for ($i = 0; $i < $length; ++$i) {
                $message[$i] = $message[$i] ^ $mask_key[$i % 4];
            }
        }

        $result = $header . $message;
        unset($message, $binary, $mask, $length, $opcode, $mask_bit, $mask_key, $i);
        return $result;
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