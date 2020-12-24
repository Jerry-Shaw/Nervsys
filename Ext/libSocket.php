<?php

/**
 * Socket Extension
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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
use Core\OSUnit;

/**
 * Class libSocket
 *
 * @package Ext
 */
class libSocket extends Factory
{
    public int    $wait  = 30;
    public int    $port  = 2468;
    public string $ping  = '';
    public string $addr  = '0.0.0.0';
    public string $type  = 'tcp';
    public string $proto = 'tcp';

    public string $local_pk   = '';
    public string $local_cert = '';
    public string $passphrase = '';

    public bool $sock_debug  = false;
    public bool $self_signed = false;

    public array $master  = [];
    public array $clients = [];
    public array $actives = [];

    public string $master_id;

    /**
     * Registered handler class
     *
     * Methods:
     * onConnect(string sid): array
     * onHandshake(string sid, string proto): bool
     * onMessage(string msg): array
     * onSend(array data, bool online): array
     * onClose(string sid): void
     *
     * @var string handler class name
     */
    public string $handler_class;

    /** @var \Ext\libMPC $lib_mpc */
    public libMPC $lib_mpc;

    /**
     * Listen to bind address
     *
     * @param string $address
     * @param int    $port
     * @param string $protocol (tcp/udp/ssl/tls)
     *
     * @return $this
     */
    public function listenTo(string $address, int $port, string $protocol = 'tcp'): self
    {
        $this->addr  = &$address;
        $this->port  = &$port;
        $this->proto = &$protocol;

        unset($address, $port, $protocol);
        return $this;
    }

    /**
     * Set ping value for heartbeat
     *
     * @param string $value
     *
     * @return $this
     */
    public function setPingVal(string $value): self
    {
        $this->ping = &$value;

        unset($value);
        return $this;
    }

    /**
     * Set tv_sec for upper timeout
     *
     * @param int $tv_sec
     *
     * @return $this
     */
    public function setWaitSec(int $tv_sec): self
    {
        $this->wait = &$tv_sec;

        unset($tv_sec);
        return $this;
    }

    /**
     * Set SSL options
     *
     * @param string $local_cert
     * @param string $local_pk
     * @param string $passphrase
     * @param bool   $self_signed
     *
     * @return $this
     */
    public function setSslOption(string $local_cert, string $local_pk = '', string $passphrase = '', bool $self_signed = false): self
    {
        $this->local_cert  = &$local_cert;
        $this->local_pk    = &$local_pk;
        $this->passphrase  = &$passphrase;
        $this->self_signed = &$self_signed;

        unset($local_cert, $local_pk, $passphrase, $self_signed);
        return $this;
    }

    /**
     * Set server type (tcp/udp/ws)
     *
     * @param string $type
     *
     * @return $this
     */
    public function setServerType(string $type): self
    {
        $this->type = &$type;

        unset($type);
        return $this;
    }

    /**
     * Set custom data handler classname
     *
     * @param string $handler_class
     *
     * @return $this
     */
    public function setHandlerClass(string $handler_class): self
    {
        $this->handler_class = '/' . ltrim(strtr($handler_class, '\\', '/'), '/');

        unset($handler_class);
        return $this;
    }

    /**
     * Set socket debug mode
     *
     * @param bool $sock_debug
     *
     * @return $this
     */
    public function setSockDebug(bool $sock_debug): self
    {
        $this->sock_debug = &$sock_debug;

        unset($sock_debug);
        return $this;
    }

    /**
     * Run socket server
     *
     * @param int $mpc_cnt
     *
     * @throws \Exception
     */
    public function run(int $mpc_cnt = 10): void
    {
        $context = stream_context_create();

        if ('' !== $this->local_cert) {
            stream_context_set_option($context, 'ssl', 'local_cert', $this->local_cert);

            '' !== $this->local_pk && stream_context_set_option($context, 'ssl', 'local_pk', $this->local_pk);
            '' !== $this->passphrase && stream_context_set_option($context, 'ssl', 'passphrase', $this->passphrase);

            stream_context_set_option($context, 'ssl', 'allow_self_signed', $this->self_signed);
            stream_context_set_option($context, 'ssl', 'ssltransport', $this->proto);
            stream_context_set_option($context, 'ssl', 'verify_peer', false);
            stream_context_set_option($context, 'ssl', 'disable_compression', true);
        }

        $socket = stream_socket_server(
            $this->proto . '://' . $this->addr . ':' . (string)$this->port,
            $errno,
            $errstr,
            'udp' != $this->proto ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN : STREAM_SERVER_BIND,
            $context
        );

        if (false === $socket) {
            throw new \Exception($errno . ': ' . $errstr, E_USER_ERROR);
        }

        $this->master_id = $this->genId();
        $this->master    = [$this->master_id => &$socket];

        $this->lib_mpc = libMPC::new()
            ->setProcNum($mpc_cnt)
            ->setPhpPath(OSUnit::new()->getPhpPath())
            ->start();

        $this->{'on' . ucfirst($this->type)}();
    }

    /**
     * Generate online ID
     *
     * @return string
     */
    public function genId(): string
    {
        $uid = substr(hash('md5', uniqid(microtime() . (string)mt_rand(), true)), 8, 16);
        return !isset($this->clients[$uid]) ? $uid : $this->genId();
    }

    /**
     * Watch for read clients
     *
     * @param array $read
     *
     * @return array
     */
    public function watch(array $read): array
    {
        $write = $except = [];

        //Watch read streams
        if (0 === ($changes = (int)stream_select($read, $write, $except, 0 <= $this->wait ? $this->wait : null))) {
            $read = [];
        }

        //On status changes or time arrived
        $this->debug('Monitor: ' . $changes . ' out of ' . count($this->clients) . ' in queue.');

        unset($write, $except, $changes);
        return $read;
    }

    /**
     * Add MPC job
     *
     * @param string $method
     * @param array  $data
     *
     * @return string
     */
    public function addMpc(string $method, array $data): string
    {
        $tk = $this->lib_mpc->addJob($this->handler_class . '/' . $method, $data);

        unset($method, $data);
        return $tk;
    }

    /**
     * Read full message from client
     *
     * @param string $sock_id
     *
     * @return string
     */
    public function readMsg(string $sock_id): string
    {
        try {
            if (false === ($msg = fread($this->clients[$sock_id], 1024))) {
                throw new \Exception('Read message failed!', E_USER_NOTICE);
            }

            while ('' !== ($buff = fread($this->clients[$sock_id], 4096))) {
                $msg .= $buff;
            }
        } catch (\Throwable $throwable) {
            $this->close($sock_id);
            unset($throwable, $sock_id, $msg);
            return '';
        }

        unset($sock_id, $buff);
        return $msg;
    }

    /**
     * Prepare send message
     *
     * @param array $msg_tk
     *
     * @return array
     */
    public function prepMsg(array $msg_tk): array
    {
        $send_data = [];

        foreach ($msg_tk as $sock_id => $mtk) {
            //Fetch msg from onMessage logic
            if (!is_array($msg_data = json_decode(trim($this->lib_mpc->fetch($mtk)), true))) {
                $this->close($sock_id);
                continue;
            }

            //Build send_tk data
            $send_data[$sock_id] = [
                'stk' => $this->addMpc('onSend', [
                    'data'   => $msg_data,
                    'online' => isset($msg_data['to_sid']) ? isset($this->clients[$msg_data['to_sid']]) : false
                ])
            ];
        }

        unset($msg_tk, $mtk);

        foreach ($send_data as $sock_id => &$item) {
            //Fetch msg from onSend logic
            if (!is_array($msg_data = json_decode(trim($this->lib_mpc->fetch($item['stk'])), true))) {
                unset($send_data[$sock_id]);
                $this->close($sock_id);
                continue;
            }

            //Drop message without receiver
            if (!isset($msg_data['to_sid'])) {
                unset($send_data[$sock_id]);
                continue;
            }

            //Copy to_sid value to array
            $item['to'] = !is_array($msg_data['to_sid']) ? [$msg_data['to_sid']] : $msg_data['to_sid'];

            //Clean up data
            unset($msg_data['to_sid'], $item['stk']);

            //Rebuild message data
            $item['msg'] = json_encode($msg_data, JSON_FORMAT);
        }

        unset($sock_id, $msg_data, $item);
        return $send_data;
    }

    /**
     * Send message to a client
     *
     * @param string $sock_id
     * @param string $message
     *
     * @return int
     */
    public function sendMsg(string $sock_id, string $message): int
    {
        try {
            $byte = fwrite($this->clients[$sock_id], $message);
        } catch (\Throwable $throwable) {
            $this->close($sock_id);
            unset($throwable, $sock_id, $message, $byte);
            return 0;
        }

        unset($sock_id, $message);
        return $byte;
    }

    /**
     * Push message to clients
     *
     * @param array  $clients
     * @param string $message
     */
    public function pushMsg(array $clients, string $message): void
    {
        foreach ($clients as $sock_id) {
            $this->sendMsg($sock_id, $message);
        }

        unset($clients, $message, $sock_id);
    }

    /**
     * Close connection
     *
     * @param string $sock_id
     */
    public function close(string $sock_id): void
    {
        try {
            fclose($this->clients[$sock_id]);
        } catch (\Throwable $throwable) {
            unset($throwable);
        }

        unset($this->clients[$sock_id], $this->actives[$sock_id]);
        $this->lib_mpc->fetch($this->addMpc('onClose', ['sid' => $sock_id]));

        //On client exit
        $this->debug('Exit: "' . $sock_id . '" left. Still ' . count($this->clients) . ' online.');
        unset($sock_id);
    }

    /**
     * 输出 debug 信息
     *
     * @param string $debug_msg
     */
    public function debug(string $debug_msg): void
    {
        if ($this->sock_debug) {
            echo strtr($debug_msg, ["\r" => '\r', "\n" => '\n']) . PHP_EOL;
        }

        unset($debug_msg);
    }

    /**
     * Heartbeat logic
     */
    public function heartbeat(): void
    {
        //Set check time
        $chk_time = time();

        //Check active clients
        foreach ($this->actives as $sock_id => $active_time) {
            //Calculate time duration
            $duration = $chk_time - $active_time;

            if (60 < $duration) {
                //Close offline client
                $this->close($sock_id);
            } elseif (30 < $duration && '' !== $this->ping) {
                //Send ping message to client
                $this->sendMsg($sock_id, $this->ping);
                //On send heartbeat frame message
                $this->debug('Heartbeat: send "' . $this->ping . '" to "' . $sock_id . '".');
            }
        }

        unset($chk_time, $sock_id, $active_time, $duration);
    }

    /**
     * Get WebSocket header codes (fin, opcode, mask)
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
     * WebSocket generate handshake response
     *
     * @param string $sid
     * @param string $header
     *
     * @return string
     */
    public function wsHandshake(string $sid, string $header): string
    {
        //Validate Sec-WebSocket-Key
        $key_name = 'Sec-WebSocket-Key';
        $key_pos  = strpos($header, $key_name);

        if (false === $key_pos) {
            unset($sid, $header, $key_name, $key_pos);
            return '';
        }

        //Validate Sec-WebSocket-Protocol
        $ws_protocol = '';
        $proto_name  = 'Sec-WebSocket-Protocol';
        $proto_pos   = strpos($header, $proto_name);

        if (false !== $proto_pos) {
            $proto_pos  += 24;
            $proto_val  = substr($header, $proto_pos, strpos($header, "\r\n", $proto_pos) - $proto_pos);
            $proto_pass = $this->lib_mpc->fetch($this->addMpc('onHandshake', ['sid' => $sid, 'proto' => $proto_val]));

            //Reject handshake
            if (true !== json_decode($proto_pass, true)) {
                unset($sid, $header, $key_name, $key_pos, $ws_protocol, $proto_name, $proto_pos, $proto_val, $proto_pass);
                return '';
            }

            //Only response the last protocol value
            if (false !== ($val_pos = strrpos($proto_val, ','))) {
                $proto_val = substr($proto_val, $val_pos + 2);
            }

            $ws_protocol = 'Sec-WebSocket-Protocol: ' . $proto_val . "\r\n";;
            unset($proto_val, $proto_pass, $val_pos);
        }

        //Get WebSocket key & rehash
        $key_pos += 19;
        $key_val = substr($header, $key_pos, strpos($header, "\r\n", $key_pos) - $key_pos);
        $key_val = hash('sha1', $key_val . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true);

        //Generate response
        $response = 'HTTP/1.1 101 Switching Protocols' . "\r\n"
            . 'Upgrade: websocket' . "\r\n"
            . 'Connection: Upgrade' . "\r\n"
            . 'Sec-WebSocket-Accept: ' . base64_encode($key_val) . "\r\n"
            . $ws_protocol . "\r\n";

        unset($sid, $header, $key_name, $key_pos, $ws_protocol, $proto_name, $proto_pos, $key_val);
        return $response;
    }

    /**
     * WebSocket decode message
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
     * WebSocket encode message
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
     * Send WebSocket Ping frame
     *
     * @param string $sock_id
     */
    public function wsPing(string $sock_id): void
    {
        $this->sendMsg($sock_id, chr(0x89) . chr(0));
        unset($sock_id);
    }

    /**
     * Send WebSocket Pong frame
     *
     * @param string $sock_id
     */
    public function wsPong(string $sock_id): void
    {
        $this->sendMsg($sock_id, chr(0x8A) . chr(0));
        unset($sock_id);
    }

    /**
     * Tcp server
     *
     * @throws \Exception
     */
    private function onTcp(): void
    {
        //Copy master to clients
        $this->clients = $this->master;

        while (true) {
            $read = $this->watch($this->clients);

            if (empty($read)) {
                //Call heartbeat handler
                $this->heartbeat();
                continue;
            }

            $msg_tk = [];

            //Read from socket and send to MPC
            foreach ($read as $sock_id => $client) {
                if ($sock_id !== $this->master_id) {
                    //Read all client message (binary|string)
                    if ('' === ($socket_msg = $this->readMsg($sock_id))) {
                        $this->close($sock_id);
                        continue;
                    }

                    //Update active time
                    $this->actives[$sock_id] = time();

                    //Send to onMessage logic via MPC
                    $msg_tk[$sock_id] = $this->addMpc('onMessage', ['msg' => $socket_msg]);

                    //On received message from client
                    $this->debug('Receive: "' . $socket_msg . '" from "' . $sock_id . '".');
                    unset($socket_msg);
                } else {
                    //Accept new connection
                    try {
                        if (false === ($accept = stream_socket_accept($client))) {
                            continue;
                        }

                        stream_set_blocking($accept, false);

                        $accept_id = $this->genId();

                        $this->clients[$accept_id] = $accept;
                        $this->actives[$accept_id] = time();

                        $this->sendMsg($accept_id, $this->lib_mpc->fetch($this->addMpc('onConnect', ['sid' => $accept_id])));
                    } catch (\Throwable $throwable) {
                        unset($throwable, $accept, $accept_id);
                        continue;
                    }

                    //On new client connected
                    $this->debug('Assigned: "' . $accept_id . '" to new connection.');
                    unset($accept, $accept_id);
                }
            }

            //Process message
            $send_tk = $this->prepMsg($msg_tk);

            //Send message
            foreach ($send_tk as $item) {
                //Push message to clients
                $this->pushMsg($item['to'], $item['msg']);

                //On send message to client
                $this->debug('Send: "' . $item['msg'] . '" to "' . implode(', ', $item['to']) . '".');
            }

            //Call heartbeat handler
            $this->heartbeat();

            unset($read, $msg_tk, $sock_id, $client, $send_tk, $item);
        }
    }

    /**
     * SebSocket server
     *
     * @throws \Exception
     */
    private function onWs(): void
    {
        //New accepted clients
        $accept_clients = [];

        //Copy master to clients
        $this->clients = $this->master;

        while (true) {
            $read = $this->watch($this->clients);

            if (empty($read)) {
                //Call heartbeat handler
                $this->heartbeat();
                continue;
            }

            $msg_tk = [];

            //Read from socket and send to MPC
            foreach ($read as $sock_id => $client) {
                if ($sock_id !== $this->master_id) {
                    //Read all client message (WebSocket)
                    if ('' === ($socket_msg = $this->readMsg($sock_id))) {
                        $this->close($sock_id);
                        continue;
                    }

                    //Update active time
                    $this->actives[$sock_id] = time();

                    //Check handshake
                    if (isset($accept_clients[$sock_id])) {
                        //On received handshake header from client
                        $this->debug('Handshake: receive "' . $socket_msg . '" from "' . $sock_id . '".');

                        //Get handshake response
                        $response = $this->wsHandshake($sock_id, $socket_msg);

                        if ('' !== $response) {
                            //Send handshake and connection
                            $this->sendMsg($sock_id, $response);
                            $this->sendMsg($sock_id, $this->wsEncode($this->lib_mpc->fetch($this->addMpc('onConnect', ['sid' => $sock_id]))));

                            //On respond handshake to new connection
                            $this->debug('Handshake: respond "' . $response . '" to "' . $sock_id . '".');
                        } else {
                            //Error on handshake
                            $this->close($sock_id);

                            //On reject handshake to new connection
                            $this->debug('Handshake: reject "' . $sock_id . '" to connect.');
                        }

                        unset($accept_clients[$sock_id], $response);
                        continue;
                    }

                    //Get wWebSocket codes
                    $ws_codes = $this->wsGetCodes($socket_msg);

                    //Accept pong frame (pong:0xA), drop non-masked frames
                    if (0xA === $ws_codes['opcode'] || 1 !== $ws_codes['mask']) {
                        //On receive pong frame (pong:0xA), or, non-masked frames
                        $this->debug('Heartbeat: receive OpCode="' . $ws_codes['opcode'] . '", MASK="' . $ws_codes['mask'] . '".');
                        unset($ws_codes);
                        continue;
                    }

                    //Respond to ping frame (ping:0x9)
                    if (0x9 === $ws_codes['opcode']) {
                        $this->wsPong($sock_id);
                        unset($ws_codes);
                        continue;
                    }

                    //Check opcode (connection closed: 8)
                    if (0x8 === $ws_codes['opcode']) {
                        $this->close($sock_id);
                        unset($ws_codes);
                        continue;
                    }

                    //Send to onMessage logic via MPC
                    $msg_tk[$sock_id] = $this->addMpc('onMessage', ['msg' => ($socket_msg = $this->wsDecode($socket_msg))]);

                    //On received message from client
                    $this->debug('Receive: "' . $socket_msg . '" from "' . $sock_id . '".');
                    unset($socket_msg, $ws_codes);
                } else {
                    //Accept new connection
                    try {
                        if (false === ($accept = stream_socket_accept($client))) {
                            unset($accept);
                            continue;
                        }

                        stream_set_blocking($accept, false);

                        $accept_id = $this->genId();

                        $this->clients[$accept_id] = $accept;
                        $this->actives[$accept_id] = time();

                        $accept_clients[$accept_id] = 1;
                    } catch (\Throwable $throwable) {
                        unset($throwable, $accept, $accept_id);
                        continue;
                    }

                    //On new client connected
                    $this->debug('Assigned: "' . $accept_id . '" to new connection.');
                    unset($accept, $accept_id);
                }
            }

            //Process message
            $send_tk = $this->prepMsg($msg_tk);

            //Send message
            foreach ($send_tk as $item) {
                //Push message to clients
                $this->pushMsg($item['to'], $this->wsEncode($item['msg']));

                //On send message to client
                $this->debug('Send: "' . $item['msg'] . '" to "' . implode(', ', $item['to']) . '".');
            }

            //Call heartbeat handler
            $this->heartbeat();

            unset($read, $msg_tk, $sock_id, $client, $send_tk, $item);
        }

        unset($accept_clients);
    }
}