<?php

/**
 * Socket Module
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
class ctrl_socket
{
    //Data to send
    public static $data = '';

    //Socket Variables
    public static $address = '127.0.0.1';
    public static $protocol = 'UDP';
    public static $udp_port = 65000;
    public static $tcp_port = 60000;
    public static $timeout = 600;

    //Socket Properties
    private static $socket_protocol = SOL_UDP;
    private static $socket_domain = AF_INET;
    private static $socket_type = SOCK_DGRAM;

    /**
     * Start Socket Server
     */
    public static function server()
    {
        switch (self::$protocol) {
            case 'UDP':
                self::$socket_protocol = SOL_UDP;
                self::$socket_type = SOCK_DGRAM;
                self::udp_server();
                break;
            case 'TCP':
                self::$socket_protocol = SOL_TCP;
                self::$socket_type = SOCK_STREAM;
                self::tcp_server();
                break;
            default:
                exit('Unsupported Protocol!');
                break;
        }
    }

    /**
     * Start Socket Client
     */
    public static function client()
    {
        switch (self::$protocol) {
            case 'UDP':
                self::$socket_protocol = SOL_UDP;
                self::$socket_type = SOCK_DGRAM;
                self::udp_sender();
                break;
            case 'TCP':
                self::$socket_protocol = SOL_TCP;
                self::$socket_type = SOCK_STREAM;
                self::tcp_sender();
                break;
            default:
                exit('Unsupported Protocol!');
                break;
        }
    }

    /**
     * UDP Server
     */
    private static function udp_server()
    {
        $start = true;
        $lock_file = CLI_WORK_PATH . self::$protocol . self::$udp_port;
        if (is_file($lock_file)) {
            $lock_time = (int)file_get_contents($lock_file);
            if (self::$timeout > time() - $lock_time) $start = false;
        }
        if ($start) {
            $socket = socket_create(self::$socket_domain, self::$socket_type, self::$socket_protocol);
            if (false !== $socket && socket_bind($socket, '0.0.0.0', self::$udp_port)) {
                while (true) {
                    if (0 < socket_recvfrom($socket, $data, 1024, 0, $from, $port)) exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $data);
                    file_put_contents($lock_file, time());
                    usleep(1000);
                }
                socket_close($socket);
            }
        }
    }

    /**
     * UDP Sender
     */
    public static function udp_sender()
    {
        $socket = socket_create(self::$socket_domain, self::$socket_type, self::$socket_protocol);
        if (false !== $socket) {
            socket_sendto($socket, self::$data, strlen(self::$data), 0, self::$address, self::$udp_port);
            socket_close($socket);
        }
    }

    /**
     * TCP Server
     */
    public static function tcp_server()
    {
        $start = true;
        $lock_file = CLI_WORK_PATH . self::$protocol . self::$tcp_port;
        if (is_file($lock_file)) {
            $lock_time = (int)file_get_contents($lock_file);
            if (self::$timeout > time() - $lock_time) $start = false;
        }
        if ($start) {
            ob_implicit_flush();
            $socket = socket_create(self::$socket_domain, self::$socket_type, self::$socket_protocol);
            if (false !== $socket && socket_bind($socket, '0.0.0.0', self::$tcp_port) && socket_listen($socket)) {
                $accept = socket_accept($socket);
                if (is_resource($accept)) {
                    while (true) {
                        socket_write($accept, self::$data);
                        $data = (string)socket_read($accept, 1024);
                        if ('' !== $data) $exec = exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $data);
                        if ('' !== $exec) socket_write($accept, $exec);
                        file_put_contents($lock_file, time());
                        usleep(1000);
                    }
                }
                socket_close($socket);
            }
        }
    }

    /**
     * Send via TCP
     */
    public static function tcp_sender()
    {
        $socket = socket_create(self::$socket_domain, self::$socket_type, self::$socket_protocol);
        if (false !== $socket && socket_connect($socket, self::$address, self::$tcp_port)) {
            while (true) {
                socket_write($socket, self::$data);
                $data = (string)socket_read($socket, 1024);
                if ('' !== $data) $exec = exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $data);
                if ('' !== $exec) socket_write($socket, $exec);
            }
            socket_shutdown($socket);
            socket_close($socket);
        }
    }
}