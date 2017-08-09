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

namespace core\ctrl;

class socket
{
    //Port Settings
    public static $tcp_port = 62000;
    public static $udp_port = 64000;

    //Local identity
    private static $identity = '';

    //Socket buffer
    const buffer = 65535;

    //Identity name
    const id = 'id';

    /**
     * TCP Server
     */
    public static function server_tcp(): void
    {
        //Create TCP Socket
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (
            false === $socket ||
            !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
            !socket_bind($socket, '0.0.0.0', self::$tcp_port) ||
            !socket_set_nonblock($socket) ||
            !socket_listen($socket)
        ) return;
        //Initial local identity
        self::identify();
        //Initial client Pool
        pool::$data['TCP'] = [$socket];
        //Run TCP Server
        while (true) {
            //Copy client list
            $write = $except = [];
            $read = pool::$data['TCP'];
            //Select connections
            $select = socket_select($read, $write, $except, 0);
            if (false === $select || 0 === $select) continue;
            //Check client
            if (in_array($socket, $read, true)) {
                //Accept client
                $accept = socket_accept($socket);
                if (false === $accept) continue;
                pool::$data['TCP'][] = $accept;
                //Send message
                socket_write($accept, 'Welcome~' . PHP_EOL);
                //Show in console
                socket_getpeername($accept, $ip);
                echo 'New client: ' . $ip . PHP_EOL;
                //Remove from read list
                $key = array_search($socket, $read);
                unset($read[$key]);
            }
            //Process client data
            foreach ($read as $client) {
                //Read data and remove disconnected clients
                if (0 === (int)socket_recvfrom($client, $content, self::buffer, 0, $ip, $port)) {
                    $key = array_search($client, pool::$data['TCP']);
                    unset(pool::$data['TCP'][$key]);
                    continue;
                }
                //Trim data
                $content = trim($content);
                //Empty data
                if ('' === $content || false === strpos($content, ':')) {
                    $key = array_search($client, pool::$data['TCP']);
                    unset(pool::$data['TCP'][$key]);
                    continue;
                }
                //Parse data
                $data = self::parse_data($content);
                //Error data
                if (empty($data)) {
                    $key = array_search($client, pool::$data['TCP']);
                    unset(pool::$data['TCP'][$key]);
                    continue;
                }
                //Run command
                $result = self::run_cmd($data);
                //Send result
                if ('' !== $result) socket_write($client, crypt::encrypt($result, self::get_identity($data['mark'], 'pri')) . PHP_EOL);
            }
        }
        socket_close($socket);
    }

    /**
     * UDP Server
     */
    public static function server_udp(): void
    {
        //Create UDP Socket
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (
            false === $socket ||
            !socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1) ||
            !socket_bind($socket, '0.0.0.0', self::$udp_port) ||
            !socket_set_nonblock($socket)
        ) return;
        //Initial local identity
        self::identify();
        //Run UDP Server
        while (true) {
            //Read data and remove disconnected clients
            if (0 === (int)socket_recvfrom($socket, $content, self::buffer, 0, $ip, $port)) continue;
            //Trim data
            $content = trim($content);
            //Empty data
            if ('' === $content || false === strpos($content, ':')) continue;
            //Check data
            $data = explode(':', $content);
            //Skip self request
            if (self::$identity === $data[1]) continue;
            //Generate key file
            $file = CLI_CAS_PATH . $data[1];
            switch ($data[0]) {
                case 'broadcast':
                    if (2 === count($data)) self::act_broadcast($ip, $data[1], $file);
                    break;
                case 'receive':
                    if (4 === count($data)) self::act_receive($ip, $data[1], $file, $data[2], $data[3]);
                    break;
                case 'delete':
                    if (2 !== count($data)) continue;
                    $path = realpath($file . '.pub');
                    if (false !== $path) unlink($path);
                    unset($path);
                    break;
                case 'remove':
                    if (2 !== count($data)) continue;
                    foreach (['mrk', 'pub', 'pri'] as $ext) {
                        $path = realpath($file . '.' . $ext);
                        if (false !== $path) unlink($path);
                    }
                    unset($path);
                    break;
                default:
                    $data = self::parse_data($content);
                    if (empty($data)) continue;
                    $result = self::run_cmd($data);
                    if ('' !== $result) self::sender_udp($result, $ip, self::$udp_port, SO_REUSEADDR, $data['mark']);
                    break;
            }
            unset($content, $data, $file);
            usleep(1000);
        }
        socket_close($socket);
    }

    /**
     * TCP Sender
     *
     * @param string $data
     * @param string $host
     * @param int $port
     * @param int $type
     * @param string $mark
     */
    public static function sender_tcp(string $data, string $host, int $port, int $type, string $mark = ''): void
    {
        if ('' === $mark || '' === $data || '' === $host || 0 >= $port) return;
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (false === $socket || !socket_set_option($socket, SOL_SOCKET, $type, 1) || !socket_connect($socket, $host, $port)) return;
        $key = self::get_identity($mark, 'pri');
        if ('' === $key) return;
        if (0 === (int)socket_write($socket, crypt::encrypt($data, $key) . PHP_EOL)) return;
        $content = trim((string)socket_read($socket, self::buffer, PHP_BINARY_READ));
        if ('' === $content || false === strpos($content, ':')) return;
        $cmd = self::parse_data($content);
        if (empty($cmd)) return;
        $result = self::run_cmd($cmd);
        if ('' !== $result) socket_write($socket, crypt::encrypt($result, $key) . PHP_EOL);
        socket_shutdown($socket);
        socket_close($socket);
        unset($data, $host, $port, $mark, $type, $socket, $key, $content, $cmd, $result);
    }

    /**
     * UDP Sender
     *
     * @param string $data
     * @param string $host
     * @param int $port
     * @param int $type
     * @param string $mark
     */
    public static function sender_udp(string $data, string $host, int $port, int $type, string $mark = ''): void
    {
        if ('' === $data || '' === $host || 0 >= $port) return;
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (false === $socket) return;
        if ('' !== $mark) {
            $key = self::get_identity($mark, 'pri');
            if ('' === $key) return;
            $data = crypt::encrypt($data, $key);
            unset($key);
        }
        $length = strlen($data);
        if (!socket_set_option($socket, SOL_SOCKET, $type, 1) || $length !== (int)socket_sendto($socket, $data, $length, 0, $host, $port)) return;
        socket_close($socket);
        unset($data, $host, $port, $mark, $type, $socket, $length);
    }

    /**
     * Broadcast
     */
    public static function broadcast(): void
    {
        self::identify();
        while (true) {
            self::sender_udp('broadcast:' . self::$identity, '255.255.255.255', self::$udp_port, SO_BROADCAST, '');
            sleep(60);
        }
    }

    /**
     * Get local identity
     */
    private static function identify(): void
    {
        $identity = self::get_identity(self::id, 'mrk');
        if ('' === $identity) {
            $identity = get_uuid();
            file_put_contents(CLI_CAS_PATH . self::id . '.mrk', $identity);
        }
        self::$identity = &$identity;
        unset($identity);
    }

    /**
     * UDP Broadcast Response
     *
     * @param string $host
     * @param string $mark
     * @param string $file
     */
    private static function act_broadcast(string $host, string $mark, string $file): void
    {
        $mrk = $file . '.mrk';
        if (false !== realpath($mrk)) return;
        $pkey = crypt::get_pkey();
        file_put_contents($mrk, self::$identity);
        file_put_contents($file . '.pub', $pkey['public']);
        file_put_contents($file . '.pri', $pkey['private']);
        $cmd = 'receive:' . self::$identity . ':' . $mark . ':' . base64_encode($pkey['public']);
        self::sender_udp($cmd, $host, self::$udp_port, SO_REUSEADDR, '');
        unset($host, $mark, $file, $mrk, $pkey, $cmd);
    }

    /**
     * UDP Receive Response
     *
     * @param string $host
     * @param string $mark
     * @param string $file
     * @param string $uuid
     * @param string $pkey
     */
    private static function act_receive(string $host, string $mark, string $file, string $uuid, string $pkey): void
    {
        //Check identity
        if (self::$identity !== $uuid) {
            self::sender_udp('remove:' . $uuid, $host, self::$udp_port, SO_REUSEADDR, '');
            return;
        }
        //Check pkey
        $key = base64_decode($pkey, true);
        if (false === $key) {
            self::sender_udp('remove:' . $uuid, $host, self::$udp_port, SO_REUSEADDR, '');
            return;
        }
        //Check save status
        if ((int)file_put_contents($file . '.mrk', $uuid) !== strlen($uuid) || (int)file_put_contents($file . '.pub', $key) !== strlen($key)) {
            self::sender_udp('remove:' . $uuid, $host, self::$udp_port, SO_REUSEADDR, '');
            return;
        }
        $identity = self::get_identity($mark, 'mrk');
        $cmd = '' !== $identity ? 'delete:' : 'remove:';
        //Send back
        self::sender_udp($cmd . $identity, $host, self::$udp_port, SO_REUSEADDR, '');
        unset($host, $mark, $file, $uuid, $pkey, $key, $identity, $cmd);
    }

    /**
     * Parse received socket data
     *
     * @param string $content
     *
     * @return array
     */
    private static function parse_data(string $content): array
    {
        $data = explode(':', $content, 2);
        $pub_key = self::get_identity($data[0], 'pub');
        if ('' === $pub_key) return [];
        $decrypt = crypt::decrypt($data[1], $pub_key);
        if ('' === $decrypt) return [];
        $detail = json_decode($decrypt, true);
        if (!isset($detail) || !isset($detail['cmd']) || !isset($detail['mark']) || $data[0] !== $detail['mark']) return [];
        unset($content, $data, $pub_key, $decrypt);
        return $detail;
    }

    /**
     * Get identity
     *
     * @param string $mark
     * @param string $ext
     *
     * @return string
     */
    private static function get_identity(string $mark, string $ext): string
    {
        $file = realpath(CLI_CAS_PATH . $mark . '.' . $ext);
        $identity = false !== $file ? (string)file_get_contents($file) : '';
        unset($mark, $ext, $file);
        return $identity;
    }

    /**
     * Run command
     *
     * @param array $data
     *
     * @return string
     */
    private static function run_cmd(array $data): string
    {
        $data['cmd'] = trim($data['cmd']);
        if ('' === $data['cmd']) return '';
        if (false !== strpos($data['cmd'], '\\') && false === strpos($data['cmd'], ' ')) {
            //Parse CMD
            $model = explode('\\', $data['cmd']);
            $model = array_filter($model, 'remove_empty');
            //Check common model
            if (in_array(current($model), COMMON_LIST, true)) {
                $var = ['cmd' => &$data['cmd']];
                if (isset($data['map']) && '' !== $data['map']) $var['map'] = &$data['map'];
                //Parse query data
                parse_str($data['data'], $query);
                //Merge input data when exists
                if (!empty($query)) $var += $query;
                //Merge data variables
                pool::$data += $var;
                //Start Module
                pool::start();
                //Get result
                $result = pool::$pool;
                //Reset pool
                pool::$pool = [];
                //Reset data
                foreach ($var as $key => $value) unset(pool::$data[$key]);
                unset($var, $query, $key, $value);
            } else {
                //Internal calling
                $cmd = ' --cmd="' . $data['cmd'] . '"';
                if (isset($data['map']) && '' !== $data['map']) $cmd .= ' --map="' . $data['map'] . '"';
                if (isset($data['data']) && '' !== $data['data']) $cmd .= ' --data="' . $data['data'] . '"';
                $result = (string)exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $cmd);
            }
            unset($model);
        } else $result = (string)exec(CLI_EXEC_PATH . ' ' . ROOT . '/api.php ' . $data['cmd']);
        unset($data);
        return $result;
    }
}