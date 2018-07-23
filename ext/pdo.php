<?php

/**
 * PDO Connector Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

class pdo extends \PDO
{
    //PDO settings
    public static $type    = 'mysql';
    public static $host    = '127.0.0.1';
    public static $port    = 3306;
    public static $user    = 'root';
    public static $pwd     = '';
    public static $db      = '';
    public static $timeout = 10;
    public static $persist = true;
    public static $charset = 'utf8mb4';

    /**
     * pdo constructor.
     */
    public function __construct()
    {
        $param = self::build_dsn_opt();
        parent::__construct($param['dsn'], self::$user, self::$pwd, $param['opt']);
        unset($param);
    }

    /**
     * Build DSN & OPTION
     *
     * @return array
     */
    private static function build_dsn_opt(): array
    {
        $dsn_opt = [
            'dsn' => self::$type . ':',
            'opt' => [
                parent::ATTR_CASE               => parent::CASE_NATURAL,
                parent::ATTR_ERRMODE            => parent::ERRMODE_EXCEPTION,
                parent::ATTR_PERSISTENT         => self::$persist,
                parent::ATTR_ORACLE_NULLS       => parent::NULL_NATURAL,
                parent::ATTR_EMULATE_PREPARES   => false,
                parent::ATTR_STRINGIFY_FETCHES  => false,
                parent::ATTR_DEFAULT_FETCH_MODE => parent::FETCH_ASSOC
            ]
        ];

        switch (self::$type) {
            case 'mysql':
                $dsn_opt['dsn'] .= 'host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$db . ';charset=' . self::$charset;

                $dsn_opt['opt'][parent::ATTR_TIMEOUT]                  = self::$timeout;
                $dsn_opt['opt'][parent::MYSQL_ATTR_INIT_COMMAND]       = 'SET NAMES ' . self::$charset;
                $dsn_opt['opt'][parent::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                break;
            case 'mssql':
                $dsn_opt['dsn'] .= 'host=' . self::$host . ',' . self::$port . ';dbname=' . self::$db . ';charset=' . self::$charset;
                break;
            case 'pgsql':
                $dsn_opt['dsn'] .= 'host=' . self::$host . ';port=' . self::$port . ';dbname=' . self::$db . ';user=' . self::$user . ';password=' . self::$pwd;
                break;
            case 'oci':
                $dsn_opt['dsn'] .= 'dbname=//' . self::$host . ':' . self::$port . '/' . self::$db . ';charset=' . self::$charset;
                break;
            default:
                //Report type NOT support
                throw new \PDOException('PDO: ' . self::$type . ' NOT support!', E_USER_ERROR);
        }

        return $dsn_opt;
    }
}