<?php

/**
 * MySQL Extension
 *
 * Copyright 2018-2019 kristenzz <kristenzz1314@gmail.com>
 * Copyright 2019 秋水之冰 <27206617@qq.com>
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
 * Class mysql
 *
 * @package ext
 */
class mysql extends factory
{
    /** @var \PDO $pdo */
    public $pdo;

    //Last SQL
    protected $sql = '';

    //Affected rows
    protected $rows = 0;

    //Table name
    protected $table = '';

    //Table prefix
    protected $prefix = '';

    //Runtime data
    protected $runtime = [];

    /**
     * Set string value with raw prefix
     *
     * @param string $value
     *
     * @return string
     */
    public function raw(string $value): string
    {
        if (!isset($this->runtime['raw'])) {
            $this->runtime['raw'] = ':' . hash('crc32b', uniqid(microtime() . mt_rand(), true)) . ':';
        }

        return $this->runtime['raw'] . $value;
    }

    /**
     * Set table name using prefix
     *
     * @param string $table
     *
     * @return $this
     */
    public function set_table(string $table): object
    {
        if ('' === $table) {
            if ('' !== $this->table) {
                return $this;
            }

            //Using class name
            $table = get_class($this);
        }

        if (false !== $pos = strrpos($table, '\\')) {
            $table = substr($table, $pos + 1);
        }

        $this->table = $this->escape($this->prefix . $table);

        unset($table, $pos);
        return $this;
    }

    /**
     * Set table prefix
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function set_prefix(string $prefix): object
    {
        $this->prefix = &$prefix;

        unset($prefix);
        return $this;
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
            $stmt = $this->pdo->prepare($this->runtime['sql']);
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $this->sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        return $stmt;
    }

    /**
     * Get last executed SQL
     *
     * @return string
     */
    public function get_last_sql(): string
    {
        return $this->sql;
    }

    /**
     * Get the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     *
     * @return int
     */
    public function get_last_affected(): int
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
    public function get_last_insert_id(string $name = ''): int
    {
        return (int)$this->pdo->lastInsertId('' === $name ? null : $name);
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
        $this->build_act(__FUNCTION__);
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
        $this->build_act(__FUNCTION__);
        $this->set_table($table);

        unset($table);
        return $this;
    }

    /**
     * Update table set data
     *
     * @param string $table
     *
     * @return $this
     */
    public function update(string $table = ''): object
    {
        $this->build_act(__FUNCTION__);
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
        $this->build_act(__FUNCTION__);
        $this->set_table($table);

        unset($table);
        return $this;
    }

    /**
     * Set select column fields
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

                $this->runtime['value']['v'][] = $val;
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
            $col = $this->escape($col);

            $this->runtime['value']['k'][$col] = $col . (0 <= $val ? '+' : '-') . (string)abs($val);
        }

        unset($values, $col, $val);
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
     * Set group by conditions
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
     * Set order by
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
     * Set limit
     *
     * @param int $offset
     * @param int $length
     *
     * @return $this
     */
    public function limit(int $offset, int $length = 0): object
    {
        $this->runtime['limit'] = 0 < $length
            ? (string)$offset . ', ' . (string)$length
            : (string)$offset;

        unset($offset, $length);
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
     * Begin transaction
     *
     * @return bool
     */
    public function begin(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback transaction
     *
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
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

            if (false === $this->rows = $this->pdo->exec($sql)) {
                $this->rows = -1;
            }
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        unset($sql);
        return $this->rows;
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
            $this->sql = &$sql;
            $sql_param = [$sql, $fetch_style];

            if ($fetch_style === \PDO::FETCH_COLUMN) {
                $sql_param[] = &$col_no;
            }

            $stmt = $this->pdo->query(...$sql_param);

            $this->rows = $stmt->rowCount();
        } catch (\Throwable $throwable) {
            throw new \PDOException('SQL: ' . $sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        unset($sql, $fetch_style, $col_no, $sql_param);
        return $stmt;
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
            throw new \PDOException('SQL: ' . $this->sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        unset($stmt);
        return $result;
    }

    /**
     * Fetch one row
     *
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
            throw new \PDOException('SQL: ' . $this->sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        $data = $stmt->fetch($fetch_style);

        if (!is_array($data)) {
            $data = false !== $data ? [$data] : [];
        }

        unset($fetch_style, $stmt);
        return $data;
    }

    /**
     * Fetch all rows
     *
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
            throw new \PDOException('SQL: ' . $this->sql . '. ' . PHP_EOL . 'Error:' . $throwable->getMessage(), E_USER_ERROR);
        }

        $data = $stmt->fetchAll($fetch_style);

        unset($fetch_style, $stmt);
        return $data;
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
                $symbol = ['.', '+', '-', '*', '/', '(', ')'];

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
     * Check a value is raw formatted
     *
     * @param string $value
     *
     * @return bool
     */
    protected function is_raw(string &$value): bool
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
     * Get condition data list
     *
     * @param array $cond_list
     * @param array $bind_list
     *
     * @return array
     */
    protected function get_cond(array $cond_list, array &$bind_list): array
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
     * Build valid action
     *
     * @param string $action
     */
    protected function build_act(string $action): void
    {
        //Check on going actions
        if (isset($this->runtime['action'])) {
            throw new \PDOException('Another "' . $this->runtime['action'] . '" action is waiting to execute!', E_USER_ERROR);
        }

        $this->runtime['action'] = strtoupper($action);
        unset($action);
    }

    /**
     * Build real/prep SQL
     */
    protected function build_sql(): void
    {
        //Call builder
        $sql_list = $this->{'build_' . strtolower($this->runtime['action'])}();

        //Build real SQL
        $this->sql = implode(' ', $sql_list['real']);

        //Build prepared SQL
        $this->runtime['sql'] = implode(' ', $sql_list['prep']);

        unset($sql_list);
    }

    /**
     * Build SQL conditions
     * Complex condition compatible
     *
     * @param array  $values
     * @param string $refer_key
     */
    protected function build_cond(array $values, string $refer_key): void
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
            if (1 < count($value)) {
                //Condition
                if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                    $cond_list[$cond_key][] = $item;
                    array_shift($value);
                } elseif (!empty($cond_list)) {
                    $cond_list[$cond_key][] = 'AND';
                }
            } else {
                //Check and add raw SQL
                $item = (string)current($value);

                if ($this->is_raw($item)) {
                    $cond_list[$cond_key][] = $item;

                    if (!isset($this->runtime['cond'])) {
                        $this->runtime['cond'] = [];
                    }
                }

                continue;
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
                $data   = array_values($data);

                $cond_list[$cond_key][] = '(';

                foreach ($data as $key => $item) {
                    $cond_list[$cond_key][] = '?';

                    if ($key < $commas) {
                        $cond_list[$cond_key][] = ',';
                    }

                    if (!is_string($item)) {
                        $this->runtime['cond'][] = $item;
                    } elseif (!is_numeric($item)) {
                        $this->runtime['cond'][] = '"' . $item . '"';
                    } elseif (false === strpos($item, '.')) {
                        $this->runtime['cond'][] = (int)$item;
                    } else {
                        $this->runtime['cond'][] = (float)$item;
                    }
                }

                $cond_list[$cond_key][] = ')';
            }

            ++$cond_key;
        }

        $this->runtime[$refer_key] = array_merge($this->runtime[$refer_key], $cond_list);
        unset($values, $refer_key, $cond_key, $cond_list, $value, $item, $field, $data, $commas, $key);
    }

    /**
     * Build SQL for INSERT
     *
     * @return array
     */
    protected function build_insert(): array
    {
        $result = ['prep' => [], 'real' => []];

        $result['prep'][] = 'INSERT INTO';
        $result['prep'][] = $this->table;
        $result['prep'][] = '(' . implode(', ', array_keys($this->runtime['value']['k'])) . ')';
        $result['prep'][] = 'VALUES';

        $result['real'] = $result['prep'];

        $result['prep'][] = '(' . implode(', ', array_values($this->runtime['value']['k'])) . ')';
        $result['real'][] = '(' . implode(', ', $this->get_cond($this->runtime['value']['k'], $this->runtime['value']['v'])) . ')';

        $this->runtime['bind'] = &$this->runtime['value']['v'];

        return $result;
    }

    /**
     * Build SQL for SELECT
     *
     * @return array
     */
    protected function build_select(): array
    {
        $result = ['prep' => [], 'real' => []];

        $result['prep'][] = 'SELECT';
        $result['prep'][] = isset($this->runtime['field']) ? implode(', ', $this->runtime['field']) : '*';
        $result['prep'][] = 'FROM ' . $this->table;

        if (isset($this->runtime['join'])) {
            $result['prep'][] = implode(' ', $this->runtime['join']);
        }

        $result['real'] = $result['prep'];

        if (isset($this->runtime['where'])) {
            $result['prep'][] = 'WHERE';
            $result['real'][] = 'WHERE';

            foreach ($this->runtime['where'] as $cond) {
                $result['prep'][] = implode(' ', $cond);
                $result['real'][] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            $this->runtime['bind'] = &$this->runtime['cond'];
        }

        if (isset($this->runtime['group'])) {
            $result['prep'][] = 'GROUP BY ' . implode(' ', $this->runtime['group']);
            $result['real'][] = 'GROUP BY ' . implode(' ', $this->runtime['group']);
        }

        if (isset($this->runtime['having'])) {
            $result['prep'][] = 'HAVING';
            $result['real'][] = 'HAVING';

            foreach ($this->runtime['having'] as $cond) {
                $result['prep'][] = implode(' ', $cond);
                $result['real'][] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            if (!isset($this->runtime['bind'])) {
                $this->runtime['bind'] = &$this->runtime['cond'];
            }
        }

        if (isset($this->runtime['order'])) {
            $result['prep'][] = 'ORDER BY ' . implode(', ', $this->runtime['order']);
            $result['real'][] = 'ORDER BY ' . implode(', ', $this->runtime['order']);
        }

        if (isset($this->runtime['limit'])) {
            $result['prep'][] = 'LIMIT ' . $this->runtime['limit'];
            $result['real'][] = 'LIMIT ' . $this->runtime['limit'];
        }

        if (isset($this->runtime['lock'])) {
            $result['prep'][] = 'FOR ' . $this->runtime['lock'];
            $result['real'][] = 'FOR ' . $this->runtime['lock'];
        }

        unset($cond);
        return $result;
    }

    /**
     * Build SQL for UPDATE
     *
     * @return array
     */
    protected function build_update(): array
    {
        $result = ['prep' => [], 'real' => []];

        $result['prep'][] = 'UPDATE';
        $result['prep'][] = $this->table;

        if (isset($this->runtime['join'])) {
            $result['prep'][] = implode(' ', $this->runtime['join']);
        }

        $result['prep'][] = 'SET';

        $result['real'] = $result['prep'];

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

        $result['prep'][] = implode(', ', $updates);
        $result['real'][] = implode(', ', $values);

        $this->runtime['bind'] = $this->runtime['value']['v'] ?? [];

        if (isset($this->runtime['where'])) {
            $result['prep'][] = 'WHERE';
            $result['real'][] = 'WHERE';

            foreach ($this->runtime['where'] as $cond) {
                $result['prep'][] = implode(' ', $cond);
                $result['real'][] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            $this->runtime['bind'] = array_merge($this->runtime['bind'], $this->runtime['cond']);
        }

        if (isset($this->runtime['limit'])) {
            $result['prep'][] = 'LIMIT ' . $this->runtime['limit'];
            $result['real'][] = 'LIMIT ' . $this->runtime['limit'];
        }

        unset($updates, $values, $col, $val, $item, $cond);
        return $result;
    }

    /**
     * Build SQL for DELETE
     *
     * @return array
     */
    protected function build_delete(): array
    {
        $result = ['prep' => [], 'real' => []];

        $result['prep'][] = 'DELETE FROM';
        $result['prep'][] = $this->table;

        $result['real'] = $result['prep'];

        if (isset($this->runtime['where'])) {
            $result['prep'][] = 'WHERE';
            $result['real'][] = 'WHERE';

            foreach ($this->runtime['where'] as $cond) {
                $result['prep'][] = implode(' ', $cond);
                $result['real'][] = implode(' ', $this->get_cond($cond, $this->runtime['cond']));
            }

            $this->runtime['bind'] = &$this->runtime['cond'];
        }

        if (isset($this->runtime['limit'])) {
            $result['prep'][] = 'LIMIT ' . $this->runtime['limit'];
            $result['real'][] = 'LIMIT ' . $this->runtime['limit'];
        }

        unset($cond);
        return $result;
    }
}