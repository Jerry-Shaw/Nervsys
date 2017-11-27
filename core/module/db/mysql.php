<?php

/**
 * MySQL Module
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

namespace core\db;

class mysql
{
    /**
     * Default settings for MySQL
     */
    public static $host       = '127.0.0.1';
    public static $port       = 3306;
    public static $user       = 'root';
    public static $pwd        = '';
    public static $db         = '';
    public static $charset    = 'utf8mb4';
    public static $persistent = true;

    /**
     * @return \PDO
     */
    public static function connect(): \PDO
    {
        try {
            if ('' === (string)self::$db) throw new \Exception('MySQL: Database Name ERROR!');
            $dsn = 'mysql:host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$db . ';charset=' . self::$charset;
            $options = [
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . self::$charset,
                \PDO::ATTR_PERSISTENT         => (bool)self::$persistent
            ];
            $mysql = new \PDO($dsn, (string)self::$user, (string)self::$pwd, $options);
            $mysql->exec('SET NAMES ' . self::$charset);
            unset($dsn, $options);
        } catch (\Exception $error) {
            exit('MySQL: Failed to connect! ' . $error->getMessage());
        }
        return $mysql;
    }
}