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

    /** @var \PDO $instance */
    protected $instance = null;

    //Connection params
    private $dsn = '';
    private $opt = [];

    /**
     * PDO connector
     *
     * @return $this
     */
    public function connect(): object
    {
        if (!is_object($this->instance)) {
            //Build DSN & OPTION
            $this->build_param();

            //Obtain PDO instance from factory
            $this->instance = parent::obtain(\PDO::class, [$this->dsn, $this->user, $this->pwd, $this->opt]);
        }

        return $this;
    }

    /**
     * Get \PDO instance
     *
     * @return \PDO
     */
    public function get_pdo(): \PDO
    {
        if (!is_object($this->instance)) {
            $this->connect();
        }

        return $this->instance;
    }

    /**
     * Build connection params for PDO
     */
    private function build_param(): void
    {
        //DSN
        $this->dsn = $this->type . ':';

        //Option
        $this->opt = [
            \PDO::ATTR_CASE               => \PDO::CASE_NATURAL,
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_PERSISTENT         => $this->persist,
            \PDO::ATTR_ORACLE_NULLS       => \PDO::NULL_NATURAL,
            \PDO::ATTR_EMULATE_PREPARES   => false,
            \PDO::ATTR_STRINGIFY_FETCHES  => false,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
        ];

        //Specific settings
        switch ($this->type) {
            case 'mysql':
                $this->dsn .= 'host=' . $this->host
                    . ';port=' . $this->port
                    . ';dbname=' . $this->db
                    . ';charset=' . $this->charset;

                $this->opt[\PDO::ATTR_TIMEOUT]                  = $this->timeout;
                $this->opt[\PDO::MYSQL_ATTR_INIT_COMMAND]       = 'SET NAMES ' . $this->charset;
                $this->opt[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                break;

            case 'mssql':
                $this->dsn .= 'host=' . $this->host
                    . ',' . $this->port
                    . ';dbname=' . $this->db
                    . ';charset=' . $this->charset;
                break;

            case 'pgsql':
                $this->dsn .= 'host=' . $this->host
                    . ';port=' . $this->port
                    . ';dbname=' . $this->db
                    . ';user=' . $this->user
                    . ';password=' . $this->pwd;
                break;

            case 'oci':
                $this->dsn .= 'dbname=//' . $this->host
                    . ':' . $this->port
                    . '/' . $this->db
                    . ';charset=' . $this->charset;
                break;

            default:
                throw new \PDOException('Unsupported type: ' . $this->type, E_USER_ERROR);
        }
    }
}