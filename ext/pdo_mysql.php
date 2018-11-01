<?php
/**
 * Pdo MySQL Extension
 *
 * Copyright 2018 kristenzz <kristenzz1314@gmail.com>
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
    //Action
    private $act = '';

    //SQL params
    private $field = '';
    private $table = '';
    private $where = '';

    private $join   = '';
    private $group  = '';
    private $having = '';

    private $order = '';
    private $limit = '';

    private $incr  = [];
    private $value = [];

    private $bind_value = [];
    private $bind_where = [];

    /**
     * Insert into table
     *
     * @param string $table
     *
     * @return $this
     */
    public function insert(string $table = ''): object
    {
        $this->act = 'INSERT';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Select from table
     *
     * @param string $table
     *
     * @return $this
     */
    public function select(string $table = ''): object
    {
        $this->act = 'SELECT';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Update table date
     *
     * @param string $table
     *
     * @return $this
     */
    public function update(string $table = ''): object
    {
        $this->act = 'UPDATE';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Delete from table
     *
     * @param string $table
     *
     * @return $this
     */
    public function delete(string $table = ''): object
    {
        $this->act = 'DELETE';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Set insert values
     *
     * @param array $values
     *
     * @return $this
     */
    public function value(array $values): object
    {
        foreach ($values as $key => $value) {
            if (!isset($this->value[$key])) {
                $this->value[$key]  = $value;
                $this->bind_value[] = $value;
            }
        }

        unset($values, $value);
        return $this;
    }

    /**
     * Set update increase values
     * Using negative number for decrement
     *
     * @param array $value
     * @param bool  $append
     *
     * @return $this
     */
    public function incr(array $value, bool $append = false): object
    {
        $col = key($value);
        $val = current($value);

        if (!is_numeric($val)) {
            throw new \PDOException('MySQL: Increase value: "' . $val . '" for column "' . $col . '" ERROR!', E_USER_ERROR);
        }

        $val = false === strpos($val, '.') ? (int)$val : (float)$val;

        !$append ? $this->incr = [$col => &$val] : $this->incr[$col] = &$val;

        unset($value, $append, $col, $val);
        return $this;
    }

    /**
     * Set select fields
     *
     * @param string ...$fields
     *
     * @return $this
     */
    public function field(string ...$fields): object
    {
        if ('' !== $this->field) {
            $this->field .= ',';
        }

        $this->field .= implode(', ', $fields);

        unset($fields);
        return $this;
    }

    /**
     * Set join conditions
     *
     * @param string $table
     * @param array  $where
     * @param string $type
     *
     * @return $this
     */
    public function join(string $table, array $where, string $type = 'INNER'): object
    {
        $this->join .= $this->build_join($table, $where, $type);

        unset($table, $where, $type);
        return $this;
    }

    /**
     * Set where conditions
     *
     * @param array $where
     *
     * @return $this
     */
    public function where(array $where): object
    {
        $this->where .= $this->build_where($where, __FUNCTION__);

        unset($where);
        return $this;
    }

    /**
     * Set having conditions
     *
     * @param array $having
     *
     * @return $this
     */
    public function having(array $having): object
    {
        $this->having .= $this->build_where($having, __FUNCTION__);

        unset($having);
        return $this;
    }

    /**
     * Set order
     *
     * @param string $field
     * @param string $order
     *
     * @return $this
     */
    public function order(string $field, string $order = 'ASC'): object
    {
        if (!in_array($item = strtoupper($order), ['ASC', 'DESC'], true)) {
            throw new \PDOException('MySQL: Order method: "' . $order . '" NOT supported!', E_USER_ERROR);
        }

        if ('' !== $this->order) {
            $this->order .= ', ';
        }

        $this->order .= $this->escape($field) . ' ' . $item;

        unset($field, $order, $item);
        return $this;
    }

    /**
     * Set group conditions
     *
     * @param string ...$group
     *
     * @return $this
     */
    public function group(string ...$group): object
    {
        $this->group = implode(', ', $group);

        unset($group);
        return $this;
    }

    /**
     * Set limit
     *
     * @param int $offset
     * @param int $length
     *
     * @return $this
     */
    public function limit(int $offset, int $length = 0): object
    {
        $this->limit = 0 === $length ? '0, ' . $offset : $offset . ', ' . $length;

        unset($offset, $length);
        return $this;
    }

    /**
     * Exec SQL and return affected rows
     *
     * @param string $sql
     *
     * @return int
     */
    public function exec(string $sql): int
    {
        try {
            if (false === $exec = parent::connect()->exec($sql)) {
                $exec = -1;
            }
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL Dump: ' . $sql . '. ' . PHP_EOL . 'Error Msg:' . $throwable->getMessage(), E_USER_ERROR);
        }

        unset($sql);
        return $exec;
    }

    /**
     * Query SQL and return fetched data
     *
     * @param string $sql
     * @param bool   $fetch_col
     * @param int    $col_no
     *
     * @return array
     */
    public function query(string $sql, bool $fetch_col = false, int $col_no = 0): array
    {
        try {
            $stmt = !$fetch_col
                ? parent::connect()->query($sql, \PDO::FETCH_ASSOC)
                : parent::connect()->query($sql, \PDO::FETCH_COLUMN, $col_no);
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL Dump: ' . $sql . '. ' . PHP_EOL . 'Error Msg:' . $throwable->getMessage(), E_USER_ERROR);
        }

        $data = $stmt->fetchAll(!$fetch_col ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($sql, $fetch_col, $col_no, $stmt);
        return $data;
    }

    /**
     * Execute prepared SQL and return fetched data
     *
     * @param bool $fetch_col
     *
     * @return array
     */
    public function fetch(bool $fetch_col = false): array
    {
        $stmt = parent::connect()->prepare($this->build_sql());

        try {
            $stmt->execute($this->bind_value);
            $this->clean_up();
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL Dump: ' . $stmt->queryString . '. ' . PHP_EOL . 'Error Msg:' . $throwable->getMessage(), E_USER_ERROR);
        }

        $data = $stmt->fetchAll(!$fetch_col ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($fetch_col, $stmt);
        return $data;
    }

    /**
     * Execute prepared SQL and return execute result
     *
     * @return bool
     */
    public function execute(): bool
    {
        $stmt = parent::connect()->prepare($this->build_sql());

        try {
            $result = $stmt->execute($this->bind_value);
            $this->clean_up();
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL Dump: ' . $stmt->queryString . '. ' . PHP_EOL . 'Error Msg:' . $throwable->getMessage(), E_USER_ERROR);
        }

        unset($stmt);
        return $result;
    }

    /**
     * Get last insert value from AUTO_INCREMENT column
     *
     * @param string $name
     *
     * @return int
     */
    public function last_insert(string $name = ''): int
    {
        return (int)parent::connect()->lastInsertId('' === $name ? null : $name);
    }

    /**
     * Begin transaction
     *
     * @return bool
     */
    public static function begin(): bool
    {
        return parent::connect()->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     */
    public static function commit(): bool
    {
        return parent::connect()->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public static function rollback(): bool
    {
        return parent::connect()->rollBack();
    }

    /**
     * Set table
     *
     * @param string $table
     */
    private function table(string $table): void
    {
        if ('' === $table) {
            $table = strtr(get_class($this), '\\', '_');
        }

        $this->table = $this->escape($table);
        unset($table);
    }

    /**
     * Escape table name and column
     *
     * @param string $field
     *
     * @return string
     */
    private function escape(string $field): string
    {
        $list = false !== strpos($field, ',') ? explode(',', $field) : [$field];

        $list = array_map(
            static function (string $item): string
            {
                $list = [];
                $item = trim($item);

                $offset = 0;
                $length = strlen($item);
                $symbol = ['+', '-', '*', '/', '(', ')'];

                do {
                    $find  = [];
                    $match = false;

                    //find function position
                    foreach ($symbol as $mark) {
                        if (false === $pos = strpos($item, $mark, $offset)) {
                            continue;
                        }

                        if (empty($find) || $pos < $find[1]) {
                            $find = [$mark, $pos];
                        }

                        $match = true;
                    }

                    //Position NOT found
                    if (!$match) {
                        if ($offset < $length) {
                            $value   = substr($item, $offset);
                            $content = trim($value);

                            $list[] = [is_numeric($content) ? 'num' : ('' !== $content ? 'col' : 'sp'), $value];
                        }

                        break;
                    }

                    //Process symbols
                    if ('(' === $find[0]) {
                        if ($find[1] > $offset) {
                            $list[] = ['func', substr($item, $offset, $find[1] - $offset)];
                        }

                        $list[] = ['opt', '('];
                        ++$find[1];
                    } elseif ($find[1] > $offset) {
                        $value   = substr($item, $offset, $find[1] - $offset);
                        $content = trim($value);

                        $list[] = [is_numeric($content) ? 'num' : ('' !== $content ? 'col' : 'sp'), $value];
                        $list[] = ['opt', substr($item, $find[1]++, 1)];
                    } else {
                        $list[] = ['opt', substr($item, $find[1]++, 1)];
                    }

                    $offset = $find[1];
                } while ($match);

                unset($offset, $length, $symbol, $find, $match, $mark, $pos, $value, $content);

                //Process column values
                foreach ($list as $key => $val) {
                    //Skip except columns
                    if ('col' !== $val[0]) {
                        $list[$key] = $val[1];
                        continue;
                    }

                    //Process alias
                    if (false !== strpos($val[1], ' ')) {
                        $val[1] = false === stripos($item, ' as ')
                            ? str_ireplace(' ', '` `', $val[1])
                            : str_ireplace(' as ', '` AS `', $val[1]);
                    }

                    //Process connector
                    if (false !== strpos($val[1], '.')) {
                        $val[1] = str_replace('.', '`.`', $val[1]);
                    }

                    $list[$key] = !isset($list[$key - 1]) || ')' !== $list[$key - 1]
                        ? '`' . trim($val[1], '`') . '`'
                        : trim($val[1], '`') . '`';
                }

                $item = implode($list);

                unset($list, $key, $val);
                return $item;
            }, $list
        );

        $field = implode(', ', $list);

        unset($list);
        return $field;
    }

    /**
     * Build SQL caller
     */
    private function build_sql(): string
    {
        if ('' === $this->act) {
            throw new \PDOException('MySQL: No action provided!');
        }
        return trim($this->{'build_' . strtolower($this->act)}());
    }

    /**
     * Build INSERT SQL
     */
    private function build_insert(): string
    {
        return 'INSERT INTO ' . $this->table
            . ' (' . $this->escape(implode(', ', array_keys($this->value))) . ')'
            . ' VALUES (' . implode(', ', array_fill(0, count($this->bind_value), '?')) . ')';
    }

    /**
     * Build SELECT SQL
     */
    private function build_select(): string
    {
        $sql = 'SELECT ' . ('' !== $this->field ? $this->escape($this->field) : '*') . ' FROM ' . $this->table . ' ';

        if ('' !== $this->join) {
            $sql .= $this->join;
        }

        if ('' !== $this->where) {
            $sql .= 'WHERE ' . $this->where;
        }

        if ('' !== $this->group) {
            $sql .= 'GROUP BY ' . $this->escape($this->group) . ' ';
        }

        if ('' !== $this->having) {
            $sql .= 'HAVING ' . $this->having;
        }

        if ('' !== $this->order) {
            $sql .= 'ORDER BY ' . $this->order . ' ';
        }

        if ('' !== $this->limit) {
            $sql .= 'LIMIT ' . $this->limit;
        }

        //Rebuild bind values
        $this->bind_value = $this->bind_where;

        return $sql;
    }

    /**
     * Build UPDATE SQL
     */
    private function build_update(): string
    {
        if ('' === $this->where) {
            throw new \PDOException('MySQL: "UPDATE" action NOT allowed without "WHERE" condition!');
        }

        $sql = 'UPDATE ' . $this->table . ' SET ';

        $data = [];

        foreach ($this->value as $col => $val) {
            $data[] = $this->escape($col) . ' = ?';
        }

        foreach ($this->incr as $col => $val) {
            $opt = 0 <= $val ? '+' : '-';

            $data[] = $this->escape($col) . ' = ' . $this->escape($col) . $opt . abs($val);
        }

        $sql .= implode(', ', $data);

        unset($data, $opt);

        $sql .= ' WHERE ' . $this->where;

        if ('' !== $this->limit) {
            $sql .= 'LIMIT ' . $this->limit;
        }

        //Rebuild bind values
        $this->bind_value = array_merge($this->bind_value, $this->bind_where);

        return $sql;
    }

    /**
     * Build DELETE SQL
     */
    private function build_delete(): string
    {
        if ('' === $this->where) {
            throw new \PDOException('MySQL: "DELETE" action NOT allowed without "WHERE" condition!');
        }

        $sql = 'DELETE FROM ' . $this->table . ' WHERE ' . $this->where;

        if ('' !== $this->limit) {
            $sql .= 'LIMIT ' . $this->limit;
        }

        //Rebuild bind values
        $this->bind_value = $this->bind_where;

        return $sql;
    }

    /**
     * Build join conditions
     *
     * @param string $table
     * @param array  $where
     * @param string $type
     *
     * @return string
     */
    private function build_join(string $table, array $where, string $type): string
    {
        if (!in_array($item = strtoupper($type), ['LEFT', 'RIGHT', 'INNER'], true)) {
            throw new \PDOException('MySQL: Join operator: "' . $type . '" NOT allowed!');
        }

        $join = $item . ' JOIN ' . $this->escape($table) . ' ON ';

        if (count($where) === count($where, 1)) {
            $where = [$where];
        }

        $on = '';

        foreach ($where as $value) {
            if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                array_shift($value);
                $on .= $item . ' ';
            } elseif ('' !== $on) {
                $on .= 'AND ';
            }

            $on .= $this->escape($value[0]) . ' = ' . $this->escape($value[1]) . ' ';
        }

        $join .= $on;

        unset($table, $where, $type, $item, $on, $value);
        return $join;
    }

    /**
     * Build where conditions
     * Complex condition compatible
     *
     * @param array  $values
     * @param string $refer_to
     *
     * @return string
     */
    private function build_where(array $values, string $refer_to): string
    {
        $where = '';

        if (!is_array($values[0])) {
            $values = [$values];
        }

        foreach ($values as $value) {
            if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                array_shift($value);
                $where .= $item . ' ';
            } elseif ('' !== $where || '' !== $this->$refer_to) {
                $where .= 'AND ';
            }

            $where .= $this->escape($value[0]) . ' ';

            if (3 === count($value)) {
                if (!in_array($item = strtoupper($value[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
                    throw new \PDOException('MySQL: Operator: "' . $value[1] . '" NOT allowed!');
                }

                $where .= $item . ' ';

                if (!is_array($value[2])) {
                    $this->bind_where[] = &$value[2];

                    $where .= '? ';
                } else {
                    $this->bind_where = array_merge($this->bind_where, $value[2]);

                    $where .= 'BETWEEN' !== $item ? '(' . implode(', ', array_fill(0, count($value[2]), '?')) . ') ' : '? AND ? ';
                }
            } elseif (!is_array($value[1])) {
                if (!in_array($item = strtoupper($value[1]), ['IS NULL', 'IS NOT NULL'], true)) {
                    $this->bind_where[] = &$value[1];

                    $where .= '= ? ';
                } else {
                    $where .= $item . ' ';
                }
            } else {
                $this->bind_where = array_merge($this->bind_where, $value[1]);

                $where .= 'IN ' . '(' . implode(', ', array_fill(0, count($value[1]), '?')) . ')' . ' ';
            }
        }

        unset($values, $refer_to, $value, $item);
        return $where;
    }

    /**
     * Clean up stored data
     */
    private function clean_up(): void
    {
        $this->field = '';
        $this->table = '';
        $this->where = '';

        $this->join   = '';
        $this->group  = '';
        $this->having = '';

        $this->order = '';
        $this->limit = '';

        $this->incr  = [];
        $this->value = [];

        $this->bind_value = [];
        $this->bind_where = [];
    }
}