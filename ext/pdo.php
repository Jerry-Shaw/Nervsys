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

use core\handler\factory;

class pdo extends factory
{
    //PDO arguments
    protected $type    = 'mysql';
    protected $host    = '127.0.0.1';
    protected $port    = 3306;
    protected $user    = 'root';
    protected $pwd     = '';
    protected $db      = '';
    protected $timeout = 10;
    protected $persist = true;
    protected $charset = 'utf8mb4';

    /**
     * PDO connector
     *
     * @return \PDO
     */
    public function connect(): object
    {
        //Build DSN & OPTION
        $param = $this->build_dsn_opt();

        //Obtain PDO instance from factory
        $pdo = parent::obtain('PDO', [$param['dsn'], $this->user, $this->pwd, $param['opt']]);

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