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
     *
     * http:server
     *
     * udp:broadcast
     *
     * @var string
     */
    public static $type = 'tcp:server';

    //Host
    public static $host = '0.0.0.0';

    //Port
    public static $port = 65535;

    //Buffer
    public static $buffer = 65535;

    //Domain
    public static $domain = AF_INET;

    //Socket id
    private static $socket = '';

    //Server pool
    private static $server = [];

    /**
     * Create Socket
     *
     * @return bool
     */
    public static function create(): bool
    {
        //Check Port
        if (1 > self::$port || 65535 < self::$port) {
            debug('Socket Port ERROR!');
            return false;
        }

        //Create socket resource
        $socket = false === strpos(self::$type, 'udp:')
            ? socket_create(self::$domain, SOCK_STREAM, SOL_TCP)
            : socket_create(self::$domain, SOCK_DGRAM, SOL_UDP);

        if (false === $socket) {
            debug('Socket Creation Failed!');
            return false;
        }

        //Set socket options
        switch (self::$type) {
            case 'tcp:server':
                if (
                    !socket_bind($socket, self::$host, self::$port) ||
                    !socket_listen($socket) ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_nonblock($socket)
                ) return false;
                break;
            case 'tcp:sender':
                if (
                    !socket_connect($socket, self::$host, self::$port) ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_nonblock($socket)
                ) return false;
                break;
            case 'udp:server':
                if (
                    !socket_bind($socket, self::$host, self::$port) ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_nonblock($socket)
                ) return false;
                break;
            case 'udp:sender':
                if (
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_nonblock($socket)
                ) return false;
                break;
            case 'http:server':
                if (
                    !socket_bind($socket, self::$host, self::$port) ||
                    !socket_listen($socket) ||
                    !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
                    !socket_set_block($socket)
                ) return false;
                break;
            case 'udp:broadcast':
                if (
                    !socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1) ||
                    !socket_set_nonblock($socket)
                ) return false;
                break;
            default:
                debug('Socket Type ERROR!');
                return false;
                break;
        }

        //Generator unique hash for socket resource
        self::$socket = hash('md5', uniqid(mt_rand(), true));
        self::$server[self::$socket] = &$socket;
        unset($socket);
        return true;
    }

    /**
     * Listen to connection
     *
     * @param array $read
     */
    public static function listen(array &$read = []): void
    {
        $write = $except = [];
        $read[self::$socket] = self::$server[self::$socket];
        socket_select($read, $write, $except, null);
        unset($write, $except);
    }

    /**
     * Accept new client
     *
     * @param array $read
     * @param array $client
     */
    public static function accept(array &$read, array &$client): void
    {
        //Set Socket for http:server
        if ('http:server' === self::$type) $read[self::$socket] = self::$server[self::$socket];

        //Check Socket resource
        if (!isset($read[self::$socket])) return;

        //Accept new client
        $accept = socket_accept($read[self::$socket]);
        if (false === $accept) return;
        unset($read[self::$socket]);

        $client[hash('md5', uniqid(mt_rand(), true))] = &$accept;
        unset($accept);
    }

    /**
     * Read message & maintain clients
     *
     * @param array $read
     * @param array $client
     *
     * @return array
     */
    public static function read(array $read = [], array &$client = []): array
    {
        $receive = [];

        //Reset read list for tcp:sender / udp:sender
        if (false !== strpos(self::$type, ':sender')) $read = [self::$socket => self::$server[self::$socket]];

        //Read message and maintain clients
        if (false === strpos(self::$type, 'udp:')) {
            foreach ($read as $key => $sock) {
                if (0 === (int)@socket_recv($sock, $msg, self::$buffer, 0)) {
                    unset($client[$key]);
                    socket_close($sock);
                    continue;
                }
                //Gather message
                $receive[$key] = ['msg' => trim($msg)];
            }
        } else {
            foreach ($read as $key => $sock) {
                if (0 === (int)@socket_recvfrom($sock, $msg, self::$buffer, 0, $from, $port)) {
                    unset($client[$key]);
                    socket_close($sock);
                    continue;
                }
                //Gather message
                $receive[$key] = ['msg' => trim($msg), 'from' => $from, 'port' => $port];
            }
            unset($from, $port);
        }

        unset($read, $key, $sock, $msg);
        return $receive;
    }

    /**
     * Write message & maintain clients
     *
     * @param array $write
     * @param array $client
     *
     * @return array
     */
    public static function write(array $write, array &$client = []): array
    {
        $send = $close = [];

        //Prepare data
        foreach ($write as $key => $item) {
            //Check message
            if (!isset($item['msg'])) {
                $send[$key] = false;
                unset($write[$key]);
                continue;
            }

            //Prepare data
            $write[$key]['msg'] .= PHP_EOL;
            $write[$key]['len'] = strlen($write[$key]['msg']);

            //Fix host & socket
            if (!isset($item['host'])) $write[$key]['host'] = self::$host;
            if (!isset($item['sock'])) $write[$key]['sock'] = empty($client) || !isset($client[$key]) ? self::$server[self::$socket] : $client[$key];
        }

        //Send message and maintain clients
        if (false === strpos(self::$type, 'udp:')) {
            foreach ($write as $key => $item) {
                if ($item['len'] === (int)@socket_send($item['sock'], $item['msg'], $item['len'], 0)) {
                    //Close connection for http:server
                    if ('http:server' === self::$type) socket_close($item['sock']);
                    //Message send succeeded
                    $send[$key] = true;
                } else $close[$key] = $item['sock'];
            }
        } else {
            foreach ($write as $key => $item) {
                if ($item['len'] === (int)@socket_sendto($item['sock'], $item['msg'], $item['len'], 0, $item['host'], self::$port)) $send[$key] = true;
                else $close[$key] = $item['sock'];
            }
        }

        //Close disconnected clients
        foreach ($close as $key => $item) {
            unset($client[$key]);
            socket_close($item);
            $send[$key] = false;
        }

        unset($write, $close, $key, $item);
        return $send;
    }
}