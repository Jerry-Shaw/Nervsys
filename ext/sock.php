<?php

/**
 * Socket Extension
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ext;

use core\ctr\os;
use core\ctr\router\cli;

class sock
{
    /**
     * Protocol and type
     * Available as follows:
     *
     * tcp:server
     * tcp:sender
     *
     * udp:server
     * udp:sender
     * udp:broadcast
     *
     * web:server
     * http:server
     *
     * @var string
     */
    public static $sock = 'tcp:server';

    //Host
    public static $host = '0.0.0.0';

    //Port
    public static $port = 65535;

    //Buffer
    public static $buffer = 65535;

    //Server group
    public static $server = [];

    //Client group
    public static $client = [];

    //System information
    public static $system = [];

    /**
     * Get system information
     */
    public static function sys_info(): void
    {
        $env_info = os::get_env();
        if (empty($env_info)) return;
        self::$system = array_merge(self::$system, $env_info);
        self::$system['SYS_HASH'] = os::get_hash();
        unset($env_info);
    }

    /**
     * Create Socket
     *
     * @return string
     */
    public static function create(): string
    {
        //Check Port
        if (1 > self::$port || 65535 < self::$port) return '';

        //Create socket resource
        switch (self::$sock) {
            case 'tcp:server':
            case 'web:server':
            case 'http:server':
                $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                if (
                    false === $socket ||
                    !socket_bind($socket, self::$host, self::$port) ||
                    !socket_listen($socket) ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_nonblock($socket)
                ) return '';
                break;
            case 'tcp:sender':
                $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                if (
                    false === $socket ||
                    !socket_connect($socket, self::$host, self::$port) ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1)
                ) return '';
                break;
            case 'udp:server':
                $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                if (
                    false === $socket ||
                    !socket_bind($socket, self::$host, self::$port) ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_block($socket)
                ) return '';
                break;
            case 'udp:sender':
                $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                if (
                    false === $socket ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_nonblock($socket)
                ) return '';
                break;
            case 'udp:broadcast':
                $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                if (
                    false === $socket ||
                    !socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1) ||
                    !socket_set_nonblock($socket)
                ) return '';
                break;
            default:
                stderr('Socket Protocol ERROR!');
                return '';
                break;
        }

        //Generator unique hash for socket resource
        $hash = hash('md5', uniqid(mt_rand(), true));
        self::$server[$hash] = &$socket;
        unset($socket);
        return $hash;
    }

    /**
     * Listen to connection
     *
     * @param string $hash
     * @param array  $read
     */
    public static function listen(string $hash, array &$read): void
    {
        //Only for server
        if (!in_array(self::$sock, ['tcp:server', 'udp:server', 'web:server', 'http:server'], true)) {
            stderr('Socket Protocol ERROR!');
            return;
        }

        $write = $except = [];
        $read[$hash] = self::$server[$hash];

        //Watch clients
        socket_select($read, $write, $except, null);
        unset($hash, $write, $except);
    }

    /**
     * Accept new client (for tcp / web)
     *
     * @param string $hash
     * @param array  $read
     * @param array  $clients
     */
    public static function accept(string $hash, array &$read, array &$clients): void
    {
        if (!isset($read[$hash])) return;

        $accept = socket_accept($read[$hash]);
        if (false === $accept) return;
        unset($read[$hash]);

        $clients[hash('md5', uniqid(mt_rand(), true))] = &$accept;
        unset($hash, $accept);
    }

    /**
     * Read message & maintain clients
     *
     * @param array $read
     * @param array $clients
     *
     * @return array
     */
    public static function read(array &$read, array &$clients): array
    {
        $message = [];

        foreach ($read as $key => $sock) {
            //Read and remove disconnected clients
            if (0 === (int)@socket_recvfrom($sock, $msg, self::$buffer, 0, $from, $port)) {
                unset($read[$key], $clients[$key]);
                socket_close($sock);
                continue;
            }

            unset($read[$key]);
            //Gather message
            $message[$key] = ['msg' => trim($msg), 'from' => $from, 'port' => $port];
        }

        unset($key, $sock, $msg, $from, $port);
        return $message;
    }


    public static function send()
    {

    }


    /**
     * Start TCP Server
     *
     * @param string $hash
     */
    public static function tcp_start(string $hash): void
    {
        if (!isset(self::$server[$hash])) return;

        //Run TCP Server
        $socket = self::$server[$hash];
        while (true) {
            //Copy client list
            $read = isset(self::$client[$hash]) && is_array(self::$client[$hash]) ? self::$client[$hash] : [];
            $read[] = $socket;
            $write = $except = [];

            //Select connections
            $select = socket_select($read, $write, $except, 0);
            if (false === $select || 0 === $select) continue;
            unset($select);

            //New client
            if (in_array($socket, $read, true)) {
                //Accept client
                $accept = socket_accept($socket);
                if (false !== $accept) self::$client[$hash][] = $accept;
                unset($accept);

                //Remove from read list
                $key = array_search($socket, $read);
                if (false !== $key) unset($read[$key], $key);
            }

            //Process client message
            foreach ($read as $client) {
                //Read message and remove disconnected clients
                if (0 === (int)socket_recvfrom($client, $msg, self::buffer, 0, $from, $port)) {
                    $key = array_search($client, self::$client[$hash]);
                    if (false !== $key) unset(self::$client[$hash][$key], $key);
                    continue;
                }

                //Trim message
                $msg = trim($msg);
                if ('' === $msg) continue;

                //Run command
                exec(self::$system['PHP_EXEC'] . ' ' . ROOT . '/api.php ' . $msg, $output, $status);
                if (0 !== $status || empty($output)) continue;

                //Send result
                socket_write($client, implode(PHP_EOL, $output) . PHP_EOL);
                unset($msg, $from, $port, $output, $status);
            }
            unset($read, $write, $except, $client);
        }
        socket_close($socket);
    }

    /**
     * Send group of message via TCP
     *
     * @param array $data
     *
     * @return array
     */
    public static function tcp_sender(array $data): array
    {
        if (empty($data)) return [];

        //Start TCP connection
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false === $socket || !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) || !socket_connect($socket, self::$host, self::$port)) return [];

        //Send data
        $result = [];
        foreach ($data as $msg) {
            $msg = trim($msg);
            if ('' === $msg) {
                $result[] = '';
                continue;
            }

            $write = socket_write($socket, $msg . PHP_EOL);
            if (false === $write || 0 === $write) {
                $result[] = '';
                continue;
            }
            unset($write);

            $return = socket_read($socket, self::buffer, PHP_BINARY_READ);
            if (false === $return) {
                $result[] = '';
                continue;
            }

            $result[] = trim($return);
            unset($return);
        }
        socket_shutdown($socket);
        socket_close($socket);

        unset($data, $socket, $msg);
        return $result;
    }

    /**
     * Start UDP Server
     *
     * @param string $hash
     */
    public static function udp_start(string $hash): void
    {
        if (!isset(self::$server[$hash])) return;

        //Run TCP Server
        $socket = self::$server[$hash];
        while (true) {
            if (!socket_set_block($socket)) return;

            //Read message
            if (0 === (int)socket_recvfrom($socket, $msg, self::buffer, 0, $from, $port)) continue;

            //Trim message
            $msg = trim($msg);
            if ('' === $msg) continue;

            //Run command
            exec(self::$system['PHP_EXEC'] . ' ' . ROOT . '/api.php ' . $msg, $output, $status);
            if (0 !== $status || empty($output)) continue;

            $result = implode(PHP_EOL, $output) . PHP_EOL;
            $length = strlen($result);

            //Send result
            if (!socket_set_nonblock($socket) || $length !== (int)socket_sendto($socket, $result, $length, 0, $from, self::$port)) return;
            unset($msg, $from, $port, $output, $status, $result, $length);
        }
        socket_close($socket);
    }

    /**
     * Send group of message via UDP
     *
     * @param array $data
     * @param int   $type (SO_REUSEADDR / SO_BROADCAST)
     *
     * @return array
     */
    public static function udp_sender(array $data, int $type): array
    {
        if (empty($data)) return [];

        //Start TCP connection
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (false === $socket || !socket_set_option($socket, SOL_SOCKET, $type, 1)) return [];

        //Send data
        $result = [];
        foreach ($data as $msg) {
            if (!socket_set_nonblock($socket)) {
                $result[] = false;
                continue;
            }

            $msg = trim($msg);
            if ('' === $msg) {
                $result[] = false;
                continue;
            }

            $length = strlen($msg);
            if ($length !== (int)socket_sendto($socket, $msg, $length, 0, self::$host, self::$port)) {
                $result[] = false;
                continue;
            }

            unset($length);
            $result[] = true;
        }
        socket_shutdown($socket);
        socket_close($socket);

        unset($data, $type, $socket, $msg);
        return $result;
    }


    //======================================


    public function genKey()
    {
        // 参考 RFC 文档（Internet 标准 WebSocket 协议部分）
        $guid = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $client_key = $this->header['sec_websocket_key'];
        $client_key = trim($client_key);
        $client_key .= $guid;
        $client_key = sha1($client_key, true);
        $client_key = base64_encode($client_key);

        return $client_key;
    }

    //Port Settings
    public static $tcp_port = 62000;
    public static $udp_port = 64000;

    //Local identity
    private static $identity = '';

    //Socket buffer
    const buffer = 65535;

    //Identity name
    const id = 'id';


}