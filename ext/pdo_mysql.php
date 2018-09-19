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

    private $bind  = [];
    private $incr  = [];
    private $value = [];

    /**
     * Insert into table
     *
     * @param string $table
     *
     * @return object
     */
    public function insert(string $table = ''): object
    {
        $this->clean_up();
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
     * @return object
     */
    public function select(string $table = ''): object
    {
        $this->clean_up();
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
        $this->clean_up();
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
        $this->clean_up();
        $this->act = 'DELETE';

        $this->table($table);

        unset($table);
        return $this;
    }

    /**
     * Set insert values
     *
     * @param array $value
     * @param bool  $append
     *
     * @return object
     */
    public function value(array $value, bool $append = false): object
    {
        if (!$append) {
            $this->bind  = [current($value)];
            $this->value = &$value;
        } else {
            $this->bind[] = current($value);
            $this->value  += $value;
        }

        unset($value, $append);
        return $this;
    }

    /**
     * Set update increase values
     * Using negative number for decrement
     *
     * @param array $value
     * @param bool  $append
     *
     * @return object
     */
    public function incr(array $value, bool $append = false): object
    {
        $col = key($value);
        $val = current($value);

        if (!is_numeric($val)) {
            throw new \PDOException('MySQL: Increase value: "' . $val . '" for column "' . $col . '" ERROR!');
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
     * @return object
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
     * @return object
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
     * @return object
     */
    public function where(array $where): object
    {
        $this->where .= $this->build_where($where, $this->where);

        unset($where);
        return $this;
    }

    /**
     * Set having conditions
     *
     * @param array $having
     *
     * @return object
     */
    public function having(array $having): object
    {
        $this->having .= $this->build_where($having, $this->having);

        unset($having);
        return $this;
    }

    /**
     * Set order
     *
     * @param string $field
     * @param string $order
     *
     * @return object
     */
    public function order(string $field, string $order = 'ASC'): object
    {
        if (!in_array($item = strtoupper($order), ['ASC', 'DESC'], true)) {
            throw new \PDOException('MySQL: Order method: "' . $order . '" NOT supported!');
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
     * @return object
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
        //Execute directly
        if (false === $exec = parent::connect()->exec($sql)) {
            $exec = -1;
        }

        unset($sql);
        return $exec;
    }

    /**
     * Execute prepared SQL and return fetched data
     *
     * @param bool $column
     *
     * @return array
     */
    public function fetch(bool $column = false): array
    {
        $stmt = parent::connect()->prepare($this->build_sql());
        $stmt->execute($this->bind);

        $data = $stmt->fetchAll(!$column ? \PDO::FETCH_ASSOC : \PDO::FETCH_COLUMN);

        unset($stmt);
        return $data;
    }

    /**
     * Execute prepared SQL and return execute result
     *
     * @return bool
     */
    public function execute(): bool
    {
        $stmt   = parent::connect()->prepare($this->build_sql());
        $result = $stmt->execute($this->bind);

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
     * @throws \Exception
     */
    public static function begin(): bool
    {
        return parent::connect()->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     * @throws \Exception
     */
    public static function commit(): bool
    {
        return parent::connect()->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     * @throws \Exception
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
                    //Skip functions
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
        $this->bind = array_values($this->value);

        return 'INSERT INTO ' . $this->table
            . ' (' . $this->escape(implode(', ', array_keys($this->value))) . ') '
            . 'VALUES (' . implode(', ', array_fill(0, count($this->bind), '?')) . ')';
    }

    /**
     * Build SELECT SQL
     */
    private function build_select(): string
    {
        $sql = 'SELECT ' . ('' !== $this->field ? $this->escape($this->field) : '`*`') . ' FROM ' . $this->table . ' ';

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
     *
     * @param array  $value
     * @param string $refer_to
     *
     * @return string
     */
    private function build_where(array $value, string $refer_to): string
    {
        $where = '';

        if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
            array_shift($value);
            $where .= $item . ' ';
        } elseif ('' !== $refer_to) {
            $where .= 'AND ';
        }

        $where .= $this->escape($value[0]) . ' ';

        if (3 === count($value)) {
            if (!in_array($item = strtoupper($value[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
                throw new \PDOException('MySQL: Operator: "' . $value[1] . '" NOT allowed!');
            }

            $where .= $item . ' ';

            if (!is_array($value[2])) {
                $this->bind[] = &$value[2];

                $where .= '? ';
            } else {
                $this->bind = array_merge($this->bind, $value[2]);

                $where .= 'BETWEEN' !== $item ? '(' . implode(', ', array_fill(0, count($value[2]), '?')) . ') ' : '? AND ? ';
            }
        } elseif (!is_array($value[1])) {
            if (!in_array($item = strtoupper($value[1]), ['IS NULL', 'IS NOT NULL'], true)) {
                $this->bind[] = &$value[1];

                $where .= '= ? ';
            } else {
                $where .= $item . ' ';
            }
        } else {
            $this->bind = array_merge($this->bind, $value[1]);

            $where .= 'IN ' . '(' . implode(', ', array_fill(0, count($value[1]), '?')) . ')' . ' ';
        }

        unset($value, $item);
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

        $this->bind  = [];
        $this->incr  = [];
        $this->value = [];
    }
}