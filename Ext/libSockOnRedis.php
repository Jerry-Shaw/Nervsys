<?php

/**
 * Socket server on Redis Extension
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

use Core\Lib\App;
use Core\OSUnit;

/**
 * Class libSockOnRedis
 *
 * @package Ext
 */
class libSockOnRedis extends libSocket
{
    public App    $app;
    public \Redis $redis;
    public libMPC $lib_mpc;

    public bool $is_ws = false;

    public int $mpc_fork = 10;
    public int $mpc_exec = 1000;

    public int $batch_size = 200;

    public string $proc_name = 'worker';

    public string $hash_sock_ol = 'socket:map';
    public string $hash_proc_ol = 'socket:proc';
    public string $list_msg_key = 'socket:msg:';

    public array $ws_handshake = [];

    /**
     * Registered handler class
     *
     * MUST expose methods:
     * onConnect(string sid, string proc): void
     * onHandshake(string sid, string proto): bool
     * onMessage(string sid, string msg): void (push to Redis)
     * onClose(string sid): void
     *
     * @var string handler class name
     * @var object handler class object
     */
    public string $handler_class;
    public object $handler_object;

    /**
     * libSvrOnRedis constructor.
     *
     * @param string $address
     * @param string $port
     * @param string $protocol
     */
    public function __construct(string $address, string $port, string $protocol = 'tcp')
    {
        $this->app = App::new();

        $this->setAddr($address, $port, $protocol);
        $this->proc_name = $protocol . ':' . $port . ':' . $this->app->hostname;
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
     * Set libMPC properties
     *
     * @param int $max_fork
     * @param int $max_exec
     *
     * @return $this
     */
    public function setMpcProp(int $max_fork, int $max_exec): self
    {
        $this->mpc_fork = &$max_fork;
        $this->mpc_exec = &$max_exec;

        unset($max_fork, $max_exec);
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
     * Set socket handler object
     *
     * @param object $handler_object
     *
     * @return $this
     */
    public function setSocketHandler(object $handler_object): self
    {
        $this->handler_object = &$handler_object;
        $this->handler_class  = '/' . get_class($handler_object);

        unset($handler_object);
        return $this;
    }

    /**
     * Start server on Redis
     *
     * @param string $mem_limit
     * @param bool   $is_ws
     *
     * @throws \Exception
     */
    public function start(string $mem_limit = '1G', bool $is_ws = false): void
    {
        ini_set('memory_limit', $mem_limit);

        if ($is_ws) {
            $this->is_ws = true;
        }

        if ($this->run()) {
            register_shutdown_function(
                function ()
                {
                    //Remove worker registry
                    $this->redis->hDel($this->hash_proc_ol, $this->proc_name);
                }
            );

            //Add worker registry
            $this->redis->hSet($this->hash_proc_ol, $this->proc_name, time());
        } else {
            throw new \Exception('Failed to start!');
        }

        //Cleanup socket records
        $this->cleanup();

        //Start libMPC
        $this->lib_mpc = libMPC::new()->setPhpPath(OSUnit::new()->getPhpPath())->start($this->mpc_fork, $this->mpc_exec);

        while (true) {
            //Watch & read clients
            $this->readClients($this->watch($this->socket_clients));

            //Check heartbeat
            $this->heartbeat();

            //Push messages
            $this->pushMsg();
        }
    }

    /**
     * Cleanup socket records
     */
    public function cleanup(): void
    {
        $iterator = null;

        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);

        while (0 !== $iterator && !empty($socket = $this->redis->hScan($this->hash_sock_ol, $iterator))) {
            foreach ($socket as $sock_id => $proc_name) {
                if ($proc_name === $this->proc_name) {
                    $this->redis->hDel($this->hash_sock_ol, $sock_id);
                }
            }
        }

        unset($iterator, $socket, $sock_id, $proc_name);
    }

    /**
     * Close connection
     *
     * @param string $sock_id
     *
     * @throws \Exception
     */
    public function close(string $sock_id): void
    {
        $this->lib_mpc->add($this->handler_class . '/onClose', ['sid' => &$sock_id]);
        $this->redis->hDel($this->hash_sock_ol, $sock_id);
        parent::close($sock_id);
        unset($sock_id);
    }

    /**
     * Generate online socket ID
     *
     * @return string
     */
    public function genId(): string
    {
        $sock_id = parent::genId();
        return !$this->redis->hExists($this->hash_sock_ol, $sock_id) ? $sock_id : $this->genId();
    }

    /**
     * Find hostname of SID (online: proc_name; offline: empty string)
     *
     * @param string $sock_id
     *
     * @return string
     */
    public function findSid(string $sock_id): string
    {
        return (string)$this->redis->hGet($this->hash_sock_ol, $sock_id);
    }

    /**
     * Transfer message to process online
     *
     * @param string $sock_id
     * @param string $msg
     *
     * @return bool
     */
    public function transMsg(string $sock_id, string $msg): bool
    {
        $proc_name = $this->findSid($sock_id);

        if ('' === $proc_name || !$this->redis->hExists($this->hash_proc_ol, $proc_name)) {
            unset($sock_id, $msg, $proc_name);
            return false;
        }

        $len = $this->redis->lPush($this->list_msg_key . $proc_name, json_encode(['to' => &$sock_id, 'msg' => &$msg], JSON_FORMAT));

        unset($sock_id, $msg, $proc_name);
        return is_int($len);
    }

    /**
     * Push socket messages (batch size)
     */
    public function pushMsg(): void
    {
        $proc_job = 0;
        $proc_key = $this->list_msg_key . $this->proc_name;

        while ($proc_job < $this->batch_size && false !== ($msg_pack = $this->redis->rPop($proc_key))) {
            //Decode message data
            $msg_data = json_decode($msg_pack, true);

            //Message data ERROR
            if (!is_array($msg_data) || !isset($msg_data['to']) || !isset($msg_data['msg'])) {
                continue;
            }

            //Client offline
            if (!isset($this->socket_clients[$msg_data['to']])) {
                continue;
            }

            //Send message
            $this->sendMsg($msg_data['to'], $msg_data['msg'], $this->is_ws);

            //Add proc_job count
            ++$proc_job;
        }

        unset($proc_job, $proc_key, $msg_pack, $msg_data);
    }

    /**
     * Receive decoded message
     *
     * @param string $sock_id
     *
     * @return string
     * @throws \Exception
     */
    public function recvMsg(string $sock_id): string
    {
        //Read Message
        $socket_msg = $this->readMsg($sock_id);

        if (0 === $socket_msg['len']) {
            $this->wsPing($sock_id);
        }

        if (0 >= $socket_msg['len']) {
            unset($sock_id, $socket_msg);
            return '';
        }

        $this->socket_actives[$sock_id] = time();

        $msg = &$socket_msg['msg'];

        unset($socket_msg);

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
     * Read clients
     *
     * @param array $clients
     *
     * @throws \Exception
     */
    public function readClients(array $clients): void
    {
        foreach ($clients as $sock_id => $client) {
            if ($sock_id === $this->master_id) {
                //Accept
                if ('' === ($accept_id = $this->accept())) {
                    continue;
                }

                //Add to ws_handshake list
                $this->is_ws && $this->ws_handshake[$accept_id] = time();

                //Send connection info via Queue
                $this->lib_mpc->add($this->handler_class . '/onConnect', ['sid' => &$accept_id, 'proc' => $this->proc_name]);

                unset($accept_id);
            } elseif (isset($this->ws_handshake[$sock_id])) {
                //Handshake
                unset($this->ws_handshake[$sock_id]);

                //Read Message
                $msg_data = $this->readMsg($sock_id);

                if (0 === $msg_data['len']) {
                    $this->showLog('exit', $sock_id . ': Handshake data ERROR!');
                    $this->close($sock_id);
                    unset($msg_data);
                    continue;
                }

                $this->showLog('handshake', $sock_id . ': ' . $msg_data['msg']);

                //Response handshake to WebSocket connection
                if (!$this->sendHandshake($sock_id, $msg_data['msg'])) {
                    $this->close($sock_id);
                    unset($msg_data);
                    continue;
                }

                //Add to sock online list
                $this->redis->hSet($this->hash_sock_ol, $sock_id, $this->proc_name);
                $this->showLog('handshake', $sock_id . ': is online!');

                unset($msg_data);
            } else {
                //Read
                if ('' === ($socket_msg = $this->recvMsg($sock_id))) {
                    continue;
                }

                //Send socket message via Queue (MUST push to worker job list in Redis)
                $this->lib_mpc->add($this->handler_class . '/onMessage', ['sid' => &$sock_id, 'msg' => &$socket_msg]);

                unset($socket_msg);
            }
        }

        unset($clients, $sock_id, $client);
    }

    /**
     * Send handshake response
     *
     * @param string $sock_id
     * @param string $socket_msg
     *
     * @return bool
     */
    public function sendHandshake(string $sock_id, string $socket_msg): bool
    {
        //Get WebSocket Key & Protocol
        $ws_key   = $this->wsGetKey($socket_msg);
        $ws_proto = $this->wsGetProto($socket_msg);

        try {
            //Call registered onHandshake
            $handshake_status = true === $this->handler_object->onHandshake($sock_id, $ws_proto) ? 1 : 0;
        } catch (\Throwable $throwable) {
            //Catch onHandshake Exception
            $this->app->showDebug($throwable, true);
            unset($throwable, $socket_msg);
            $handshake_status = -1;
        }

        if (1 !== $handshake_status) {
            //Close when protocol invalid
            $this->sendMsg($sock_id, 'Http/1.1 406 Not Acceptable' . "\r\n\r\n");
            $this->showLog('exit', $sock_id . ': Protocol NOT Allowed!');

            unset($sock_id, $socket_msg, $ws_key, $ws_proto, $handshake_status);
            return false;
        }

        //Send handshake response
        $this->sendMsg($sock_id, $this->wsGetHandshake($ws_key, $ws_proto));

        unset($sock_id, $socket_msg, $ws_key, $ws_proto, $handshake_status);
        return true;
    }
}