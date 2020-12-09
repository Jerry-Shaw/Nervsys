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
    public string $addr  = '0.0.0.0';
    public int    $port  = 2468;
    public string $type  = 'tcp';
    public string $proto = 'tcp';

    public string $local_pk    = '';
    public string $local_cert  = '';
    public string $passphrase  = '';
    public bool   $self_signed = false;

    public array $master  = [];
    public array $clients = [];

    /** @var \Ext\libMPC $lib_mpc */
    public libMPC $lib_mpc;

    public string $handler_class;

    /**
     * Listen to bind address
     *
     * @param string $address
     * @param int    $port
     * @param string $protocol (tcp/udp/ssl/tlsv1.3/...)
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
            stream_context_set_option($context, 'ssl', 'ssltransport', 'tlsv1.3');
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

        $this->lib_mpc = libMPC::new()->setProcNum($mpc_cnt)->setPhpPath(OSUnit::new()->getPhpPath())->start();
        $this->master  = [$this->genId() => $socket];

        $this->{'on' . ucfirst($this->type)}();
    }

    /**
     * Generate online ID
     *
     * @return string
     */
    protected function genId(): string
    {
        $uid = substr(hash('md5', uniqid(microtime() . (string)mt_rand(), true)), 8, 16);
        return !isset($this->clients[$uid]) ? $uid : $this->genId();
    }

    /**
     * Add MPC job
     *
     * @param string $method
     * @param array  $data
     *
     * @return string
     */
    protected function addMpc(string $method, array $data): string
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
    protected function readMsg(string $sock_id): string
    {
        $msg = fread($this->clients[$sock_id], 1024);

        if (false === $msg || '' === $msg) {
            fclose($this->clients[$sock_id]);
            unset($this->clients[$sock_id], $sock_id, $msg);
            return '';
        }

        while ('' !== ($buff = fread($this->clients[$sock_id], 4096))) {
            $msg .= $buff;
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
    protected function prepMsg(array $msg_tk): array
    {
        $send_tk = [];

        foreach ($msg_tk as $sock_id => $mtk) {
            if ('' === ($msg = trim($this->lib_mpc->fetch($mtk)))) {
                continue;
            }

            if (!is_array($msg_data = json_decode($msg, true))) {
                fclose($this->clients[$sock_id]);
                unset($this->clients[$sock_id]);
                continue;
            }

            $to_sid = (string)($msg_data['to_sid'] ?? '');
            $sid_ol = '' !== $to_sid ? isset($this->clients[$to_sid]) : false;

            //Send to onSend logic via MPC
            $stk = $this->addMpc('onSend', [
                'data'   => $msg_data,
                'to_sid' => $to_sid,
                'sid_ol' => $sid_ol
            ]);

            //Save stk to send_tk or drop offline data
            $sid_ol ? $send_tk[$to_sid] = $stk : $this->lib_mpc->fetch($stk);
        }

        unset($msg_tk, $sock_id, $mtk, $msg, $msg_data, $to_sid, $sid_ol, $stk);
        return $send_tk;
    }

    /**
     * Send message to a client
     *
     * @param string $sock_id
     * @param string $msg
     *
     * @return int
     */
    protected function sendMsg(string $sock_id, string $msg): int
    {
        try {
            $byte = fwrite($this->clients[$sock_id], $msg);
        } catch (\Throwable $throwable) {
            fclose($this->clients[$sock_id]);
            unset($this->clients[$sock_id], $sock_id, $msg, $byte, $throwable);
            return 0;
        }

        unset($sock_id, $msg);
        return $byte;
    }

    /**
     * WebSocket get opcode
     *
     * @param string $buff
     *
     * @return int
     */
    protected function wsGetOpcode(string $buff): int
    {
        return ord($buff[0]) & 0x0F;
    }

    /**
     * WebSocket generate handshake response
     *
     * @param string $header
     *
     * @return string
     */
    protected function wsHandshake(string $header): string
    {
        //WebSocket key name & key mask
        $key_name = 'Sec-WebSocket-Key';
        $key_mask = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

        //Get key position
        if (false === $key_pos = strpos($header, $key_name)) {
            return '';
        }

        //Move key offset
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
     * WebSocket decode message
     *
     * @param string $buff
     *
     * @return string
     */
    protected function wsDecode(string $buff): string
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
     * WebSocket encode message
     *
     * @param string $msg
     *
     * @return string
     */
    protected function wsEncode(string $msg): string
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
     * Tcp server
     *
     * @throws \Exception
     */
    private function onTcp(): void
    {
        $write  = $except = [];
        $socket = current($this->master);

        //Copy master to clients
        $this->clients = $this->master;

        while (true) {
            $read = $this->clients;

            if (false === ($changes = stream_select($read, $write, $except, 60))) {
                throw new \Exception('Socket server ERROR!', E_USER_ERROR);
            }

            if (0 === $changes) {
                continue;
            }

            $msg_tk = [];

            //Read from socket and send to MPC
            foreach ($read as $sock_id => $client) {
                //Accept new connection
                if ($client === $socket) {
                    try {
                        $accept = stream_socket_accept($client);

                        if (false === $accept) {
                            continue;
                        }

                        stream_set_blocking($accept, false);
                    } catch (\Throwable $throwable) {
                        continue;
                    }

                    $this->clients[$sid = $this->genId()] = $accept;
                    $this->sendMsg($sid, $this->lib_mpc->fetch($this->addMpc('onConnect', ['sid' => $sid])));

                    continue;
                }

                //Send to onMessage logic via MPC
                $msg_tk[$sock_id] = $this->addMpc('onMessage', ['msg' => $this->readMsg($sock_id)]);
            }

            //Process message
            $send_tk = $this->prepMsg($msg_tk);

            //Send message
            foreach ($send_tk as $sock_id => $stk) {
                $this->sendMsg($sock_id, $this->lib_mpc->fetch($stk));
            }

            unset($read, $changes, $msg_tk, $sock_id, $client, $accept, $sid, $send_tk, $stk);
        }

        unset($write, $except, $socket);
    }

    /**
     * SebSocket server
     *
     * @throws \Exception
     */
    private function onWs(): void
    {
        $write = $except = [];

        //Copy master to clients
        $this->clients = $this->master;

        //Add master status
        $client_status[key($this->master)] = 0;

        while (true) {
            $read = $this->clients;

            if (false === ($changes = stream_select($read, $write, $except, 60))) {
                throw new \Exception('Socket server ERROR!', E_USER_ERROR);
            }

            if (0 === $changes) {
                continue;
            }

            $msg_tk = [];

            //Read from socket and send to MPC
            foreach ($read as $sock_id => $client) {
                switch ($client_status[$sock_id]) {
                    case 1:
                        //Read all client message in length (json)
                        $socket_msg = $this->readMsg($sock_id);

                        //Check opcode (connection closed: 8)
                        if (8 === $this->wsGetOpcode($socket_msg)) {
                            unset($this->clients[$sock_id], $client_status[$sock_id]);
                            fclose($client);
                            break;
                        }

                        //Decode websocket data
                        $socket_msg = $this->wsDecode($socket_msg);

                        //Send to onMessage logic via MPC
                        $msg_tk[$sock_id] = $this->addMpc('onMessage', ['msg' => $socket_msg]);
                        break;

                    case 2:
                        //Read client message in length (header)
                        $socket_msg = $this->readMsg($sock_id);

                        //Send handshake and sid info
                        $client_status[$sock_id] = 1;
                        $this->sendMsg($sock_id, $this->wsHandshake($socket_msg));
                        $this->sendMsg($sock_id, $this->wsEncode($this->lib_mpc->fetch($this->addMpc('onConnect', ['sid' => $sock_id]))));
                        break;

                    default:
                        //Accept new connection
                        try {
                            $accept = stream_socket_accept($client);

                            if (false === $accept) {
                                break;
                            }

                            stream_set_blocking($accept, false);
                        } catch (\Throwable $throwable) {
                            break;
                        }

                        $accept_id                 = $this->genId();
                        $this->clients[$accept_id] = $accept;
                        $client_status[$accept_id] = 2;
                        break;
                }
            }

            //Process message
            $send_tk = $this->prepMsg($msg_tk);

            //Send message
            foreach ($send_tk as $sock_id => $stk) {
                $this->sendMsg($sock_id, $this->wsEncode($this->lib_mpc->fetch($stk)));
            }

            unset($read, $changes, $msg_tk, $sock_id, $client, $socket_msg, $accept, $accept_id, $send_tk, $stk);
        }

        unset($write, $except, $client_status);
    }

    /**
     * UDP server
     */
    private function onUdp(): void
    {

    }
}