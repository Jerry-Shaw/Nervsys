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

class socket
{
    //Property (server / sender)
    public static $type = 'server';

    //Protocol (web / tcp / udp / bcst)
    public static $socket = 'tcp';

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
    public static $system_info = [];

    /**
     * Validate Socket arguments
     */
    public static function validate(): void
    {
        //Socket Type
        if (!in_array(self::$type, ['server', 'sender'], true)) {
            if (DEBUG) {
                fwrite(STDOUT, 'Socket Type ERROR!' . PHP_EOL);
                fclose(STDOUT);
            }
            exit;
        }

        //Socket Port
        if (1 > self::$port || 65535 < self::$port) {
            if (DEBUG) {
                fwrite(STDOUT, 'Socket Port ERROR!' . PHP_EOL);
                fclose(STDOUT);
            }
            exit;
        }

        //Socket Protocol
        if (!in_array(self::$socket, ['web', 'tcp', 'udp', 'bcst'], true)) {
            if (DEBUG) {
                fwrite(STDOUT, 'Socket Protocol ERROR!' . PHP_EOL);
                fclose(STDOUT);
            }
            exit;
        }
    }

    /**
     * Get system information
     */
    public static function sys_info(): void
    {
        $os = PHP_OS;
        try {
            $platform = '\\core\\ctr\\os\\' . strtolower($os);
            $exec_info = $platform::exec_info();
            if (0 === $exec_info['pid']) return;
            self::$system_info['PHP_PID'] = &$exec_info['pid'];
            self::$system_info['PHP_CMD'] = &$exec_info['cmd'];
            self::$system_info['PHP_EXEC'] = &$exec_info['path'];
            self::$system_info['SYS_HASH'] = $platform::get_hash();
            unset($platform, $exec_info);
        } catch (\Throwable $exception) {
            if (DEBUG) {
                fwrite(STDOUT, $os . ' NOT fully supported yet! ' . $exception->getMessage() . PHP_EOL);
                fclose(STDOUT);
            }
        }
        unset($os);
    }

    /**
     * Create TCP Server
     *
     * @return string
     */
    public static function tcp_create(): string
    {
        //Create TCP Socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (
            false === $socket ||
            !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
            !socket_bind($socket, self::$host, self::$port) ||
            !socket_set_nonblock($socket) ||
            !socket_listen($socket)
        ) return '';

        //Add to Server
        $hash = hash('md5', uniqid(mt_rand(), true));
        self::$server[$hash] = $socket;
        unset($socket);
        return $hash;
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
                if (0 === (int)socket_recvfrom($client, $msg, self::buffer, 0, $ip, $port)) {
                    $key = array_search($client, self::$client[$hash]);
                    if (false !== $key) unset(self::$client[$hash][$key], $key);
                    continue;
                }
                unset($ip, $port);

                //Trim message
                $msg = trim($msg);
                if ('' === $msg) continue;

                //Run command & Send result
                exec(self::$system_info['PHP_EXEC'] . ' ' . ROOT . '/api.php ' . $msg, $output, $status);
                unset($msg);

                if (0 === $status && !empty($output)) {
                    if (!is_string($output)) $output = json_encode($output);
                    socket_write($client, $output . PHP_EOL);
                }
                unset($output, $status);
            }
            unset($read, $write, $except, $client);
        }
        socket_close($socket);
    }

    /**
     * Send group of message
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
}