<?php

/**
 * SvrOnRedis Extension
 *
 * Copyright 2021 秋水之冰 <27206617@qq.com>
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

use Core\OSUnit;

/**
 * Class libSvrOnRedis
 *
 * @package Ext
 */
class libSvrOnRedis extends libSocket
{
    public \Redis $redis;
    public libMPC $lib_mpc;

    public bool $is_ws = false;

    public int $batch_size = 200;

    public string $proc_ol   = 'socket:online';
    public string $proc_key  = 'socket:job:';
    public string $proc_name = 'worker';

    /**
     * Registered handler class
     *
     * MUST expose methods:
     * onConnect(string sid, string socket): void
     * onHandshake(string sid, string proto): bool
     * onMessage(string sid, string msg): void
     * onSend(string key): array (contains "to" & "msg")
     * onClose(string sid): void
     *
     * @var string handler class name
     */
    public string $handler_class = '';

    /**
     * libSvrOnRedis constructor.
     *
     * @param int    $mpc_cnt
     * @param string $mem_limit
     *
     * @throws \Exception
     */
    public function __construct(int $mpc_cnt = 10, string $mem_limit = '1G')
    {
        ini_set('memory_limit', $mem_limit);

        $this->proc_name = $_SERVER['HOSTNAME'] ?? 'worker';
        $this->proc_key  .= $this->proc_name;

        $this->lib_mpc = libMPC::new()
            ->setPhpPath(OSUnit::new()->getPhpPath())
            ->setProcNum($mpc_cnt)
            ->start();

        unset($mem_limit);
    }

    /**
     * Bind Redis instance
     *
     * @param \Redis $redis
     *
     * @return $this
     */
    public function bindRedis(\Redis $redis): self
    {
        $this->redis = &$redis;

        unset($redis);
        return $this;
    }

    /**
     * Set push message batch size
     *
     * @param int $batch_size
     *
     * @return $this
     */
    public function setBatchSize(int $batch_size): self
    {
        $this->batch_size = &$batch_size;

        unset($batch_size);
        return $this;
    }

    /**
     * Set handler class by name
     *
     * @param string $class_name
     *
     * @return $this
     */
    public function setHandlerClass(string $class_name): self
    {
        $this->handler_class = &$class_name;

        unset($class_name);
        return $this;
    }

    /**
     * Start server on Redis
     *
     * @param bool $is_ws
     *
     * @throws \Exception
     */
    public function start(bool $is_ws = false): void
    {
        if ($is_ws) {
            $this->is_ws = true;
        }

        if ($this->run()) {
            register_shutdown_function(
                function ()
                {
                    //Remove worker registry
                    $this->redis->hDel($this->proc_ol, $this->proc_name);
                }
            );

            //Add worker registry
            $this->redis->hSet($this->proc_ol, $this->proc_name, time());
        } else {
            throw new \Exception('Failed to start!');
        }

        while (true) {
            //Watch all connections
            $read = $this->watch($this->socket_clients);

            if (empty($read)) {
                //Call heartbeat handler
                $this->heartbeat();
                continue;
            }

            //Read clients
            $this->readClients($read);

            //Push messages
            $this->pushMsg();

            //Call heartbeat handler
            $this->heartbeat();
        }
    }

    /**
     * Close client
     *
     * @param string $sock_id
     */
    public function close(string $sock_id): void
    {
        //Send close status via MPC
        $this->lib_mpc->addJob($this->handler_class . '/onClose', ['sid' => &$sock_id]);
        parent::close($sock_id);
        unset($sock_id);
    }

    /**
     * Read clients
     *
     * @param array $clients
     */
    public function readClients(array $clients): void
    {
        foreach ($clients as $sock_id => $client) {
            if ($sock_id !== $this->master_id) {
                //Read
                if ('' === ($socket_msg = $this->recvMsg($sock_id))) {
                    continue;
                }

                //Send socket message via MPC (MUST push to worker job list in Redis, NO returned)
                $this->lib_mpc->addJob($this->handler_class . '/onMessage', ['sid' => &$sock_id, 'msg' => &$socket_msg]);
                unset($socket_msg);
            } else {
                //Accept
                if ('' === ($accept_id = $this->accept())) {
                    continue;
                }

                //Send connection info via MPC
                $this->lib_mpc->addJob($this->handler_class . '/onConnect', ['sid' => &$accept_id, 'socket' => $this->proc_name]);

                //Response handshake to WebSocket connection
                if ($this->is_ws && !$this->sendHandshake($accept_id)) {
                    $this->close($accept_id);
                    continue;
                }

                $this->showLog('connect', $accept_id . ': Connected!');
                unset($accept_id);
            }
        }

        unset($clients, $sock_id, $client);
    }

    /**
     * Push socket messages (batch size)
     */
    public function pushMsg(): void
    {
        $job_tk = [];

        for ($i = 0; $i < $this->batch_size; ++$i) {
            //Get 200 messages via MPC by worker process key
            $job_tk[] = $this->lib_mpc->addJob($this->handler_class . '/onSend', ['key' => $this->proc_key]);
        }

        foreach ($job_tk as $tk) {
            //Fetch message data via MPC by job_tk
            $msg = json_decode($this->lib_mpc->fetch($tk), true);

            //Message data ERROR
            if (!is_array($msg) || !isset($msg['to']) || !isset($msg['msg'])) {
                continue;
            }

            //Client offline
            if (!isset($this->socket_clients[$msg['to']])) {
                continue;
            }

            //Send message
            $this->sendMsg($msg['to'], $msg['msg'], $this->is_ws);
        }

        unset($job_tk, $i, $tk, $msg);
    }

    /**
     * Receive decoded message
     *
     * @param string $sock_id
     *
     * @return string
     */
    public function recvMsg(string $sock_id): string
    {
        $socket_msg = $this->readMsg($sock_id);

        if (0 >= $socket_msg['len']) {
            unset($sock_id, $socket_msg);
            return '';
        }

        //Update active time
        $this->socket_actives[$sock_id] = time();

        //Copy message
        $msg = &$socket_msg['msg'];
        unset($socket_msg);

        //Process WebSocket message
        if ($this->is_ws) {
            //Get WebSocket codes
            $ws_codes = $this->wsGetCodes($msg);

            //Accept pong frame (pong:0xA), drop non-masked frames
            if (0xA === $ws_codes['opcode'] || 1 !== $ws_codes['mask']) {
                //On receive pong frame (pong:0xA), or, non-masked frames
                $this->showLog('heartbeat', $sock_id . ': Receive OpCode="' . $ws_codes['opcode'] . '".');
                unset($sock_id, $msg, $ws_codes);
                return '';
            }

            //Respond to ping frame (ping:0x9)
            if (0x9 === $ws_codes['opcode']) {
                $this->showLog('heartbeat', $sock_id . ': Response OpCode="' . $ws_codes['opcode'] . '".');
                $this->wsPong($sock_id);
                unset($sock_id, $msg, $ws_codes);
                return '';
            }

            //Check opcode (connection closed: 8)
            if (0x8 === $ws_codes['opcode']) {
                $this->showLog('exit', $sock_id . ': Exit with OpCode="' . $ws_codes['opcode'] . '".');
                $this->close($sock_id);
                unset($sock_id, $msg, $ws_codes);
                return '';
            }

            //Decode message
            $msg = $this->wsDecode($msg);
            unset($ws_codes);
        }

        $this->showLog('receive', $sock_id . ': ' . $msg);

        unset($sock_id);
        return $msg;
    }

    /**
     * Send handshake response
     *
     * @param string $sock_id
     *
     * @return bool
     */
    public function sendHandshake(string $sock_id): bool
    {
        $socket_msg = $this->readMsg($sock_id);

        if (0 === $socket_msg['len']) {
            $this->showLog('exit', $sock_id . ': Handshake data ERROR!');
            $this->close($sock_id);

            unset($sock_id, $socket_msg);
            return false;
        }

        $this->showLog('handshake', $sock_id . ': ' . $socket_msg['msg']);

        //Get WebSocket Key & Protocol
        $ws_key   = $this->wsGetKey($socket_msg['msg']);
        $ws_proto = $this->wsGetProto($socket_msg['msg']);

        unset($socket_msg);

        //Check protocol via MPC
        $tk_handshake   = $this->lib_mpc->addJob($this->handler_class . '/onHandshake', ['sid' => &$sock_id, 'proto' => &$ws_proto]);
        $pass_handshake = $this->lib_mpc->fetch($tk_handshake);

        if (true !== json_decode($pass_handshake, true)) {
            //Close when protocol invalid
            $this->sendMsg($sock_id, 'Http/1.1 406 Not Acceptable' . "\r\n\r\n");
            $this->showLog('exit', $sock_id . ': Protocol data ERROR!');
            $this->close($sock_id);

            unset($sock_id, $ws_key, $ws_proto, $tk_handshake, $pass_handshake);
            return false;
        }

        //Send handshake response
        $this->sendMsg($sock_id, $this->wsGetHandshake($ws_key, $ws_proto));

        unset($sock_id, $ws_key, $ws_proto, $tk_handshake, $pass_handshake);
        return true;
    }
}