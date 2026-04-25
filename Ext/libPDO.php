<?php

/**
 * PDO connector Extension
 *
 * Copyright 2016-2026 秋水之冰 <27206617@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;

/**
 * Class libPDO
 *
 * @package Ext
 */
class libPDO extends Factory
{
    public \PDO|null $pdo;

    protected string $usr = '';
    protected string $pwd = '';
    protected string $dsn = '';
    protected array  $opt = [];

    /**
     * libPDO constructor.
     *
     * @param string $type    Database type: mysql, mssql, pgsql, oci, sqlite
     * @param string $host    mysql/mssql/pgsql/oci: hostname or IP
     *                        sqlite: database file path OR ':memory:' for in-memory database
     * @param int    $port    mysql/mssql/pgsql/oci: port number
     *                        sqlite: NOT USED
     * @param string $user    mysql/mssql/pgsql/oci: database username
     *                        sqlite: NOT USED
     * @param string $pwd     mysql/mssql/pgsql/oci: database password
     *                        sqlite: NOT USED
     * @param string $db      mysql/mssql/pgsql/oci: database name
     *                        sqlite: NOT USED
     * @param int    $timeout mysql/pgsql/oci: connection timeout in seconds
     *                        sqlite: busy timeout in seconds (converted to milliseconds internally)
     *                        mssql: NOT USED
     * @param bool   $persist All types: enable persistent connection
     * @param string $charset mysql/mssql/oci: character set
     *                        sqlite: encoding used in PRAGMA encoding
     *                        pgsql: NOT USED
     */
    public function __construct(
        string $type = 'mysql',
        string $host = '127.0.0.1',
        int    $port = 3306,
        string $user = 'root',
        string $pwd = '',
        string $db = '',
        int    $timeout = 10,
        bool   $persist = true,
        string $charset = 'utf8mb4'
    )
    {
        // Set pdo to null
        $this->pdo = null;

        // Copy username & password (for non-SQLite)
        $this->usr = $user;
        $this->pwd = $pwd;

        // Build DSN & OPTION
        $this->buildDsn($type, $host, $port, $user, $pwd, $db, $timeout, $persist, $charset);

        // Free memory
        unset($type, $host, $port, $user, $pwd, $db, $timeout, $persist, $charset);
    }

    /**
     * Connect PDO Driver
     *
     * @return $this
     * @throws \ReflectionException
     */
    public function connect(): static
    {
        // Destroy existed PDO object from factory
        if ($this->pdo instanceof \PDO) {
            $this->destroy($this->pdo);
        }

        // SQLite does not require username/password
        if (str_starts_with($this->dsn, 'sqlite:')) {
            $this->pdo = parent::getObj(\PDO::class, [$this->dsn, null, null, $this->opt]);
            // Enable foreign key constraints for SQLite
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $this->pdo = parent::getObj(\PDO::class, [$this->dsn, $this->usr, $this->pwd, $this->opt]);
        }

        return $this;
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
    private function buildDsn(
        string $type,
        string $host,
        int    $port,
        string $user,
        string $pwd,
        string $db,
        int    $timeout,
        bool   $persist,
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

                $this->opt[\PDO::ATTR_TIMEOUT] = $timeout;

                if (version_compare(PHP_VERSION, '8.4.0', '>=')) {
                    $this->opt[\PDO\Mysql::ATTR_INIT_COMMAND]       = 'SET NAMES ' . $charset;
                    $this->opt[\PDO\Mysql::ATTR_USE_BUFFERED_QUERY] = true;
                } else {
                    $this->opt[\PDO::MYSQL_ATTR_INIT_COMMAND]       = 'SET NAMES ' . $charset;
                    $this->opt[\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
                }
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

            case 'sqlite':
                // SQLite: host parameter is used as database file path
                // Supports ':memory:' for in-memory database or file path like '/path/to/database.db'
                if ('' === $host || '127.0.0.1' === $host) {
                    $host = ':memory:';
                }

                $this->dsn = 'sqlite:' . $host;
                // SQLite timeout is measured in milliseconds
                $this->opt[\PDO::ATTR_TIMEOUT] = $timeout * 1000;
                break;

            default:
                throw new \PDOException('Unsupported type: ' . $type, E_USER_ERROR);
        }

        unset($type, $host, $port, $user, $pwd, $db, $timeout, $persist, $charset);
    }
}