<?php

/**
 * Socket Extension
 *
 * Copyright 2017 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2018 秋水之冰 <27206617@qq.com>
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

namespace ext;

class socket
{
    //Socket resource
    public static $sock = null;

    //Timeout (in seconds)
    public static $timeout = 60;

    //Socket type
    private static $type = '';

    /**
     * Get Socket param data
     *
     * @param string $proto
     * @param string $host
     *
     * @return array
     */
    private static function param(string $proto, string $host): array
    {
        $param = [];

        $param['domain'] = false === strpos($host, ':') ? AF_INET : AF_INET6;
        $param['type'] = 'udp' === $proto ? SOCK_DGRAM : SOCK_STREAM;
        $param['protocol'] = getprotobyname($proto);

        self::$type = &$proto;

        unset($proto, $host);
        return $param;
    }

    /**
     * Create Server
     *
     * @param string $proto
     * @param string $host
     * @param int    $port
     * @param bool   $block
     *
     * @throws \Exception
     */
    public static function server(string $proto, string $host, int $port, bool $block = false): void
    {
        $param = self::param($proto, $host);
        $socket = socket_create($param['domain'], $param['type'], $param['protocol']);

        if (false === $socket) {
            throw new \Exception('Server ERROR!');
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => self::$timeout, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => self::$timeout, 'usec' => 0]);

        if (!socket_bind($socket, $host, $port)) {
            throw new \Exception('Bind failed: ' . socket_strerror(socket_last_error($socket)));
        }

        if ('udp' !== $proto && !socket_listen($socket)) {
            throw new \Exception('Listen failed: ' . socket_strerror(socket_last_error($socket)));
        }

        $block ? socket_set_block($socket) : socket_set_nonblock($socket);

        self::$sock = &$socket;

        unset($proto, $host, $port, $block, $param, $socket);
    }

    /**
     * Create Client
     *
     * @param string $proto
     * @param string $host
     * @param int    $port
     * @param bool   $block
     * @param bool   $broadcast
     *
     * @throws \Exception
     */
    public static function client(string $proto, string $host, int $port, bool $block = false, bool $broadcast = false): void
    {
        $param = self::param($proto, $host);
        $socket = socket_create($param['domain'], $param['type'], $param['protocol']);

        if (false === $socket) {
            throw new \Exception('Client ERROR!');
        }

        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, ['sec' => self::$timeout, 'usec' => 0]);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => self::$timeout, 'usec' => 0]);

        if ($broadcast) {
            socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
        }

        if ('udp' !== $proto && !socket_connect($socket, $host, $port)) {
            throw new \Exception('Connect failed: ' . socket_strerror(socket_last_error($socket)));
        }

        $block ? socket_set_block($socket) : socket_set_nonblock($socket);

        self::$sock = &$socket;

        unset($proto, $host, $port, $block, $broadcast, $param, $socket);
    }

    /**
     * Watch Connection
     *
     * @param array    $read
     * @param array    $write
     * @param int|null $timeout
     *
     * @return int
     */
    public static function watch(array &$read, array &$write, int $timeout = null): int
    {
        $except = [];
        $read[] = self::$sock;

        $watch = socket_select($read, $write, $except, $timeout);

        if (false === $watch) {
            $read = $write = [];
        }

        unset($timeout, $except);
        return (int)$watch;
    }

    /**
     * Accept Client
     *
     * @param $client
     *
     * @return bool
     */
    public static function accept(&$client): bool
    {
        $client = socket_accept(self::$sock);

        return false !== $client;
    }

    /**
     * Read message
     *
     * @param     $socket
     * @param int $size
     * @param int $flags
     *
     * @return string
     */
    public static function read($socket, int $size = 4096, int $flags = 0): string
    {
        'udp' === self::$type
            ? socket_recvfrom($socket, $msg, $size, $flags, $from, $port)
            : socket_recv($socket, $msg, $size, $flags);

        unset($socket, $size, $flags);
        return trim((string)$msg);
    }

    /**
     * Send data
     *
     * @param        $socket
     * @param string $data
     * @param string $host
     * @param int    $port
     * @param int    $flags
     *
     * @return bool
     */
    public static function send($socket, string $data, string $host = '', int $port = 0, int $flags = 0): bool
    {
        $data .= PHP_EOL;
        $size = strlen($data);

        $send = 'udp' === self::$type
            ? socket_sendto($socket, $data, $size, $flags, $host, $port)
            : socket_send($socket, $data, $size, $flags);

        unset($socket, $data, $host, $port, $flags);
        return $size === $send;
    }

    /**
     * Close socket
     *
     * @param $socket
     */
    public static function close($socket): void
    {
        socket_close($socket);
        unset($socket, $exist);
    }
}