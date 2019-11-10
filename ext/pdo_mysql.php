<?php

/**
 * Pdo MySQL Extension
 *
 * Copyright 2018-2019 kristenzz <kristenzz1314@gmail.com>
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

/**
 * Class pdo_mysql
 *
 * @package ext
 */
class pdo_mysql extends pdo
{
    //Affected rows
    protected $rows = 0;

    //Table prefix
    protected $prefix = '';

    //Runtime data
    protected $runtime = [];

    /**
     * Set table name using prefix
     *
     * @param string $table
     */
    protected function set_table(string $table): void
    {
        if (isset($this->runtime['table'])) {
            return;
        }

        if ('' === $table) {
            $table = get_class($this);

            if (false !== $pos = strrpos($table, '\\')) {
                $table = substr($table, $pos + 1);
            }

            unset($pos);
        }

        $this->runtime['table'] = $this->escape($this->prefix . $table);
        unset($table);
    }

    /**
     * Insert into table
     *
     * @param string $table
     *
     * @return $this
     */
    public function insert(string $table = ''): object
    {
        $this->runtime['action'] = 'INSERT';
        $this->set_table($table);

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
        $this->runtime['action'] = 'SELECT';
        $this->set_table($table);

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
        $this->runtime['action'] = 'UPDATE';
        $this->set_table($table);

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
        $this->runtime['action'] = 'DELETE';
        $this->set_table($table);

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
        $this->runtime['lock'] = implode(' ', $modes);

        unset($modes);
        return $this;
    }


    /**
     * Set string value with raw prefix
     *
     * @param string $value
     *
     * @return string
     */
    public function set_raw(string $value): string
    {
        if (!isset($this->runtime['raw'])) {
            $this->runtime['raw'] = ':' . hash('crc32b', uniqid(microtime() . mt_rand(), true)) . ':';
        }

        return $this->runtime['raw'] . $value;
    }

    /**
     * Check a value is raw formatted
     *
     * @param string $value
     *
     * @return bool
     */
    private function is_raw(string &$value): bool
    {
        if (!isset($this->runtime['raw'])) {
            return false;
        }

        if (0 !== strpos($value, $this->runtime['raw'])) {
            return false;
        }

        $value = substr($value, strlen($this->runtime['raw']));
        return true;
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
        foreach ($values as $col => $val) {
            $col = $this->escape($col);

            if (is_string($val) && $this->is_raw($val)) {
                $this->runtime['value']['k'][$col] = $val;
            } else {
                $this->runtime['value']['k'][$col] = '?';
                $this->runtime['value']['v'][]     = $val;
            }
        }

        unset($values, $col, $val);
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
        foreach ($values as $col => $val) {
            $opt = 0 <= $val ? '+' : '-';
            $col = $this->escape($col);

            $this->runtime['value']['k'][$col] = $col . $opt . abs($val);
        }

        unset($values, $col, $val, $opt);
        return $this;
    }


    /**
     * Set select fields
     *
     * @param string ...$fields
     *
     * @return $this
     */
    public function fields(string ...$fields): object
    {
        foreach ($fields as $field) {
            if (!$this->is_raw($field)) {
                $field = $this->escape($field);
            }

            $this->runtime['field'][] = $field;
        }

        unset($fields, $field);
        return $this;
    }


    /**
     * Set join conditions
     *
     * @param string $table
     * @param array  $on
     * @param string $type
     *
     * @return $this
     */
    public function join(string $table, array $on, string $type = 'INNER'): object
    {
        $join = [strtoupper($type) . ' JOIN'];

        $join[] = $this->escape($this->prefix . $table);
        $join[] = 'ON';

        if (count($on) === count($on, COUNT_RECURSIVE)) {
            $on = [$on];
        }

        //Condition list
        $cond_key  = 0;
        $cond_list = [];

        //Process conditions
        foreach ($on as $value) {
            //Condition
            if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                $cond_list[$cond_key][] = strtoupper(array_shift($value));
            } elseif (!empty($cond_list)) {
                $cond_list[$cond_key][] = 'AND';
            }

            //Left field
            $cond_list[$cond_key][] = $this->escape(array_shift($value));

            //Operator
            if (1 === count($value)) {
                $cond_list[$cond_key][] = '=';
            } else {
                $item = strtoupper(array_shift($value));

                if (!in_array($item, ['=', '<', '>', '<=', '>=', '<>', '!='], true)) {
                    throw new \PDOException('Invalid operator: "' . $item . '"!', E_USER_ERROR);
                }

                $cond_list[$cond_key][] = $item;
            }

            //Right field
            $cond_list[$cond_key][] = $this->escape(current($value));

            ++$cond_key;
        }

        //Merge to join
        foreach ($cond_list as $value) {
            $join[] = implode(' ', $value);
        }

        $this->runtime['join'][] = implode(' ', $join);

        unset($table, $on, $type, $join, $cond_key, $cond_list, $value, $item);
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
        $this->runtime['limit'] = 0 === $length ? (string)$offset : (string)$offset . ', ' . (string)$length;

        unset($offset, $length);
        return $this;
    }

    /**
     * Escape table name and column
     *
     * @param string $field
     *
     * @return string
     */
    protected function escape(string $field): string
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
     * Build SQL conditions
     * Complex condition compatible
     *
     * @param array  $values
     * @param string $refer_key
     */
    private function build_cond(array $values, string $refer_key): void
    {
        //Rewrite condition
        if (count($values) === count($values, COUNT_RECURSIVE)) {
            $values = [$values];
        }

        //Preset values
        if (!isset($this->runtime[$refer_key])) {
            $this->runtime[$refer_key] = [];
        }

        //Condition list
        $cond_key  = 0;
        $cond_list = [];

        //Process conditions
        foreach ($values as $value) {
            //Condition
            if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                $cond_list[$cond_key][] = strtoupper(array_shift($value));
            } elseif (!empty($cond_list)) {
                $cond_list[$cond_key][] = 'AND';
            }

            //Field
            $field = array_shift($value);

            if (!$this->is_raw($field)) {
                $field = $this->escape($field);
            }

            $cond_list[$cond_key][] = $field;

            //Operator
            if (2 === count($value)) {
                $item = strtoupper(array_shift($value));

                if (!in_array($item, ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
                    throw new \PDOException('Invalid operator: "' . $item . '"!', E_USER_ERROR);
                }

                $cond_list[$cond_key][] = $item;
            } elseif (!is_array($value[0])) {
                if (!in_array($item = strtoupper($value[0]), ['IS NULL', 'IS NOT NULL'], true)) {
                    $cond_list[$cond_key][] = '=';
                } else {
                    $value[0] = $this->set_raw($item);
                }
            } else {
                $cond_list[$cond_key][] = 'IN';
            }

            //Data
            if (!is_array($data = current($value))) {
                if (!is_string($data) || !$this->is_raw($data)) {
                    $cond_list[$cond_key][]  = '?';
                    $this->runtime['cond'][] = $data;
                } else {
                    $cond_list[$cond_key][] = $data;
                }
            } else {
                $commas = count($data) - 1;

                $cond_list[$cond_key][] = '(';

                foreach ($data as $key => $item) {
                    $cond_list[$cond_key][] = '?';

                    if ($key < $commas) {
                        $cond_list[$cond_key][] = ',';
                    }

                    $this->runtime['cond'][] = $item;
                }

                $cond_list[$cond_key][] = ')';
            }

            ++$cond_key;
        }

        //Merge conditions
        $this->runtime[$refer_key] = array_merge($this->runtime[$refer_key], $cond_list);
        unset($values, $refer_key, $cond_key, $cond_list, $value, $item, $field, $data, $commas, $key);
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
        $this->build_cond($where, 'where');

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
        $this->build_cond($having, 'having');

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
        foreach ($orders as $col => $val) {
            if (!$this->is_raw($col)) {
                $col = $this->escape($col);
            }

            $this->runtime['order'][] = $col . ' ' . strtoupper($val);
        }

        unset($orders, $col, $val);
        return $this;
    }

    /**
     * Set group conditions
     *
     * @param string ...$groups
     *
     * @return $this
     */
    public function group(string ...$groups): object
    {
        foreach ($groups as $group) {
            if (!$this->is_raw($group)) {
                $group = $this->escape($group);
            }

            $this->runtime['group'][] = $group;
        }

        unset($groups, $group);
        return $this;
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
     * Build prep/real SQL
     */
    public function build_sql(): void
    {
        $sql_list = $this->{'build_' . strtolower($this->runtime['action'])}();

        $this->runtime['sql_prep'] = &$sql_list['prep'];
        $this->runtime['sql_real'] = &$sql_list['real'];

        unset($sql_list);
    }

    /**
     * Get condition data list
     *
     * @param array $cond_list
     * @param array $bind_list
     *
     * @return array
     */
    private function get_cond(array $cond_list, array $bind_list): array
    {
        $data = [];

        foreach ($cond_list as $value) {
            if ('?' === $value) {
                $item   = current($bind_list);
                $data[] = is_string($item) ? '"' . $item . '"' : $item;
                next($bind_list);
            } else {
                $data[] = $value;
            }
        }

        unset($cond_list, $bind_list, $value, $item);
        return $data;
    }


    /**
     * Build SQL for INSERT
     *
     * @return array
     */
    private function build_insert(): array
    {
        $result = $prep = [];

        $prep[] = 'INSERT INTO';
        $prep[] = $this->runtime['table'];
        $prep[] = '(' . implode(', ', array_keys($this->runtime['value']['k'])) . ')';
        $prep[] = 'VALUES';

        $real = $prep;

        $prep[] = '(' . implode(', ', array_values($this->runtime['value']['k'])) . ')';
        $real[] = '(' . implode(', ', $this->get_cond($this->runtime['value']['k'], $this->runtime['value']['v'])) . ')';

        $result['prep'] = implode(' ', $prep);
        $result['real'] = implode(' ', $real);

        $this->runtime['bind'] = &$this->runtime['value']['v'];

        unset($prep, $real);
        return $result;
    }

    /**
     * Build SQL for SELECT
     *
     * @return array
     */
    private function build_select(): array
    {
        $result = $prep = [];

        $prep[] = 'SELECT';
        $prep[] = isset($this->runtime['field']) ? implode(', ', $this->runtime['field']) : '*';
        $prep[] = 'FROM ' . $this->runtime['table'];

        if (isset($this->runtime['join'])) {
            $prep[] = implode(' ', $this->runtime['join']);
        }

        $real = $prep;

        if (isset($this->runtime['where'])) {
            $prep[] = 'WHERE';
            $real[] = 'WHERE';

            foreach ($this->runtime['where'] as $cond) {
                $prep[] = implode(' ', $cond);
                $real[] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            $this->runtime['bind'] = &$this->runtime['value']['cond'];
        }

        if (isset($this->runtime['group'])) {
            $prep[] = 'GROUP BY ' . implode(' ', $this->runtime['group']);
            $real[] = 'GROUP BY ' . implode(' ', $this->runtime['group']);
        }

        if (isset($this->runtime['having'])) {
            $prep[] = 'HAVING';
            $real[] = 'HAVING';

            foreach ($this->runtime['having'] as $cond) {
                $prep[] = implode(' ', $cond);
                $real[] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            if (!isset($this->runtime['bind'])) {
                $this->runtime['bind'] = &$this->runtime['value']['cond'];
            }
        }

        if (isset($this->runtime['order'])) {
            $prep[] = 'ORDER BY ' . implode(', ', $this->runtime['order']);
            $real[] = 'ORDER BY ' . implode(', ', $this->runtime['order']);
        }

        if (isset($this->runtime['limit'])) {
            $prep[] = 'LIMIT ' . $this->runtime['limit'];
            $real[] = 'LIMIT ' . $this->runtime['limit'];
        }

        if (isset($this->runtime['lock'])) {
            $prep[] = 'FOR ' . $this->runtime['lock'];
            $real[] = 'FOR ' . $this->runtime['lock'];
        }

        $result['prep'] = implode(' ', $prep);
        $result['real'] = implode(' ', $real);

        unset($prep, $real, $cond);
        return $result;
    }

    /**
     * Build SQL for UPDATE
     *
     * @return array
     */
    private function build_update(): array
    {
        $result = $prep = [];

        $prep[] = 'UPDATE';
        $prep[] = $this->runtime['table'];
        $prep[] = 'SET';

        $real = $prep;

        $updates = $values = [];

        foreach ($this->runtime['value']['k'] as $col => $val) {
            $updates[] = $col . ' = ' . $val;

            if ('?' === $val) {
                $item     = current($this->runtime['value']['v']);
                $values[] = $col . ' = ' . (is_string($item) ? '"' . $item . '"' : $item);
                next($this->runtime['value']['v']);
            } else {
                $values[] = $col . ' = ' . $val;
            }
        }

        $prep[] = implode(', ', $updates);
        $real[] = implode(', ', $values);

        if (isset($this->runtime['where'])) {
            $prep[] = 'WHERE';
            $real[] = 'WHERE';

            foreach ($this->runtime['where'] as $cond) {
                $prep[] = implode(' ', $cond);
                $real[] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            $this->runtime['bind'] = array_merge($this->runtime['value']['v'], $this->runtime['cond']);
        } else {
            $this->runtime['bind'] = &$this->runtime['value']['v'];
        }

        if (isset($this->runtime['limit'])) {
            $prep[] = 'LIMIT ' . $this->runtime['limit'];
            $real[] = 'LIMIT ' . $this->runtime['limit'];
        }

        unset($prep, $real, $updates, $values, $col, $val, $item, $cond);
        return $result;
    }

    /**
     * Build SQL for DELETE
     *
     * @return array
     */
    private function build_delete(): array
    {
        $result = $prep = [];

        $prep[] = 'DELETE FROM';
        $prep[] = $this->runtime['table'];

        $real = $prep;

        if (isset($this->runtime['where'])) {
            $prep[] = 'WHERE';
            $real[] = 'WHERE';

            foreach ($this->runtime['where'] as $cond) {
                $prep[] = implode(' ', $cond);
                $real[] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            $this->runtime['bind'] = &$this->runtime['value']['cond'];
        }

        if (isset($this->runtime['limit'])) {
            $prep[] = 'LIMIT ' . $this->runtime['limit'];
            $real[] = 'LIMIT ' . $this->runtime['limit'];
        }

        unset($prep, $real, $cond);
        return $result;
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
            $this->runtime['sql_real'] = &$sql;

            if (false === $this->rows = $this->instance->exec($sql)) {
                $this->rows = -1;
            }
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(),
                E_USER_ERROR);
        }

        unset($sql);
        return $this->rows;
    }



    /**
     * Get current PDOStatement
     *
     * @return \PDOStatement
     */
    public function get_stmt(): \PDOStatement
    {
        $this->build_sql();

        try {
            $stmt = $this->instance->prepare($this->runtime['sql_prep']);
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $this->runtime['sql_real'] . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(),
                E_USER_ERROR);
        }

        return $stmt;
    }


    /**
     * Query SQL and return PDOStatement
     *
     * @param string $sql
     * @param int    $fetch_style
     * @param int    $col_no
     *
     * @return \PDOStatement
     */
    public function query(string $sql, int $fetch_style = \PDO::FETCH_ASSOC, int $col_no = 0): \PDOStatement
    {
        try {
            $this->runtime['sql_real'] = &$sql;
            $sql_param                 = [$sql, $fetch_style];

            if ($fetch_style === \PDO::FETCH_COLUMN) {
                $sql_param[] = &$col_no;
            }

            $stmt = $this->instance->query(...$sql_param);

            $this->rows = $stmt->rowCount();
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(),
                E_USER_ERROR);
        }

        unset($sql, $fetch_style, $col_no, $sql_param);
        return $stmt;
    }


    /**
     * Fetch one row
     * @param int $fetch_style
     *
     * @return array
     */
    public function fetch_row(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $stmt = $this->get_stmt();

        try {
            $stmt->execute($this->runtime['bind'] ?? []);

            $this->rows    = $stmt->rowCount();
            $this->runtime = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $this->runtime['sql_real'] . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(),
                E_USER_ERROR);
        }

        $data = $stmt->fetch($fetch_style);

        if (!is_array($data)) {
            $data = [$data];
        }

        unset($fetch_style, $stmt);
        return $data;
    }

    /**
     * Fetch all rows
     * @param int $fetch_style
     *
     * @return array
     */
    public function fetch_all(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $stmt = $this->get_stmt();

        try {
            $stmt->execute($this->runtime['bind'] ?? []);

            $this->rows    = $stmt->rowCount();
            $this->runtime = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $this->runtime['sql_real'] . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(),
                E_USER_ERROR);
        }

        $data = $stmt->fetchAll($fetch_style);

        unset($fetch_style, $stmt);
        return $data;
    }


    /**
     * Execute prepared SQL and return execute result
     *
     * @return bool
     */
    public function execute(): bool
    {
        $stmt = $this->get_stmt();

        try {
            $result = $stmt->execute($this->runtime['bind'] ?? []);

            $this->rows    = $stmt->rowCount();
            $this->runtime = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $this->runtime['sql_real'] . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(),
                E_USER_ERROR);
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
        return $this->runtime['sql_real'];
    }


}