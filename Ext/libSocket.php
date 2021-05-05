<?php

/**
 * Socket Extension
 *
 * Copyright 2016-2021 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Factory;

/**
 * Class libSocket
 *
 * @package Ext
 */
class libSocket extends Factory
{
    public int    $watch_sec  = 10;
    public string $sock_proto = 'tcp';
    public string $sock_addr  = '0.0.0.0';
    public string $sock_port  = '2468';

    public string $local_pk    = '';
    public string $local_cert  = '';
    public string $passphrase  = '';
    public bool   $self_signed = false;

    public int    $heartbeat_sec = 10;
    public string $heartbeat_val = '';

    public array $socket_master  = [];
    public array $socket_clients = [];
    public array $socket_actives = [];

    public string $master_id  = '';
    public string $log_levels = 'error, start';

    /**
     * Set socket addr options (protocol, address, port)
     *
     * @param string $address
     * @param string $port
     * @param string $protocol
     *
     * @return $this
     */
    public function setAddr(string $address, string $port, string $protocol = 'tcp'): self
    {
        $this->sock_addr  = &$address;
        $this->sock_port  = &$port;
        $this->sock_proto = &$protocol;

        unset($address, $port, $protocol);
        return $this;
    }

    /**
     * Set socket watch wait seconds
     *
     * @param int $watch_sec
     *
     * @return $this
     */
    public function setWatchSec(int $watch_sec): self
    {
        $this->watch_sec = &$watch_sec;

        unset($watch_sec);
        return $this;
    }

    /**
     * Set SSL certificate options
     *
     * @param string $local_cert
     * @param string $local_pk
     * @param string $passphrase
     * @param bool   $self_signed
     *
     * @return $this
     */
    public function setCert(string $local_cert, string $local_pk = '', string $passphrase = '', bool $self_signed = false): self
    {
        $this->local_cert  = &$local_cert;
        $this->local_pk    = &$local_pk;
        $this->passphrase  = &$passphrase;
        $this->self_signed = &$self_signed;

        unset($local_cert, $local_pk, $passphrase, $self_signed);
        return $this;
    }

    /**
     * Set heartbeat options (heartbeat_val, heartbeat_sec)
     *
     * @param string $heartbeat_val
     * @param int    $heartbeat_sec
     *
     * @return $this
     */
    public function setHeartbeat(string $heartbeat_val, int $heartbeat_sec = 10): self
    {
        $this->heartbeat_val = &$heartbeat_val;
        $this->heartbeat_sec = &$heartbeat_sec;

        unset($heartbeat_val, $heartbeat_sec);
        return $this;
    }

    /**
     * Set socket log levels (Error, Start, Listen, Connect, Handshake, Heartbeat, Receive, Send, Close, Exit)
     *
     * @param string $levels
     *
     * @return $this
     */
    public function setLogLevels(string $levels): self
    {
        $this->log_levels = &$levels;

        unset($levels);
        return $this;
    }

    /**
     * Generate online socket ID
     *
     * @return string
     */
    public function genId(): string
    {
        $sock_id = substr(hash('md5', uniqid(microtime() . (string)mt_rand(), true)), 8, 16);
        return !isset($this->clients[$sock_id]) ? $sock_id : $this->genId();
    }

    /**
     * Show socket logs
     *
     * @param string $log_level
     * @param string $log_msg
     */
    public function showLog(string $log_level, string $log_msg): void
    {
        if (false !== stripos($this->log_levels, $log_level)) {
            echo date('Y-m-d H:i:s') . ' [' . ucfirst($log_level) . '] ' . strtr($log_msg, ["\r" => '\\r', "\n" => '\\n']) . PHP_EOL;
        }

        unset($log_level, $log_msg);
    }

    /**
     * Run server/client
     *
     * @param bool $as_client
     *
     * @return bool
     */
    public function run(bool $as_client = false): bool
    {
        $context = stream_context_create();

        if ('' !== $this->local_cert) {
            stream_context_set_option($context, 'ssl', 'local_cert', $this->local_cert);
            stream_context_set_option($context, 'ssl', 'ssltransport', $this->sock_proto);
            stream_context_set_option($context, 'ssl', 'allow_self_signed', $this->self_signed);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'disable_compression', true);

            '' !== $this->local_pk && stream_context_set_option($context, 'ssl', 'local_pk', $this->local_pk);
            '' !== $this->passphrase && stream_context_set_option($context, 'ssl', 'passphrase', $this->passphrase);
        }

        $address = $this->sock_proto . '://' . $this->sock_addr . ':' . $this->sock_port;

        $socket = !$as_client
            ? stream_socket_server(
                $address,
                $errno,
                $errstr,
                'udp' != $this->sock_proto ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN : STREAM_SERVER_BIND,
                $context
            )
            : stream_socket_client(
                $address,
                $errno,
                $errstr,
                $this->watch_sec,
                'udp' != $this->sock_proto ? STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT : STREAM_CLIENT_CONNECT,
                $context
            );

        unset($context);

        if (false === $socket) {
            $this->showLog('error', $errno . ': ' . $errstr);
            return false;
        }

        $this->master_id      = $this->genId();
        $this->socket_master  = [$this->master_id => &$socket];
        $this->socket_clients = [$this->master_id => &$socket];

        $this->showLog('start', 'Socket ' . (!$as_client ? 'server' : 'client') . ' on "' . $address . '"');

        unset($as_client, $address, $socket, $errno, $errstr);
        return true;
    }

    /**
     * Accept new client
     *
     * @return string
     */
    public function accept(): string
    {
        try {
            if (false === ($accept = stream_socket_accept($this->socket_clients[$this->master_id]))) {
                unset($accept);
                return '';
            }

            stream_set_blocking($accept, false);

            $accept_id = $this->genId();

            $this->socket_clients[$accept_id] = &$accept;
            $this->socket_actives[$accept_id] = time();
        } catch (\Throwable $throwable) {
            unset($throwable, $accept, $accept_id);
            return '';
        }

        unset($accept);
        return $accept_id;
    }

    /**
     * Watch readable clients
     *
     * @param array $clients
     *
     * @return array
     */
    public function watch(array $clients): array
    {
        $write = $except = [];

        if (0 === ($changes = (int)stream_select($clients, $write, $except, 0 <= $this->watch_sec ? $this->watch_sec : null))) {
            $clients = [];
        }

        $this->showLog('listen', $changes . ' to read. ' . (count($this->socket_clients) - 1) . ' online.');

        unset($write, $except, $changes);
        return $clients;
    }

    /**
     * Close connection
     *
     * @param string $sock_id
     */
    public function close(string $sock_id): void
    {
        try {
            fclose($this->socket_clients[$sock_id]);
        } catch (\Throwable $throwable) {
            unset($throwable);
        }

        unset($this->socket_clients[$sock_id], $this->socket_actives[$sock_id]);
        $this->showLog('close', $sock_id . ': Closed. ' . (count($this->socket_clients) - 1) . ' online.');
        unset($sock_id);
    }

    /**
     * Read message from client
     *
     * @param string $sock_id
     *
     * @return array
     */
    public function readMsg(string $sock_id): array
    {
        try {
            if (false === ($msg = fread($this->socket_clients[$sock_id], 6))) {
                throw new \Exception($sock_id . ': Read ERROR!', E_USER_NOTICE);
            }

            while ('' !== ($buff = fread($this->socket_clients[$sock_id], 4096))) {
                $msg .= $buff;
            }

            $this->socket_actives[$sock_id] = time();

            $result = ['len' => strlen($msg), 'msg' => &$msg];
        } catch (\Throwable $throwable) {
            $this->showLog('exit', $throwable->getMessage());
            $this->close($sock_id);

            unset($throwable, $sock_id, $msg);
            $result = ['len' => -1, 'msg' => ''];
        }

        unset($sock_id, $msg, $buff);
        return $result;
    }

    /**
     * Send message to a client
     *
     * @param string $sock_id
     * @param string $message
     * @param bool   $ws_encode
     *
     * @return int
     */
    public function sendMsg(string $sock_id, string $message, bool $ws_encode = false): int
    {
        try {
            $byte = fwrite($this->socket_clients[$sock_id], $ws_encode ? $this->wsEncode($message) : $message);
            $this->showLog('send', $sock_id . ': ' . $message);
        } catch (\Throwable $throwable) {
            $this->showLog('exit', $sock_id . ': Send ERROR!');
            $this->close($sock_id);

            unset($throwable, $sock_id, $message, $ws_encode, $byte);
            return -1;
        }

        unset($sock_id, $message, $ws_encode);
        return $byte;
    }

    /**
     * Heartbeat logic
     */
    public function heartbeat(): void
    {
        $chk_time = time();
        $max_wait = $this->heartbeat_sec * 2;

        foreach ($this->socket_actives as $sock_id => $active_time) {
            //Calculate idle time
            $idle = $chk_time - $active_time;

            if ($max_wait < $idle) {
                //Heartbeat lost, close client
                $this->showLog('exit', $sock_id . ': Lost heartbeat.');
                $this->close($sock_id);
            } elseif ($this->heartbeat_sec <= $idle && '' !== $this->heartbeat_val) {
                //Send heartbeat message to client
                $this->showLog('heartbeat', $sock_id . ': Heartbeat sent.');
                $this->sendMsg($sock_id, $this->heartbeat_val);
            }
        }

        unset($chk_time, $max_wait, $sock_id, $active_time, $idle);
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
        $code = ['fin' => $char >> 7, 'opcode' => $char & 0x0F, 'mask' => ord($buff[1]) >> 7];

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
        if (false === ($key_pos = strpos($header, 'Sec-WebSocket-Key'))) {
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
        if (false === ($proto_pos = strpos($header, 'Sec-WebSocket-Protocol'))) {
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

        $this->showLog('handshake', 'Build response: ' . $response);

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
        //Get payload length
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

        $msg = '';
        $len = strlen($data);

        for ($i = 0; $i < $len; ++$i) {
            $msg .= $data[$i] ^ $mask[$i % 4];
        }

        unset($buff, $payload_len, $mask, $data, $len, $i);
        return $msg;
    }

    /**
     * WebSocket: Encode message
     *
     * @param string $msg
     *
     * @return string
     */
    public function wsEncode(string $msg): string
    {
        $msg_len = strlen($msg);

        if (125 >= $msg_len) {
            $buff = chr(0x81) . chr($msg_len) . $msg;
        } elseif (65535 >= $msg_len) {
            $buff = chr(0x81) . chr(126) . pack('n', $msg_len) . $msg;
        } else {
            $buff = chr(0x81) . chr(127) . pack('xxxxN', $msg_len) . $msg;
        }

        unset($msg, $msg_len);
        return $buff;
    }

    /**
     * WebSocket: Send Ping frame
     *
     * @param string $sock_id
     */
    public function wsPing(string $sock_id): void
    {
        $this->sendMsg($sock_id, chr(0x89) . chr(0));
        unset($sock_id);
    }

    /**
     * WebSocket: Send Pong frame
     *
     * @param string $sock_id
     */
    public function wsPong(string $sock_id): void
    {
        $this->sendMsg($sock_id, chr(0x8A) . chr(0));
        unset($sock_id);
    }
}