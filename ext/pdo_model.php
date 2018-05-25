<?php

/**
 * DB Model Execution for PDO Extension
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

class pdo_model extends pdo
{
    //Model Pool
    private static $pool = [];

    /**
     * Get table name
     *
     * @param string $class
     *
     * @return string
     * @throws \Exception
     */
    private static function get_table(string $class): string
    {
        //Read "db" setting
        if (isset($class::$db)) parent::connect($class::$db);

        //Read "table" setting
        if (isset(self::$pool[$class])) return self::$pool[$class];
        if (isset($class::$table)) return self::$pool[$class] = $class::$table;

        //Set table based on class name
        return self::$pool[$class] = strstr($class, '\\', true) . str_replace('\\', '_', strrchr($class, '\\'));;
    }

    /**
     * Insert data
     *
     * Usage:
     *
     * insert(
     *     [
     *         'col_a' => 'A',
     *         'col_b' => 'B,
     *         ...
     *     ],
     *     'myID'
     * )
     *
     * @param array  $data
     * @param string $last
     *
     * @return bool
     * @throws \Exception
     */
    public static function insert(array $data, string &$last = ''): bool
    {
        return pdo_mysql::insert(self::get_table(get_called_class()), $data, $last);
    }

    /**
     * Update data
     *
     * Usage:
     *
     * update(
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
     * @param array $data
     * @param array $where
     *
     * @return bool
     * @throws \Exception
     */
    public static function update(array $data, array $where): bool
    {
        return pdo_mysql::update(self::get_table(get_called_class()), $data, $where);
    }

    /**
     * Select data
     *
     * Usage:
     *
     * select(
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
     * @param array $option
     * @param bool  $column
     *
     * @return array
     * @throws \Exception
     */
    public static function select(array $option = [], bool $column = false): array
    {
        return pdo_mysql::select(self::get_table(get_called_class()), $option, $column);
    }

    /**
     * Delete data
     *
     * Usage:
     *
     * delete(
     *     [
     *         ['col_c', 'a'],
     *         ['col_d', '>', 'd'],
     *         ['col_e', '!=', 'e', 'OR'],
     *         ...
     *     ]
     * )
     *
     * @param array $where
     *
     * @return int
     * @throws \Exception
     */
    public static function delete(array $where): int
    {
        return pdo_mysql::delete(self::get_table(get_called_class()), $where);
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
        return pdo_mysql::execute($sql, $data);
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
        return pdo_mysql::query($sql, $data, $column);
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
        return pdo_mysql::exec($sql);
    }
}