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
    public static $port = 60000;

    //Buffer
    public static $buffer = 65535;

    //System information
    private static $sys_info = [];


    public static function run(): void
    {
        //Validate Socket arguments
        self::validate();

        //Get system information
        self::sys_info();



    }


    /**
     * Validate Socket arguments
     */
    private static function validate(): void
    {
        //Socket type
        if (!in_array(self::$type, ['server', 'sender'], true)) {
            if (DEBUG) {
                fwrite(STDOUT, 'Socket Type ERROR!' . PHP_EOL);
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

        //Socket Port
        if (1 > self::$port || 65535 < self::$port) {
            if (DEBUG) {
                fwrite(STDOUT, 'Socket Port ERROR!' . PHP_EOL);
                fclose(STDOUT);
            }
            exit;
        }
    }

    /**
     * Get system information
     */
    private static function sys_info(): void
    {
        $os = PHP_OS;
        try {
            $platform = '\\core\\ctr\\os\\' . strtolower($os);
            $exec_info = $platform::exec_info();
            if (0 === $exec_info['pid']) return;
            self::$sys_info['PHP_PID'] = &$exec_info['pid'];
            self::$sys_info['PHP_CMD'] = &$exec_info['cmd'];
            self::$sys_info['PHP_EXEC'] = &$exec_info['path'];
            self::$sys_info['SYS_HASH'] = $platform::get_hash();
            unset($platform, $exec_info);
        } catch (\Throwable $exception) {
            if (DEBUG) {
                fwrite(STDOUT, $os . ' NOT fully supported yet! ' . $exception->getMessage() . PHP_EOL);
                fclose(STDOUT);
            }
        }
        unset($os);
    }
}