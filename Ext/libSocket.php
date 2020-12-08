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

    public libMPC $lib_mpc;

    public string $handler_class;

    /**
     * Listen to bind address
     *
     * @param string $address
     * @param int    $port
     * @param string $protocol (tcp/udp/ssl/tls1.2/...)
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
     * @throws \Exception
     */
    public function run(): void
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

        $this->lib_mpc = libMPC::new()->setPhpPath(OSUnit::new()->getPhpPath())->start();
        $this->clients = $this->master = [$this->genId() => $socket];

        $this->{'on' . ucfirst($this->type)}();
    }

    /**
     * Generate online ID
     *
     * @return string
     */
    private function genId(): string
    {
        $uid = substr(hash('md5', uniqid(microtime() . (string)mt_rand(), true)), 8, 16);
        return !isset($this->clients[$uid]) ? $uid : $this->genId();
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

        while (true) {
            $read = $this->clients;

            if (false === $changes = stream_select($read, $write, $except, 60)) {
                throw new \Exception('Socket server ERROR!', E_USER_ERROR);
            }

            if (0 === $changes) {
                continue;
            }

            $msg_tk = $send_tk = [];

            //Read from socket and send to MPC
            foreach ($read as $sock_id => $client) {
                //Accept new connection
                if ($client === $socket && false !== ($connect = stream_socket_accept($client))) {
                    stream_set_blocking($connect, false);
                    $this->clients[$id = $this->genId()] = $connect;
                    fwrite($connect, $this->lib_mpc->fetch($this->lib_mpc->addJob($this->handler_class . '/onConnect', ['sid' => $id])));
                    continue;
                }

                //Client error
                if (false === ($socket_msg = fgets($client))) {
                    unset($this->clients[$sock_id]);
                    fclose($client);
                    continue;
                }

                //Send to onMessage logic via MPC
                $msg_tk[$sock_id] = $this->lib_mpc->addJob($this->handler_class . '/onMessage', ['msg' => $socket_msg]);
            }

            //Process message
            foreach ($msg_tk as $sock_id => $mtk) {
                $msg_json = $this->lib_mpc->fetch($mtk);

                if (!is_array($msg_data = json_decode($msg_json, true))) {
                    fclose($this->clients[$sock_id]);
                    unset($this->clients[$sock_id]);
                    continue;
                }

                $to_sid = (string)($msg_data['to_sid'] ?? '');
                $is_ol  = '' !== $to_sid ? isset($this->clients[$to_sid]) : false;

                //Send to onSend logic via MPC
                $mtk = $this->lib_mpc->addJob($this->handler_class . '/onSend', ['msg' => $msg_json, 'to_sid' => $to_sid, 'is_ol' => $is_ol]);

                //Save to message or drop fetched
                $is_ol ? $send_tk[$to_sid] = $mtk : $this->lib_mpc->fetch($mtk);
            }

            //Send message
            foreach ($send_tk as $sock_id => $mtk) {
                $msg_json = $this->lib_mpc->fetch($mtk);
                fwrite($this->clients[$sock_id], $msg_json);
            }
        }
    }


    private function onWs(): void
    {

    }


    private function onUdp(): void
    {

    }
}