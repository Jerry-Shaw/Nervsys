<?php

/**
 * SQL Execution for PDO Extension
 *
 * Author 空城 <694623056@qq.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 空城
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

class pdo_mysql extends pdo
{
    /**
     * Extension config
     * Config is an array composed of the following elements:
     *
     * 'init'    => false       //bool: PDO re-connect option
     * 'type'    => 'mysql'     //string: PDO DSN prefix (database type)
     * 'host'    => '127.0.0.1' //string: Database host address
     * 'port'    => 3306        //int: Database host port
     * 'user'    => 'root'      //string: Database username
     * 'pwd'     => ''          //string: Database password
     * 'db_name' => ''          //string: Database name
     * 'charset' => 'utf8mb4'   //string: Database charset
     * 'persist' => true        //string: Persistent connection option
     *
     * Config will be removed once used
     * Do add 'init' => true to re-connect
     *
     * @var array
     */
    public static $config = [
        'init'    => false,
        'type'    => 'mysql',
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'user'    => 'root',
        'pwd'     => '',
        'db_name' => '',
        'charset' => 'utf8mb4',
        'persist' => true
    ];

    //MySQL instance resource
    private static $db_mysql = null;

    /**
     * Extension Initialization
     */
    private static function init(): void
    {
        //No reconnection
        if ((!isset(self::$config['init']) || false === (bool)self::$config['init']) && is_object(self::$db_mysql)) return;

        //Read new config
        $cfg = ['type', 'host', 'port', 'user', 'pwd', 'db_name', 'charset', 'persist'];

        if (!empty(self::$config)) {
            //Set config for PDO
            foreach ($cfg as $key) if (isset(self::$config[$key])) self::$$key = self::$config[$key];
            //Remove config
            self::$config = [];
        }

        //Connect MySQL
        self::$db_mysql = self::connect();

        //Free memory
        unset($cfg, $key);
    }

    /**
     * Insert data
     *
     * @param string $table
     * @param array  $data
     * @param string $last
     *
     * @return bool
     */
    public static function insert(string $table, array $data, string &$last = 'id'): bool
    {
        //No data to insert
        if (empty($data)) {
            debug('No data to insert!');
            return false;
        }

        //Build "data"
        $column = self::build_data($data);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'INSERT INTO ' . self::escape($table) . ' (' . implode(', ', $column) . ') VALUES(' . implode(', ', array_keys($column)) . ')';
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($data);

        $last = '' === $last ? (string)self::$db_mysql->lastInsertId() : (string)self::$db_mysql->lastInsertId($last);

        unset($table, $data, $column, $sql, $stmt);
        return $result;
    }

    /**
     * Update data
     *
     * @param string $table
     * @param array  $data
     * @param array  $where
     *
     * @return bool
     */
    public static function update(string $table, array $data, array $where): bool
    {
        //No data to update
        if (empty($data)) {
            debug('No data to update!');
            return false;
        }

        //Build "data"
        $data_column = self::build_data($data);

        //Get "SET"
        $set_opt = [];
        foreach ($data_column as $key => $item) $set_opt[] = $item . ' = ' . $key;
        unset($data_column, $key, $item);

        //Build "where"
        $where_opt = self::build_where($where);

        //Merge data
        $data = array_merge($data, $where);
        unset($where);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'UPDATE ' . self::escape($table) . ' SET ' . implode(', ', $set_opt) . ' ' . implode(' ', $where_opt);
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($data);

        unset($table, $data, $set_opt, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Select data
     *
     * @param string $table
     * @param array  $option
     * @param bool   $column
     *
     * @return array
     */
    public static function select(string $table, array $option = [], bool $column = false): array
    {
        //Build options & get data bind
        $data = self::build_opt($option);

        //Initialize
        self::init();

        //Prepare & execute
        $sql = 'SELECT ' . $data['field'] . ' FROM ' . self::escape($table) . ' ' . implode(' ', $option);
        $stmt = self::$db_mysql->prepare($sql);
        $stmt->execute($data['bind']);

        $result = $stmt->fetchAll(!$column ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($table, $option, $column, $opt, $data, $field, $sql, $stmt);
        return $result;
    }

    /**
     * Delete data
     *
     * @param string $table
     * @param array  $where
     *
     * @return bool
     */
    public static function delete(string $table, array $where): bool
    {
        //Delete not allowed
        if (empty($where)) {
            debug('Delete is not allowed!');
            return false;
        }

        //Build "where"
        $where_opt = self::build_where($where);

        //Prepare & execute SQL
        self::init();

        $sql = 'DELETE FROM ' . self::escape($table) . ' ' . implode(' ', $where_opt);
        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($where);

        unset($table, $where, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Query SQL & fetch data
     *
     * @param string $sql
     * @param array  $data
     * @param bool   $column
     *
     * @return array
     */
    public static function query(string $sql, array $data = [], bool $column = false): array
    {
        self::init();

        $stmt = self::$db_mysql->prepare($sql);
        $stmt->execute($data);

        $result = $stmt->fetchAll(!$column ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($sql, $data, $column, $stmt);
        return $result;
    }

    /**
     * Execute SQL & fetch result
     *
     * @param string $sql
     * @param array  $data
     *
     * @return bool
     */
    public static function exec(string $sql, array $data = []): bool
    {
        self::init();

        $stmt = self::$db_mysql->prepare($sql);
        $result = $stmt->execute($data);

        unset($sql, $data, $stmt);
        return $result;
    }

    /**
     * Build "data"
     *
     * @param array $data
     *
     * @return array
     */
    private static function build_data(array &$data): array
    {
        //Columns
        $column = [];

        //Process data
        foreach ($data as $key => $value) {
            //Generate bind value
            $bind = ':d_' . $key;

            //Add to columns
            $column[$bind] = self::escape($key);

            //Renew data
            unset($data[$key]);
            $data[$bind] = $value;
        }

        unset($key, $value, $bind);
        return $column;
    }

    /**
     * Build "where"
     *
     * @param array $where
     *
     * @return array
     */
    private static function build_where(array &$where): array
    {
        //Options
        $option = ['WHERE'];

        foreach ($where as $key => $item) {
            unset($where[$key]);
            $count = count($item);

            //Add missing elements
            if (2 === $count) {
                $item[2] = $item[1];
                $item[1] = '=';
                if (0 < $key) $item[3] = 'AND';
            } elseif (3 === $count) {
                if (!in_array(strtoupper($item[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'IN', 'NOT IN'], true)) {
                    $item[3] = $item[2];
                    $item[2] = $item[1];
                    $item[1] = '=';
                } elseif (0 < $key) $item[3] = 'AND';
            }

            //Process data
            if (isset($item[3])) {
                $item[3] = strtoupper($item[3]);
                $option[] = in_array($item[3], ['AND', 'OR', 'NOT'], true) ? $item[3] : 'AND';
            }

            $option[] = self::escape($item[0]);

            //Bind data
            if (false === stripos($item[1], 'IN')) {
                $option[] = $item[1];
                //Generate bind value
                $bind = ':w_' . $item[0] . '_' . mt_rand();
                //Add to option
                $option[] = $bind;
                //Add to "where"
                $where[$bind] = $item[2];
            } else {
                $option[] = strtoupper($item[1]);
                $option[] = '(';

                if (is_array($item[2])) {
                    $opt = [];
                    foreach ($item[2] as $name => $value) {
                        //Generate bind value
                        $bind = ':w_' . $item[0] . '_' . $name . '_' . mt_rand();
                        //Add to option
                        $opt[] = $bind;
                        //Add to "where"
                        $where[$bind] = $value;
                    }
                    $option[] = implode(', ', $opt);
                    unset($opt, $name, $value);
                } else {
                    //Generate bind value
                    $bind = ':w_' . $item[0] . '_' . mt_rand();
                    //Add to option
                    $option[] = $bind;
                    //Add to "where"
                    $where[$bind] = $item[2];
                }

                $option[] = ')';
            }
        }

        unset($key, $item, $count, $bind);
        return $option;
    }

    /**
     * Build options
     * Do NOT mess function orders
     *
     * @param array $opt
     *
     * @return array
     */
    private static function build_opt(array &$opt): array
    {
        $data = [];
        $data['bind'] = [];

        //Process "field"
        $data['field'] = self::opt_field($opt);
        //Process "join"
        self::opt_join($opt);
        //Process "where"
        $data['bind'] = self::opt_where($opt);
        //Process "order"
        self::opt_order($opt);
        //Process "group"
        self::opt_group($opt);
        //Process "limit"
        $data['bind'] += self::opt_limit($opt);

        return $data;
    }

    /**
     * Get opt "field"
     *
     * @param array $opt
     *
     * @return string
     */
    private static function opt_field(array &$opt): string
    {
        //Default field value
        $field = '*';

        if (!isset($opt['field'])) return $field;
        $opt_data = $opt['field'];
        unset($opt['field']);

        if (is_array($opt_data) && !empty($opt_data)) {
            $column = [];
            foreach ($opt_data as $value) $column[] = self::escape($value);
            if (!empty($column)) $field = implode(', ', $column);
            unset($column, $value);
        } elseif (is_string($opt_data) && '' !== $opt_data) $field = &$opt_data;

        unset($opt_data);
        return $field;
    }

    /**
     * Get opt "join"
     *
     * @param array $opt
     */
    private static function opt_join(array &$opt): void
    {
        if (!isset($opt['join'])) return;
        $opt_data = $opt['join'];
        unset($opt['join']);

        if (is_array($opt_data) && !empty($opt_data)) {
            $join = [];
            foreach ($opt_data as $table => $value) {
                $value[3] = !isset($value[3]) ? 'INNER' : strtoupper($value[3]);
                if (!in_array($value[3], ['INNER', 'LEFT', 'RIGHT'], true)) $value[3] = 'INNER';
                if (2 === count($value)) {
                    $value[2] = $value[1];
                    $value[1] = '=';
                }

                $join[] = $value[3] . ' JOIN ' . self::escape($table) . ' ON ' . $value[0] . ' ' . $value[1] . ' ' . $value[2];
            }

            if (!empty($join)) $opt['join'] = implode(' ', $join);

            unset($join, $table, $value);
        } elseif (is_string($opt_data) && '' !== $opt_data) $opt['join'] = false !== stripos($opt_data, 'JOIN') ? $opt_data : 'INNER JOIN ' . $opt_data;

        unset($opt_data);
    }

    /**
     * Get opt "where"
     *
     * @param array $opt
     *
     * @return array
     */
    private static function opt_where(array &$opt): array
    {
        if (!isset($opt['where'])) return [];
        $opt_data = $opt['where'];
        unset($opt['where']);

        if (is_array($opt_data) && !empty($opt_data)) $opt['where'] = implode(' ', self::build_where($opt_data));
        elseif (is_string($opt_data) && '' !== $opt_data) {
            $opt['where'] = 'WHERE ' . $opt_data;
            $opt_data = [];
        }

        return $opt_data;
    }

    /**
     * Get opt "order"
     *
     * @param array $opt
     */
    private static function opt_order(array &$opt): void
    {
        if (!isset($opt['order'])) return;
        $opt_data = $opt['order'];
        unset($opt['order']);

        if (is_array($opt_data) && !empty($opt_data)) {
            $column = [];

            foreach ($opt_data as $key => $value) {
                $value = strtoupper($value);
                if (!in_array($value, ['DESC', 'ASC'], true)) $value = 'DESC';

                $column[] = self::escape($key) . ' ' . $value;
            }

            if (!empty($column)) $opt['order'] = 'ORDER BY ' . implode(', ', $column);
            unset($column, $key, $value);
        } elseif (is_string($opt_data) && '' !== $opt_data) $opt['order'] = 'ORDER BY ' . $opt_data;

        unset($opt_data);
    }

    /**
     * Get opt "group"
     *
     * @param array $opt
     */
    private static function opt_group(array &$opt): void
    {
        if (!isset($opt['group'])) return;
        $opt_data = $opt['group'];
        unset($opt['group']);

        if (is_array($opt_data) && !empty($opt_data)) {
            $column = [];

            foreach ($opt_data as $key) $column[] = self::escape($key);
            if (!empty($column)) $opt['group'] = 'GROUP BY ' . implode(', ', $column);

            unset($column, $key);
        } elseif (is_string($opt_data) && '' !== $opt_data) $opt['group'] = 'GROUP BY ' . $opt_data;

        unset($opt_data);
    }

    /**
     * Get opt "limit"
     *
     * @param array $opt
     *
     * @return array
     */
    private static function opt_limit(array &$opt): array
    {
        if (!isset($opt['limit'])) return [];
        $opt_data = $opt['limit'];
        unset($opt['limit']);

        $data = [];

        if (is_array($opt_data) && !empty($opt_data)) {
            if (1 === count($opt_data)) {
                $opt_data[1] = $opt_data[0];
                $opt_data[0] = 0;
            }

            $opt['limit'] = 'LIMIT :l_start, :l_offset';
            $data = [':l_start' => (int)$opt_data[0], ':l_offset' => (int)$opt_data[1]];
        } elseif (is_numeric($opt_data)) {
            $opt['limit'] = 'LIMIT :l_start, :l_offset';
            $data = [':l_start' => 0, ':l_offset' => (int)$opt_data];
        } elseif (is_string($opt_data) && '' !== $opt_data) $opt['limit'] = 'LIMIT ' . $opt_data;

        unset($opt_data);
        return $data;
    }

    /**
     * Escape column key
     *
     * @param string $value
     *
     * @return string
     */
    private static function escape(string $value): string
    {
        return false !== strpos($value, '.') ? '`' . trim($value, " `\t\n\r\0\x0B") . '`' : trim($value);
    }
}