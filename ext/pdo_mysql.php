<?php

/**
 * MySQL Execution for PDO Extension
 *
 * Copyright 2018 空城 <694623056@qq.com>
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

class pdo_mysql extends pdo
{
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
     * @throws \Exception
     */
    public static function insert(string $table, array $data, string &$last = ''): bool
    {
        //No data to insert
        if (empty($data)) return false;

        //Build "data"
        $column = self::build_data($data);

        //Prepare & execute
        $sql = 'INSERT INTO ' . self::escape($table) . ' (' . implode(', ', array_keys($column)) . ') VALUES(' . implode(', ', $column) . ')';
        $stmt = self::connect()->prepare($sql);

        try {
            $result = $stmt->execute($data);
        } catch (\Throwable $throwable) {
            debug('MySQL', $throwable->getMessage() . '. SQL Dump: ' . $stmt->queryString);
            return false;
        }

        $last = '' === $last ? (string)self::connect()->lastInsertId() : (string)self::connect()->lastInsertId($last);

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
     * @throws \Exception
     */
    public static function update(string $table, array $data, array $where): bool
    {
        //No data to update
        if (empty($data)) return false;

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
        $stmt = self::connect()->prepare($sql);

        try {
            $result = $stmt->execute($data);
        } catch (\Throwable $throwable) {
            debug('MySQL', $throwable->getMessage() . '. SQL Dump: ' . $stmt->queryString);
            return false;
        }

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
     *         'limit' => [0, 20] (from 0, read 20 rows)
     *                    OR: [10, 20] (from 10, read 20 rows)
     *                    OR: [20] (equals to [0, 20])
     *                    OR: 1 (equals to [0, 1])
     *                    OR: 20 (equals to [0, 20])
     *                    OR: '1' (equals to [0, 1])
     *                    OR: '10, 20' (equals to [10, 20])
     *     ],
     *     false (default, read all) / true (read column)
     * )
     *
     * @param string $table
     * @param array  $option
     * @param bool   $column
     *
     * @return array
     * @throws \Exception
     */
    public static function select(string $table, array $option = [], bool $column = false): array
    {
        //Build options & get data bind
        $data = self::build_opt($option);

        //Prepare & execute
        $sql = 'SELECT ' . $data['field'] . ' FROM ' . self::escape($table) . ' ' . implode(' ', $option);
        $stmt = self::connect()->prepare($sql);

        try {
            $stmt->execute($data['bind']);
        } catch (\Throwable $throwable) {
            debug('MySQL', $throwable->getMessage() . '. SQL Dump: ' . $stmt->queryString);
            return [];
        }

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
     * @return int
     * @throws \Exception
     */
    public static function delete(string $table, array $where): int
    {
        //Delete not allowed
        if (empty($where)) return 0;

        //Build "where"
        $where_opt = self::build_where($where);

        //Prepare & execute
        $sql = 'DELETE FROM ' . self::escape($table) . ' ' . implode(' ', $where_opt);
        $stmt = self::connect()->prepare($sql);

        try {
            $stmt->execute($where);
        } catch (\Throwable $throwable) {
            debug('MySQL', $throwable->getMessage() . '. SQL Dump: ' . $stmt->queryString);
            return 0;
        }

        $result = $stmt->rowCount();

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
     * @throws \Exception
     */
    public static function execute(string $sql, array $data = []): bool
    {
        //Prepare & execute
        $stmt = self::connect()->prepare($sql);

        try {
            $result = $stmt->execute($data);
        } catch (\Throwable $throwable) {
            debug('MySQL', $throwable->getMessage() . '. SQL Dump: ' . $stmt->queryString);
            return false;
        }

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
     * @throws \Exception
     */
    public static function query(string $sql, array $data = [], bool $column = false): array
    {
        //Prepare & execute
        $stmt = self::connect()->prepare($sql);

        try {
            $stmt->execute($data);
        } catch (\Throwable $throwable) {
            debug('MySQL', $throwable->getMessage() . '. SQL Dump: ' . $stmt->queryString);
            return [];
        }

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
     * @throws \Exception
     */
    public static function exec(string $sql): int
    {
        //Execute directly
        $exec = self::connect()->exec($sql);
        if (false === $exec) $exec = -1;

        unset($sql);
        return $exec;
    }

    /**
     * Begin transaction
     *
     * @return bool
     * @throws \Exception
     */
    public static function begin(): bool
    {
        return self::connect()->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     * @throws \Exception
     */
    public static function commit(): bool
    {
        return self::connect()->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     * @throws \Exception
     */
    public static function rollback(): bool
    {
        return self::connect()->rollBack();
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
                $bind = ':d_' . hash('crc32b', $key);
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
            if (!in_array($operator, ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'NOT EXISTS', 'IS NULL', 'IS NOT NULL'], true)) {
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
                    $bind = ':w_' . hash('crc32b', $item[0]) . '_' . $name . '_' . mt_rand();
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
                    $bind = ':w_' . hash('crc32b', $item[0]) . '_' . mt_rand();
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
                        if (!in_array(strtoupper($item[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'NOT EXISTS'], true)) {
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