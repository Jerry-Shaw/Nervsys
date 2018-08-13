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

use core\handler\factory;

class pdo extends factory
{
    //PDO arguments
    private $type    = 'mysql';
    private $host    = '127.0.0.1';
    private $port    = 3306;
    private $user    = 'root';
    private $pwd     = '';
    private $db      = '';
    private $timeout = 10;
    private $persist = true;
    private $charset = 'utf8mb4';

    /**
     * Set type
     *
     * @param string $type
     *
     * @return object
     */
    public function type(string $type): object
    {
        $this->type = &$type;

        unset($type);
        return $this;
    }

    /**
     * Set host
     *
     * @param string $host
     *
     * @return object
     */
    public function host(string $host): object
    {
        $this->host = &$host;

        unset($host);
        return $this;
    }

    /**
     * Set port
     *
     * @param int $port
     *
     * @return object
     */
    public function port(int $port): object
    {
        $this->port = &$port;

        unset($port);
        return $this;
    }

    /**
     * Set username
     *
     * @param string $user
     *
     * @return object
     */
    public function user(string $user): object
    {
        $this->user = &$user;

        unset($user);
        return $this;
    }

    /**
     * Set password
     *
     * @param string $pwd
     *
     * @return object
     */
    public function pwd(string $pwd): object
    {
        $this->pwd = &$pwd;

        unset($pwd);
        return $this;
    }

    /**
     * Set db name
     *
     * @param string $db
     *
     * @return object
     */
    public function db(string $db): object
    {
        $this->db = &$db;

        unset($db);
        return $this;
    }

    /**
     * Set read timeout
     *
     * @param int $timeout
     *
     * @return object
     */
    public function timeout(int $timeout): object
    {
        $this->timeout = &$timeout;

        unset($timeout);
        return $this;
    }

    /**
     * Set persist type
     *
     * @param bool $persist
     *
     * @return object
     */
    public function persist(bool $persist): object
    {
        $this->persist = &$persist;

        unset($persist);
        return $this;
    }

    /**
     * Set initial charset
     *
     * @param string $charset
     *
     * @return object
     */
    public function charset(string $charset): object
    {
        $this->charset = &$charset;

        unset($charset);
        return $this;
    }

    /**
     * PDO connector
     *
     * @return \PDO
     */
    public function connect(): \PDO
    {
        //Build DSN & OPTION
        $param = $this->build_dsn_opt();

        //Factory use PDO instance
        $pdo = parent::use('PDO', [$param['dsn'], $this->user, $this->pwd, $param['opt']]);

        unset($param);
        return $pdo;
    }

    /**
     * Build DSN & OPTION
     *
     * @return array
     */
    private function build_dsn_opt(): array
    {
        $dsn_opt = [
            'dsn' => $this->type . ':',
            'opt' => [
                \PDO::ATTR_CASE               => \PDO::CASE_NATURAL,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_PERSISTENT         => $this->persist,
                \PDO::ATTR_ORACLE_NULLS       => \PDO::NULL_NATURAL,
                \PDO::ATTR_EMULATE_PREPARES   => false,
                \PDO::ATTR_STRINGIFY_FETCHES  => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
            ]
        ];

        switch ($this->type) {
            case 'mysql':
                $dsn_opt['dsn'] .= 'host=' . $this->host
                    . ';port=' . $this->port
                    . ';dbname=' . $this->db
                    . ';charset=' . $this->charset;

                $dsn_opt['opt'][\PDO::ATTR_TIMEOUT]                  = $this->timeout;
                $dsn_opt['opt'][\PDO::MYSQL_ATTR_INIT_COMMAND]       = 'SET NAMES ' . $this->charset;
                $dsn_opt['opt'][\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                break;
            case 'mssql':
                $dsn_opt['dsn'] .= 'host=' . $this->host
                    . ',' . $this->port
                    . ';dbname=' . $this->db
                    . ';charset=' . $this->charset;
                break;
            case 'pgsql':
                $dsn_opt['dsn'] .= 'host=' . $this->host
                    . ';port=' . $this->port
                    . ';dbname=' . $this->db
                    . ';user=' . $this->user
                    . ';password=' . $this->pwd;
                break;
            case 'oci':
                $dsn_opt['dsn'] .= 'dbname=//' . $this->host
                    . ':' . $this->port
                    . '/' . $this->db
                    . ';charset=' . $this->charset;
                break;
            default:
                throw new \PDOException('Unsupported type: ' . $this->type, E_USER_ERROR);
        }

        return $dsn_opt;
    }
}