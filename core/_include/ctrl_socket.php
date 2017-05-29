<?php

/**
 * Socket Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 * Author Yara <314850412@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 * Copyright 2017 Yara
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
class ctrl_socket
{
    //UDP Settings
    public static $udp_port = 64000;
    public static $udp_address = '255.255.255.255';
    public static $udp_broadcast = '255.255.255.255';

    //TCP Settings
    public static $tcp_port = 60000;
    public static $tcp_address = '127.0.0.1';

    /**
     * UDP Broadcast
     *
     * @param string $data
     */
    public static function udp_broadcast(string $data)
    {
        if ('' !== $data) {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if (false !== $socket) {
                $length = strlen($data);
                if (!socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1) || $length !== (int)socket_sendto($socket, $data, $length, 0, self::$udp_broadcast, self::$udp_port)) echo 'Broadcast Error!';
                socket_close($socket);
                unset($length);
            }
            unset($socket);
        }
        unset($data);
    }

    /**
     * UDP Sender
     *
     * @param string $data
     */
    public static function udp_sender(string $data)
    {
        if ('' !== $data) {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if (false !== $socket) {
                if (0 === (int)socket_sendto($socket, $data, strlen($data), 0, self::$udp_address, self::$udp_port)) echo 'UDP Send Error!';
                socket_close($socket);
            }
            unset($socket);
        }
        unset($data);
    }

    /**
     * UDP Server
     */
    public static function udp_server()
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (false !== $socket && socket_set_nonblock($socket) && socket_bind($socket, '0.0.0.0', self::$udp_port)) {
            while (true) {
                if (0 < socket_recvfrom($socket, $data, 4096, 0, $from, $port)) {
                    $data = (string)exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $data);
                    if ('' !== $data) {
                        $cmd = json_decode($data, true);
                        if (isset($cmd) && isset($cmd['result'])) {
                            foreach ($cmd['result'] as $value) {
                                if (is_string($value) && '' !== $value) {
                                    self::$udp_address = &$from;
                                    self::udp_sender($value);
                                }
                            }
                        }
                        unset($cmd);
                    }
                    unset($data);
                }
                usleep(1000);
            }
            socket_close($socket);
        }
    }

    /**
     * TCP Sender
     *
     * @param string $data
     */
    public static function tcp_sender(string $data)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false !== $socket && socket_connect($socket, self::$tcp_address, self::$tcp_port)) {
            if (0 === socket_write($socket, $data)) echo 'TCP Send Error!';
            $data = (string)socket_read($socket, 4096);
            if ('' !== $data) exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $data);
            socket_shutdown($socket);
            socket_close($socket);
        }
        unset($data, $socket);
    }

    /**
     * TCP Server
     */
    public static function tcp_server()
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false !== $socket && socket_bind($socket, '0.0.0.0', self::$tcp_port) && socket_listen($socket)) {
            $accept = socket_accept($socket);
            if (is_resource($accept)) {
                while (true) {
                    $data = (string)socket_read($accept, 4096);
                    if ('' !== $data) {
                        $data = (string)exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $data);
                        if ('' !== $data) {
                            $cmd = json_decode($data, true);
                            if (isset($cmd) && isset($cmd['result'])) foreach ($cmd['result'] as $value) if (is_string($value) && '' !== $value) socket_write($accept, $value);
                            unset($cmd);
                        }
                    }
                    unset($data);
                    usleep(1000);
                }
            }
            socket_close($socket);
        }
    }
}