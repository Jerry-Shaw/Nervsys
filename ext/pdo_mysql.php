<?php
/**
 * Pdo MySQL Extension
 *
 * Copyright 2018-2019 kristenzz <kristenzz1314@gmail.com>
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
    //Last SQL
    private $sql = '';

    //Affected rows
    private $rows = 0;

    //Runtime params
    private $params = [];

    /**
     * Insert into table
     *
     * @param string $table
     *
     * @return $this
     */
    public function insert(string $table = ''): object
    {
        $this->set_action('INSERT', $table);

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
        $this->set_action('SELECT', $table);

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
        $this->set_action('UPDATE', $table);

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
        $this->set_action('DELETE', $table);

        unset($table);
        return $this;
    }

    /**
     * Set lock mode
     * UPDATE/SHARE, NOWAIT/SKIP LOCKED
     *
     * @param string ...$modes
     *
     * @return $this
     */
    public function lock(string ...$modes): object
    {
        $this->params['lock'] = implode(' ', $modes);

        unset($modes);
        return $this;
    }

    /**
     * Set insert/update value pairs
     *
     * @param array $values
     *
     * @return $this
     */
    public function value(array $values): object
    {
        foreach ($values as $key => $value) {
            $this->params['value'][$key] = $bind_key = $this->rand_key($key);

            $this->params['bind_value'][$bind_key] = $value;
        }

        unset($values, $key, $value, $bind_key);
        return $this;
    }

    /**
     * Set update increase values
     * Using negative number for decrement
     *
     * @param array $values
     *
     * @return $this
     */
    public function incr(array $values): object
    {
        foreach ($values as $key => $value) {
            if (is_string($value)) {
                $value = false === strpos($value, '.') ? (int)$value : (float)$value;
            }

            $this->params['incr'][$key] = $value;
        }

        unset($values, $key, $value);
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
        !isset($this->params['field'])
            ? $this->params['field'] = implode(', ', $fields)
            : $this->params['field'] .= ', ' . implode(', ', $fields);

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
        !isset($this->params['join'])
            ? $this->params['join'] = strtoupper($type) . ' JOIN ' . $this->escape($table) . ' ON '
            : $this->params['join'] .= strtoupper($type) . ' JOIN ' . $this->escape($table) . ' ON ';

        if (count($where) === count($where, 1)) {
            $where = [$where];
        }

        $condition = '';

        foreach ($where as $value) {
            if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                array_shift($value);
                $condition .= $item . ' ';
            } elseif ('' !== $condition) {
                $condition .= 'AND ';
            }

            $condition .= $this->escape($value[0]) . ' = ' . $this->escape($value[1]) . ' ';
        }

        $this->params['join'] .= $condition;

        unset($table, $where, $type, $condition, $value, $item);
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
        if (count($where) === count($where, 1)) {
            $where = [$where];
        }

        !isset($this->params['where'])
            ? $this->params['where'] = $this->build_condition($where, 'where')
            : $this->params['where'] .= $this->build_condition($where, 'where');

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
        !isset($this->params['having'])
            ? $this->params['having'] = $this->build_condition($having, 'having')
            : $this->params['having'] .= $this->build_condition($having, 'having');

        unset($having);
        return $this;
    }

    /**
     * Set order
     *
     * @param array $orders
     *
     * @return $this
     */
    public function order(array $orders): object
    {
        $list = [];

        foreach ($orders as $col => $val) {
            $list[] = $this->escape($col) . ' ' . strtoupper($val);
        }

        $this->params['order'] = implode(', ', $list);

        unset($orders, $list, $col, $val);
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
        $this->params['group'] = implode(', ', $group);

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
        $this->params['limit'] = 0 === $length ? '0, ' . $offset : $offset . ', ' . $length;

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
            $this->sql = &$sql;

            if (false === $this->rows = $this->instance->exec($sql)) {
                $this->rows = -1;
            }
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        unset($sql);
        return $this->rows;
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
            $this->sql = &$sql;

            $stmt = !$fetch_col
                ? $this->instance->query($sql, \PDO::FETCH_ASSOC)
                : $this->instance->query($sql, \PDO::FETCH_COLUMN, $col_no);

            $this->rows = $stmt->rowCount();
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
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
        try {
            $this->fill_sql();

            $stmt = $this->instance->prepare($this->sql);
            $stmt->execute($this->params['bind_value'] ?? null);

            $this->rows   = $stmt->rowCount();
            $this->params = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $this->sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
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
        try {
            $this->fill_sql();

            $stmt   = $this->instance->prepare($this->sql);
            $result = $stmt->execute($this->params['bind_value'] ?? null);

            $this->rows   = $stmt->rowCount();
            $this->params = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $this->sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        unset($stmt);
        return $result;
    }

    /**
     * Get last executed SQL
     *
     * @return string
     */
    public function last_sql(): string
    {
        return $this->sql;
    }

    /**
     * Get the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     *
     * @return int
     */
    public function last_affect(): int
    {
        return $this->rows;
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
        return (int)$this->instance->lastInsertId('' === $name ? null : $name);
    }

    /**
     * Begin transaction
     *
     * @return bool
     */
    public function begin(): bool
    {
        return $this->instance->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->instance->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->instance->rollBack();
    }

    /**
     * Build random bind key
     *
     * @param string $key
     *
     * @return string
     */
    private function rand_key(string $key): string
    {
        return ':' . strtr($key, '.', '_') . '_' . hash('crc32b', uniqid(mt_rand(), true));
    }

    /**
     * Set action & table
     *
     * @param string $action
     * @param string $table
     */
    private function set_action(string $action, string $table): void
    {
        if ('' === $table) {
            $table = get_class($this);

            if (false !== $pos = strrpos($table, '\\')) {
                $table = substr($table, $pos + 1);
            }

            unset($pos);
        }

        $this->params['action'] = &$action;
        $this->params['table']  = &$table;

        unset($action, $table);
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
     * Fill SQL
     */
    private function fill_sql(): void
    {
        $this->sql = trim($this->{'build_' . strtolower($this->params['action'])}());
    }

    /**
     * Build INSERT SQL
     */
    private function build_insert(): string
    {
        return 'INSERT INTO ' . $this->escape($this->params['table'])
            . ' (' . $this->escape(implode(', ', array_keys($this->params['value']))) . ')'
            . ' VALUES (' . implode(', ', $this->params['value']) . ')';
    }

    /**
     * Build SELECT SQL
     */
    private function build_select(): string
    {
        $sql = 'SELECT ' . (isset($this->params['field']) ? $this->escape($this->params['field']) : '*')
            . ' FROM ' . $this->escape($this->params['table']) . ' ';

        if (isset($this->params['join'])) {
            $sql .= $this->params['join'];
        }

        if (isset($this->params['where'])) {
            $sql .= 'WHERE ' . $this->params['where'];

            $this->params['bind_value'] = isset($this->params['bind_value'])
                ? $this->params['bind_value'] + $this->params['bind_where']
                : $this->params['bind_where'];
        }

        if (isset($this->params['group'])) {
            $sql .= 'GROUP BY ' . $this->escape($this->params['group']) . ' ';
        }

        if (isset($this->params['having'])) {
            $sql .= 'HAVING ' . $this->params['having'];

            $this->params['bind_value'] = isset($this->params['bind_value'])
                ? $this->params['bind_value'] + $this->params['bind_having']
                : $this->params['bind_having'];
        }

        if (isset($this->params['order'])) {
            $sql .= 'ORDER BY ' . $this->params['order'] . ' ';
        }

        if (isset($this->params['limit'])) {
            $sql .= 'LIMIT ' . $this->params['limit'] . ' ';
        }

        if (isset($this->params['lock'])) {
            $sql .= 'FOR ' . $this->params['lock'];
        }

        return $sql;
    }

    /**
     * Build UPDATE SQL
     */
    private function build_update(): string
    {
        $sql = 'UPDATE ' . $this->escape($this->params['table']) . ' SET ';

        $data = [];

        if (isset($this->params['value'])) {
            foreach ($this->params['value'] as $col => $val) {
                $data[] = $this->escape($col) . ' = ' . $val;
            }
        }

        if (isset($this->params['incr'])) {
            foreach ($this->params['incr'] as $col => $val) {
                $opt = 0 <= $val ? '+' : '-';
                $col = $this->escape($col);

                $data[] = $col . ' = ' . $col . $opt . abs($val);
            }
        }

        $sql .= implode(', ', $data) . ' ';

        unset($data, $col, $val, $opt);

        if (isset($this->params['where'])) {
            $sql .= 'WHERE ' . $this->params['where'];

            $this->params['bind_value'] = isset($this->params['bind_value'])
                ? $this->params['bind_value'] + $this->params['bind_where']
                : $this->params['bind_where'];
        }

        if (isset($this->params['limit'])) {
            $sql .= 'LIMIT ' . $this->params['limit'];
        }

        return $sql;
    }

    /**
     * Build DELETE SQL
     */
    private function build_delete(): string
    {
        $sql = 'DELETE FROM ' . $this->escape($this->params['table']) . ' ';

        if (isset($this->params['where'])) {
            $sql .= 'WHERE ' . $this->params['where'];

            $this->params['bind_value'] = isset($this->params['bind_value'])
                ? $this->params['bind_value'] + $this->params['bind_where']
                : $this->params['bind_where'];
        }

        if (isset($this->params['limit'])) {
            $sql .= 'LIMIT ' . $this->params['limit'];
        }

        return $sql;
    }

    /**
     * Build where conditions
     * Complex condition compatible
     *
     * @param array  $values
     * @param string $refer_key
     *
     * @return string
     */
    private function build_condition(array $values, string $refer_key): string
    {
        $condition = '';
        $param_key = 'bind_' . $refer_key;

        foreach ($values as $value) {
            if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                array_shift($value);
                $condition .= $item . ' ';
            } elseif ('' !== $condition || isset($this->params[$refer_key])) {
                $condition .= 'AND ';
            }

            $condition .= $this->escape($value[0]) . ' ';

            if (3 === count($value)) {
                if (!in_array($item = strtoupper($value[1]), ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
                    throw new \PDOException('Incorrect operator: "' . $value[1] . '"!', E_USER_ERROR);
                }

                $condition .= $item . ' ';

                if (!is_array($value[2])) {
                    $bind_key  = $this->rand_key($value[0]);
                    $condition .= $bind_key . ' ';

                    $this->params[$param_key][$bind_key] = $value[2];
                } elseif ('BETWEEN' !== $item) {
                    $bind_keys = [];

                    foreach ($value[2] as $val) {
                        $bind_keys[] = $bind_key = $this->rand_key($value[0]);

                        $this->params[$param_key][$bind_key] = $val;
                    }

                    $condition .= '(' . implode(', ', $bind_keys) . ') ';
                } else {
                    $bind_keys = [];

                    foreach ($value[2] as $val) {
                        $bind_keys[] = $bind_key = $this->rand_key($value[0]);

                        $this->params[$param_key][$bind_key] = $val;
                    }

                    $condition .= implode(' AND ', $bind_keys) . ' ';
                }
            } elseif (!is_array($value[1])) {
                if (!in_array($item = strtoupper($value[1]), ['IS NULL', 'IS NOT NULL'], true)) {
                    $bind_key  = $this->rand_key($value[0]);
                    $condition .= '= ' . $bind_key . ' ';

                    $this->params[$param_key][$bind_key] = $value[1];
                } else {
                    $condition .= $item . ' ';
                }
            } else {
                $bind_keys = [];

                foreach ($value[1] as $val) {
                    $bind_keys[] = $bind_key = $this->rand_key($value[0]);

                    $this->params[$param_key][$bind_key] = $val;
                }

                $condition .= 'IN (' . implode(', ', $bind_keys) . ') ';
            }
        }

        unset($values, $refer_key, $param_key, $value, $item, $bind_key, $bind_keys, $val);
        return $condition;
    }
}