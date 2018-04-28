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
    //Master socket
    public static $master = null;

    //Client list
    public static $client = [];

    /**
     * Create Server
     *
     * @param string $type
     * @param string $host
     * @param int    $port
     * @param bool   $async
     *
     * @throws \Exception
     */
    public static function server(string $type, string $host, int $port, bool $async = false): void
    {
        $flags = 'tcp' === $type ? STREAM_SERVER_BIND | STREAM_SERVER_LISTEN : STREAM_SERVER_BIND;
        $socket = stream_socket_server($type . '://' . $host . ':' . $port, $errno, $errstr, $flags);

        if (false === $socket || stream_set_blocking($socket, !$async)) throw new \Exception('Server Error (' . $errno . '): ' . $errstr);

        self::$master = &$socket;

        unset($type, $host, $port, $async, $flags, $socket);
    }

    /**
     * Create Client
     *
     * @param string $type
     * @param string $host
     * @param int    $port
     * @param bool   $async
     * @param int    $timeout
     *
     * @throws \Exception
     */
    public static function client(string $type, string $host, int $port, bool $async = false, int $timeout = 10): void
    {
        $flags = $async ? STREAM_CLIENT_ASYNC_CONNECT | STREAM_CLIENT_PERSISTENT : STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT;
        $socket = stream_socket_client($type . '://' . $host . ':' . $port, $errno, $errstr, $timeout, $flags);

        if (false === $socket) throw new \Exception('Client Error (' . $errno . '): ' . $errstr);

        self::$master = &$socket;

        unset($type, $host, $port, $async, $timeout, $flags, $socket);
    }

    /**
     * Watch Client
     *
     * @param array    $read
     * @param array    $write
     * @param int|null $timeout
     */
    public static function watch(array &$read, array &$write, int $timeout = null): void
    {
        $except = [];
        $read[] = self::$master;

        $watch = stream_select($read, $write, $except, $timeout);
        if (false === $watch) $read = $write = [];

        unset($timeout, $except, $watch);
    }

    /**
     * Accept Client
     *
     * @param array $read
     * @param array $client
     */
    public static function accept(array &$read, array &$client): void
    {
        if (empty($read)) return;

        $exist = array_search(self::$master, $read, true);
        if (false === $exist) return;

        $accept = stream_socket_accept(self::$master);
        if (false !== $accept) $client[] = &$accept;

        unset($read[$exist], $exist, $accept);
    }

    /**
     * Read message
     *
     * @param $socket
     *
     * @return string
     */
    public static function read($socket): string
    {
        $msg = fgetc($socket);
        if ('' === $msg) return '';

        $msg .= fgets($socket);

        unset($socket);
        return trim($msg);
    }

    /**
     * Send data
     *
     * @param        $socket
     * @param string $data
     *
     * @return bool
     */
    public static function send($socket, string $data): bool
    {
        $data .= PHP_EOL;

        $send = strlen($data) === fwrite($socket, $data);

        unset($socket, $data);
        return $send;
    }

    /**
     * Close socket
     *
     * @param $socket
     */
    public static function close($socket): void
    {
        fclose($socket);

        $exist = array_search($socket, self::$client, true);
        if (false !== $exist) unset(self::$client[$exist]);

        unset($socket, $exist);
    }
}