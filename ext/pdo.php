<?php

/**
 * PDO Connector Extension
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

class pdo
{
    /**
     * PDO settings
     */
    public static $type    = 'mysql';
    public static $host    = '127.0.0.1';
    public static $port    = 3306;
    public static $user    = 'root';
    public static $pwd     = '';
    public static $db_name = '';
    public static $charset = 'utf8mb4';
    public static $timeout = 10;
    public static $persist = true;

    //Current connection instance
    private static $connect = null;

    //Connection options
    private static $option = [];

    //Connection pool
    private static $pool = [];

    /**
     * Build DSN & options
     *
     * @return string
     * @throws \Exception
     */
    private static function dsn(): string
    {
        //Build DSN
        $dsn = self::$type . ':';

        //Build option
        self::$option[\PDO::ATTR_CASE] = \PDO::CASE_NATURAL;
        self::$option[\PDO::ATTR_ERRMODE] = \PDO::ERRMODE_EXCEPTION;
        self::$option[\PDO::ATTR_PERSISTENT] = self::$persist;
        self::$option[\PDO::ATTR_ORACLE_NULLS] = \PDO::NULL_NATURAL;
        self::$option[\PDO::ATTR_EMULATE_PREPARES] = false;
        self::$option[\PDO::ATTR_STRINGIFY_FETCHES] = false;
        self::$option[\PDO::ATTR_DEFAULT_FETCH_MODE] = \PDO::FETCH_ASSOC;

        //Fill up DSN & option
        switch (self::$type) {
            case 'mysql':
                $dsn .= 'host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$db_name . ';charset=' . self::$charset;
                self::$option[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . self::$charset;
                self::$option[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                self::$option[\PDO::ATTR_TIMEOUT] = self::$timeout;
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
                throw new \Exception('PDO: ' . self::$type . ' NOT support!');
        }

        return $dsn;
    }

    /**
     * Create PDO instance
     *
     * @param string $name
     *
     * @return \PDO
     * @throws \Exception
     */
    public static function connect(string $name = ''): \PDO
    {
        self::$connect = '' === $name
            ? (self::$connect ?? new \PDO(self::dsn(), self::$user, self::$pwd, self::$option))
            : (self::$pool[$name] ?? self::$pool[$name] = new \PDO(self::dsn(), self::$user, self::$pwd, self::$option));

        unset($name);
        return self::$connect;
    }

    /**
     * Close PDO instance
     *
     * @param string $name
     */
    public static function close(string $name = ''): void
    {
        if ('' === $name) {
            $key = array_search(self::$connect, self::$pool, true);

            if (false !== $key) {
                self::$pool[$key] = null;
                unset(self::$pool[$key]);
            }

            self::$connect = null;
        } else {
            if (!isset(self::$pool[$name])) return;
            if (self::$connect === self::$pool[$name]) self::$connect = null;

            self::$pool[$name] = null;
            unset(self::$pool[$name]);
        }
    }
}