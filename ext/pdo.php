<?php

/**
 * PDO Connector Extension
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

class pdo
{
    /**
     * Default settings for PDO
     */
    public static $type    = 'mysql';
    public static $host    = '127.0.0.1';
    public static $port    = 3306;
    public static $user    = 'root';
    public static $pwd     = '';
    public static $db_name = '';
    public static $charset = 'utf8mb4';
    public static $persist = true;

    //Connect options
    private static $option = [];

    //Common Database types
    const type = ['mysql', 'mssql', 'pgsql', 'oci'];

    /**
     * Build DSN & options
     *
     * @return string
     */
    private static function build(): string
    {
        //Build DSN
        $dsn = self::$type . ':';

        //Build option
        self::$option[\PDO::ATTR_PERSISTENT] = (bool)self::$persist;

        switch (self::$type) {
            case 'mysql':
                $dsn .= 'host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$db_name . ';charset=' . self::$charset;
                self::$option[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . self::$charset;
                break;
            case 'mssql':
                $dsn .= 'host=' . self::$host . ',' . self::$port . ';dbname=' . self::$db_name . ';charset=' . self::$charset;
                break;
            case 'pgsql':
                $dsn .= 'host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$db_name . ';user=' . self::$user . ';password=' . self::$pwd;
                break;
            case 'oci':
                $dsn .= 'dbname=//' . self::$host . ':' . self::$port . '/' . self::$db_name . ';charset=' . self::$charset;
                break;
            default:
                exit('Database type NOT support!');
        }

        return $dsn;
    }

    /**
     * @return \PDO
     */
    public static function connect(): \PDO
    {
        try {
            if ('' === (string)self::$db_name) throw new \PDOException('PDO: Database Name ERROR!');
            return new \PDO(self::build(), (string)self::$user, (string)self::$pwd, self::$option);
        } catch (\PDOException $error) {
            exit('PDO: Failed to connect! ' . $error->getMessage());
        }
    }
}