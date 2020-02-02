<?php

/**
 * PDO Connector Extension
 *
 * Copyright 2016-2019 秋水之冰 <27206617@qq.com>
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

/**
 * Class pdo
 *
 * @package ext
 */
class pdo extends factory
{
    //PDO username
    protected $usr = '';

    //PDO password
    protected $pwd = '';

    //Connection DSN
    protected $dsn = '';

    //Connection OPTION
    protected $opt = [];

    /**
     * pdo constructor.
     *
     * @param string $type
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pwd
     * @param string $db
     * @param int    $timeout
     * @param bool   $persist
     * @param string $charset
     */
    public function __construct(
        string $type = 'mysql',
        string $host = '127.0.0.1',
        int $port = 3306,
        string $user = 'root',
        string $pwd = '',
        string $db = '',
        int $timeout = 10,
        bool $persist = true,
        string $charset = 'utf8mb4'
    )
    {
        //Copy username & password
        $this->usr = &$user;
        $this->pwd = &$pwd;

        //Build DSN & OPTION
        $this->build_dsn($type, $host, $port, $user, $pwd, $db, $timeout, $persist, $charset);

        //Free memory
        unset($type, $host, $port, $user, $pwd, $db, $timeout, $persist, $charset);
    }

    /**
     * Connect PDO
     *
     * @return \PDO
     */
    public function connect(): \PDO
    {
        return \core\lib\stc\factory::build(\PDO::class, [$this->dsn, $this->usr, $this->pwd, $this->opt]);
    }

    /**
     * Build DSN OPT for PDO
     *
     * @param string $type
     * @param string $host
     * @param int    $port
     * @param string $user
     * @param string $pwd
     * @param string $db
     * @param int    $timeout
     * @param bool   $persist
     * @param string $charset
     */
    private function build_dsn(
        string $type,
        string $host,
        int $port,
        string $user,
        string $pwd,
        string $db,
        int $timeout,
        bool $persist,
        string $charset
    ): void
    {
        $this->dsn = $type . ':';

        $this->opt = [
            \PDO::ATTR_CASE               => \PDO::CASE_NATURAL,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_PERSISTENT         => $persist,
            \PDO::ATTR_ORACLE_NULLS       => \PDO::NULL_NATURAL,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ];

        switch ($type) {
            case 'mysql':
                $this->dsn .= 'host=' . $host
                    . ';port=' . $port
                    . ';dbname=' . $db
                    . ';charset=' . $charset;

                $this->opt[\PDO::ATTR_TIMEOUT]                  = $timeout;
                $this->opt[\PDO::MYSQL_ATTR_INIT_COMMAND]       = 'SET NAMES ' . $charset;
                $this->opt[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                break;

            case 'mssql':
                $this->dsn .= 'host=' . $host
                    . ',' . $port
                    . ';dbname=' . $db
                    . ';charset=' . $charset;
                break;

            case 'pgsql':
                $this->dsn .= 'host=' . $host
                    . ';port=' . $port
                    . ';dbname=' . $db
                    . ';user=' . $user
                    . ';password=' . $pwd;
                break;

            case 'oci':
                $this->dsn .= 'dbname=//' . $host
                    . ':' . $port
                    . '/' . $db
                    . ';charset=' . $charset;
                break;

            default:
                throw new \PDOException('Unsupported type: ' . $type, E_USER_ERROR);
        }

        unset($type, $host, $port, $user, $pwd, $db, $timeout, $persist, $charset);
    }
}