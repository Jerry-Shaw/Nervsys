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
     * 'type'    => 'mysql'     //string: PDO DSN prefix (database type)
     * 'host'    => '127.0.0.1' //string: Database host address
     * 'port'    => 3306        //int: Database host port
     * 'user'    => 'root'      //string: Database username
     * 'pwd'     => ''          //string: Database password
     * 'db_name' => ''          //string: Database name
     * 'charset' => 'utf8mb4'   //string: Database charset
     * 'timeout' => 10          //int: Connection timeout (in seconds)
     * 'persist' => true        //string: Persistent connection option
     *
     * Config will be emptied once used
     *
     * @var array
     */
    public static $config = [
        'type'    => 'mysql',
        'host'    => '127.0.0.1',
        'port'    => 3306,
        'user'    => 'root',
        'pwd'     => '',
        'db_name' => '',
        'charset' => 'utf8mb4',
        'timeout' => 10,
        'persist' => true
    ];

    //MySQL instance resource
    private static $mysql = null;

    /**
     * Extension Initialization
     */
    private static function init(): void
    {
        //No re-connection
        if (empty(self::$config) && is_object(self::$mysql)) return;

        //Read new config
        $cfg = ['type', 'host', 'port', 'user', 'pwd', 'db_name', 'charset', 'timeout', 'persist'];

        if (!empty(self::$config)) {
            //Set config for PDO
            foreach ($cfg as $key) if (isset(self::$config[$key])) self::$$key = self::$config[$key];
            //Empty config
            self::$config = [];
        }

        //Connect MySQL
        self::$mysql = self::connect();

        //Free memory
        unset($cfg, $key);
    }

    /**
     * Insert data
     *
     * Usage:
     *
     * insert(
     *     'myTable',
     *     [
     *         'col_a' => 'A',
     *         'col_b' => 'B,
     *         ...
     *     ],
     *     'myID'
     * )
     *
     * @param string $table
     * @param array  $data
     * @param string $last
     *
     * @return bool
     */
    public static function insert(string $table, array $data, string &$last = ''): bool
    {
        //No data to insert
        if (empty($data)) {
            debug(__CLASS__, 'No data to insert!');
            return false;
        }

        //Initialize
        self::init();

        //Build "data"
        $column = self::build_data($data);

        //Prepare & execute
        $sql = 'INSERT INTO ' . self::escape($table) . ' (' . implode(', ', array_keys($column)) . ') VALUES(' . implode(', ', $column) . ')';
        $stmt = self::$mysql->prepare($sql);
        $result = $stmt->execute($data);

        $last = '' === $last ? (string)self::$mysql->lastInsertId() : (string)self::$mysql->lastInsertId($last);

        unset($table, $data, $column, $sql, $stmt);
        return $result;
    }

    /**
     * Update data
     *
     * Usage:
     *
     * update(
     *     'myTable',
     *     [
     *         'col_a' => 'A',
     *         'col_b' => 'B,
     *         ...
     *     ],
     *     [
     *         ['col_c', 'a'],
     *         ['col_d', '>', 'd'],
     *         ['col_e', '!=', 'e', 'OR'],
     *         ...
     *     ]
     * )
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
            debug(__CLASS__, 'No data to update!');
            return false;
        }

        //Initialize
        self::init();

        //Build "data"
        $data_column = self::build_data($data);

        //Get "SET"
        $set_opt = [];
        foreach ($data_column as $key => $item) $set_opt[] = $key . ' = ' . $item;
        unset($data_column, $key, $item);

        //Build "where"
        $where_opt = self::build_where($where);

        //Merge data
        $data = array_merge($data, $where);
        unset($where);

        //Prepare & execute
        $sql = 'UPDATE ' . self::escape($table) . ' SET ' . implode(', ', $set_opt) . ' ' . implode(' ', $where_opt);
        $stmt = self::$mysql->prepare($sql);
        $result = $stmt->execute($data);

        unset($table, $data, $set_opt, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Select data
     *
     * Usage:
     *
     * select(
     *     'myTable',
     *     [
     *         'field' => ['a', 'b', ...],
     *                    OR: 'a, b, c, ...',
     *         'join' =>  [
     *                        'TableB' => ['myTable.a', '=', 'TableB.b'],
     *                        'TableC' => ['myTable.a', '<>', 'TableC.b'],
     *                        ...
     *                    ],
     *                    OR: 'INNER JOIN TableB ON xxx (conditions)',
     *         'where' => [
     *                        ['col_c', 'a'],
     *                        ['col_d', '>', 'd'],
     *                        ['col_e', '!=', 'e', 'OR'],
     *                        ...
     *                    ],
     *                    OR: 'a = "a" AND b = "b" OR c != "c" ...',
     *         'order' => [
     *                        ['a', 'DESC'],
     *                        ['b', 'ASC'],
     *                        ...
     *                    ],
     *                    OR: 'a DESC, b ASC, ...',
     *         'group' => ['a', 'b', ...],
     *                    OR: 'a, b, ...',
     *                    OR: ['a'],
     *                    OR: 'a',
     *         'limit' => [1, 20]
     *                    OR: 1, 20
     *                    OR: 1
     *     ],
     *     false (default, read all) / true (read column)
     * )
     *
     * @param string $table
     * @param array  $option
     * @param bool   $column
     *
     * @return array
     */
    public static function select(string $table, array $option = [], bool $column = false): array
    {
        //Initialize
        self::init();

        //Build options & get data bind
        $data = self::build_opt($option);

        //Prepare & execute
        $sql = 'SELECT ' . $data['field'] . ' FROM ' . self::escape($table) . ' ' . implode(' ', $option);
        $stmt = self::$mysql->prepare($sql);
        $stmt->execute($data['bind']);

        $result = $stmt->fetchAll(!$column ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($table, $option, $column, $opt, $data, $field, $sql, $stmt);
        return $result;
    }

    /**
     * Delete data
     *
     * Usage:
     *
     * delete(
     *     'myTable',
     *     [
     *         ['col_c', 'a'],
     *         ['col_d', '>', 'd'],
     *         ['col_e', '!=', 'e', 'OR'],
     *         ...
     *     ]
     * )
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
            debug(__CLASS__, 'Delete NOT allowed!');
            return false;
        }

        //Initialize
        self::init();

        //Build "where"
        $where_opt = self::build_where($where);

        //Prepare & execute
        $sql = 'DELETE FROM ' . self::escape($table) . ' ' . implode(' ', $where_opt);
        $stmt = self::$mysql->prepare($sql);
        $result = $stmt->execute($where);

        unset($table, $where, $where_opt, $sql, $stmt);
        return $result;
    }

    /**
     * Execute SQL with data
     *
     * @param string $sql
     * @param array  $data
     *
     * @return bool
     */
    public static function execute(string $sql, array $data = []): bool
    {
        //Initialize
        self::init();

        //Prepare & execute
        $stmt = self::$mysql->prepare($sql);
        $result = $stmt->execute($data);

        unset($sql, $data, $stmt);
        return $result;
    }

    /**
     * Query SQL & fetch rows
     *
     * @param string $sql
     * @param array  $data
     * @param bool   $column
     *
     * @return array
     */
    public static function query(string $sql, array $data = [], bool $column = false): array
    {
        //Initialize
        self::init();

        //Prepare & execute
        $stmt = self::$mysql->prepare($sql);
        $stmt->execute($data);

        $result = $stmt->fetchAll(!$column ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($sql, $data, $column, $stmt);
        return $result;
    }

    /**
     * Exec SQL & count affected rows
     *
     * @param string $sql
     *
     * @return int
     */
    public static function exec(string $sql): int
    {
        //Initialize
        self::init();

        //Execute directly
        $exec = self::$mysql->exec($sql);
        if (false === $exec) $exec = -1;

        unset($sql);
        return $exec;
    }

    /**
     * Begin transaction
     *
     * @return bool
     */
    public static function begin(): bool
    {
        return self::$mysql->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return self::$mysql->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public static function rollback(): bool
    {
        return self::$mysql->rollBack();
    }

    /**
     * Value bind mode detector
     *
     * @param string $value
     *
     * @return bool
     */
    private static function use_bind(string $value): bool
    {
        return '(' !== substr($value, 0, 1) || ')' !== substr($value, -1, 1);
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
        //Column
        $column = [];

        //Process data
        foreach ($data as $key => $value) {
            //Delete structure
            unset($data[$key]);

            //Process value
            if (self::use_bind($value)) {
                //Generate bind value
                $bind = ':d_' . strtr($key, '.', '_');
                //Add to column
                $column[self::escape($key)] = $bind;
                //Add to data
                $data[$bind] = $value;
                //Free memory
                unset($bind);
            } else $column[self::escape($key)] = addslashes($value);
        }

        unset($key, $value);
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
            //Delete structure
            unset($where[$key]);

            //Ignore incomplete items
            if (2 > count($item)) continue;

            //Set "in_value" mode
            $in_value = false;

            //Try uppercase operator
            $operator = strtoupper($item[1]);

            //Add missing elements
            if (!in_array($operator, ['=', '<', '>', '<=', '>=', '<>', '!=', 'IN', 'NOT IN', 'NOT EXISTS', 'IS NULL', 'IS NOT NULL'], true)) {
                if (isset($item[2])) $item[3] = $item[2];
                $item[2] = $item[1];
                $item[1] = '=';
            } elseif (in_array($operator, ['IN', 'NOT IN', 'NOT EXISTS'], true)) {
                $item[1] = $operator;
                $in_value = true;
            } elseif (in_array($operator, ['IS NULL', 'IS NOT NULL'], true)) {
                $item[1] = $operator;
                if (isset($item[2])) {
                    $item[3] = $item[2];
                    unset($item[2]);
                }
            }

            //Add missing logic gate
            if (!isset($item[3]) && 0 < $key) $item[3] = 'AND';

            //Add logic gate to option
            if (isset($item[3])) {
                $gate = strtoupper($item[3]);
                $option[] = in_array($gate, ['AND', 'OR', 'NOT'], true) ? $gate : 'AND';
                unset($gate);
            }

            //Add column name to option
            $option[] = self::escape($item[0]);

            //Add operator to option
            $option[] = $item[1];

            //Continue when no value passed
            if (!isset($item[2])) continue;

            //"in_value" mode
            if ($in_value) $option[] = '(';

            //Process values
            if (is_array($item[2])) {
                //Reset bind list
                $list = [];

                foreach ($item[2] as $name => $value) {
                    //Generate bind value
                    $bind = ':w_' . strtr($item[0], '.', '_') . '_' . $name . '_' . mt_rand();
                    //Add to bind list
                    $list[] = $bind;
                    //Add to "where"
                    $where[$bind] = $value;
                }

                //Add to option
                $option[] = implode(', ', $list);

                //Free memory
                unset($list, $name, $value, $bind);
            } else {
                if (self::use_bind($item[2])) {
                    //Generate bind value
                    $bind = ':w_' . strtr($item[0], '.', '_') . '_' . mt_rand();
                    //Add to option
                    $option[] = $bind;
                    //Add to "where"
                    $where[$bind] = $item[2];
                    //Free memory
                    unset($bind);
                } else $option[] = addslashes($item[2]);
            }

            //"in_value" mode
            if ($in_value) $option[] = ')';
        }

        unset($key, $item, $in_value, $operator);
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
            foreach ($opt_data as $table => $item) {
                //Add missing elements
                switch (count($item)) {
                    case 2:
                        $item[2] = $item[1];
                        $item[3] = 'INNER';
                        $item[1] = '=';
                        break;
                    case 3:
                        if (!in_array(strtoupper($item[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'IN', 'NOT IN', 'NOT EXISTS'], true)) {
                            $item[3] = $item[2];
                            $item[2] = $item[1];
                            $item[1] = '=';
                        } else $item[3] = 'INNER';
                        break;
                }

                if (!in_array($item[3], ['INNER', 'LEFT', 'RIGHT'], true)) $item[3] = 'INNER';

                $join[] = $item[3] . ' JOIN ' . self::escape($table) . ' ON ' . $item[0] . ' ' . $item[1] . ' ' . $item[2];
            }

            if (!empty($join)) $opt['join'] = implode(' ', $join);

            unset($join, $table, $item);

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
        $pass = true;
        $char = ['(', ' ', '.', '*', ')'];

        foreach ($char as $key) {
            if (false === strpos($value, $key)) continue;
            $pass = false;
            break;
        }

        $value = $pass ? '`' . trim($value, " `\t\n\r\0\x0B") . '`' : trim($value);

        unset($pass, $char, $key);
        return $value;
    }
}