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

namespace Ext;

use Core\Factory;

/**
 * Class extMySQL
 *
 * @package Ext
 */
class libMySQL extends Factory
{
    /** @var \PDO $pdo */
    public \PDO $pdo;

    //Affected rows
    protected int $rows = 0;

    //Table name
    protected string $table = '';

    //Table prefix
    protected string $prefix = '';

    //Last SQL
    protected string $last_sql = '';

    //Runtime data
    protected array $runtime = [];

    /**
     * Bind to PDO connection
     *
     * @param \PDO $PDO
     *
     * @return $this
     */
    public function bindPDO(\PDO $PDO): self
    {
        $this->pdo = &$PDO;

        unset($PDO);
        return $this;
    }

    /**
     * Set table prefix
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function setPrefix(string $prefix): self
    {
        $this->prefix = &$prefix;

        unset($prefix);
        return $this;
    }

    /**
     * Set table name using prefix
     *
     * @param string $table
     *
     * @return $this
     */
    public function setTable(string $table = ''): self
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

        $this->table = $this->prefix . $table;

        unset($table, $pos);
        return $this;
    }

    /**
     * Set string value with raw prefix
     *
     * @param string $value
     *
     * @return string
     */
    public function setAsRaw(string $value): string
    {
        if (!isset($this->runtime['raw'])) {
            $this->runtime['raw'] = ':' . hash('crc32b', uniqid(microtime() . mt_rand(), true)) . ':';
        }

        return $this->runtime['raw'] . $value;
    }


    public function insert(array ...$data): self
    {
        $param = [];

        foreach ($data as $value) {
            $param += $value;
        }

        $this->runtime['action'] = 'insert';

        $this->runtime['cols'] = array_keys($param);
        $this->runtime['bind'] = array_values($param);

        unset($data, $param, $value);
        return $this;
    }


    public function update(array ...$data): self
    {
        $param = [];

        foreach ($data as $value) {
            $param += $value;
        }

        $this->runtime['action'] = 'update';

        $this->runtime['cols'] = array_keys($param);
        $this->runtime['bind'] = array_values($param);

        unset($data, $param, $value);
        return $this;
    }


    public function select(string ...$column): self
    {
        $this->runtime['action'] = 'select';
        $this->runtime['cols']   = implode(',', $column);

        unset($data);
        return $this;
    }

    public function delete(): self
    {
        $this->runtime['action'] = 'delete';

        return $this;
    }

    public function to(string $table): self
    {
        $this->runtime['table'] = $table;

        unset($table);
        return $this;
    }


    public function from(string $table): self
    {
        $this->runtime['table'] = $table;

        unset($table);
        return $this;
    }


    public function where(array ...$where): self
    {
        $this->runtime['where'] = [];
        $this->runtime['stage'] = 'where';

        if (empty($where)) {
            return $this;
        }

        $cond = $this->parseWhere($where);

        array_unshift($cond, '(');
        array_push($cond, ')');

        $this->runtime['where'] = &$cond;

        unset($where, $cond);
        return $this;
    }

    public function having(array ...$where): self
    {
        $this->runtime['having'] = [];
        $this->runtime['stage']  = 'having';

        if (empty($where)) {
            return $this;
        }

        $cond = $this->parseWhere($where);

        array_unshift($cond, '(');
        array_push($cond, ')');

        $this->runtime['having'] = &$cond;

        unset($where, $cond);
        return $this;
    }

    public function and(array ...$where): self
    {
        if (empty($where)) {
            return $this;
        }

        $cond = $this->parseWhere($where);

        array_unshift($cond, !empty($this->runtime[$this->runtime['stage']]) ? 'AND (' : '(');
        array_push($cond, ')');

        $this->runtime[$this->runtime['stage']] = array_merge($this->runtime[$this->runtime['stage']], $cond);

        unset($where, $cond);
        return $this;
    }

    public function or(array ...$where): self
    {
        if (empty($where)) {
            return $this;
        }

        $cond = $this->parseWhere($where);

        array_unshift($cond, !empty($this->runtime[$this->runtime['stage']]) ? 'OR (' : '(');
        array_push($cond, ')');

        $this->runtime[$this->runtime['stage']] = array_merge($this->runtime[$this->runtime['stage']], $cond);

        unset($where, $cond);
        return $this;
    }

    public function join(string $table, string $type = 'INNER'): self
    {
        $this->runtime['stage'] = 'join';

        !isset($this->runtime['join'])
            ? $this->runtime['join'] = [$type . ' JOIN ' . $table]
            : $this->runtime['join'][] = $type . ' JOIN ' . $table;

        unset($table, $type);
        return $this;
    }

    public function on(array ...$where): self
    {
        if (empty($where)) {
            return $this;
        }

        $cond = $this->parseWhere($where);

        array_unshift($cond, !empty($this->runtime[$this->runtime['stage']]) ? 'ON (' : '(');
        array_push($cond, ')');

        $this->runtime[$this->runtime['stage']] = array_merge($this->runtime[$this->runtime['stage']], $cond);

        unset($where, $cond);
        return $this;
    }


    private function parseWhere(array $where): array
    {
        //Initial cond values
        if (!isset($this->runtime['bind'])) {
            $this->runtime['bind'] = [];
        }

        //Initial cond list
        $cond_list = [];

        //Process conditions
        foreach ($where as $value) {
            if (1 < count($value)) {
                //Condition
                if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                    $cond_list[] = $item;
                    array_shift($value);
                } elseif (!empty($cond_list)) {
                    $cond_list[] = 'AND';
                }
            } else {
                //Using raw SQL
                $item = (string)current($value);

                if ($this->isRaw($item)) {
                    $cond_list[] = $item;
                }

                continue;
            }

            //Field
            $cond_list[] = array_shift($value);

            //Operator
            if (2 === count($value)) {
                $item = strtoupper(array_shift($value));

                if (!in_array($item, ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
                    throw new \PDOException('Invalid operator: "' . $item . '"!', E_USER_ERROR);
                }

                $cond_list[] = $item;
            } elseif (!is_array($value[0])) {
                if (!in_array($item = strtoupper($value[0]), ['IS NULL', 'IS NOT NULL'], true)) {
                    $cond_list[] = '=';
                } else {
                    $cond_list[] = $item;
                    $value[0]    = '';
                }
            } else {
                $cond_list[] = 'IN';
            }

            //Data
            if (!is_array($data = current($value))) {
                if ('' === $data) {
                    continue;
                }

                if ('join' !== $this->runtime['stage']) {
                    $cond_list[] = '?';

                    $this->runtime['bind'][] = $data;
                } else {
                    $cond_list[] = $data;
                }
            } else {
                $data = array_values($data);

                if ('BETWEEN' !== end($cond_list)) {
                    if ('join' !== $this->runtime['stage']) {
                        $param = '';
                        $count = count($data) - 1;

                        foreach ($data as $key => $item) {
                            $param .= $key < $count ? '?,' : '?';

                            if (is_int($item) || is_float($item) || is_numeric($item)) {
                                $this->runtime['bind'][] = $item;
                            } else {
                                $this->runtime['bind'][] = '"' . $item . '"';
                            }
                        }
                    } else {
                        $param = implode(',', $data);
                    }

                    $cond_list[] = '(' . $param . ')';
                } else {
                    if ('join' !== $this->runtime['stage']) {
                        $cond_list[] = '? AND ?';

                        $this->runtime['bind'][] = $data[0];
                        $this->runtime['bind'][] = $data[1];
                    } else {
                        $cond_list[] = $data[0] . ' AND ' . $data[1];
                    }
                }
            }
        }

        unset($where, $value, $item, $data, $param, $count, $key);
        return $cond_list;
    }


    public function group(string ...$fields): self
    {
        foreach ($fields as &$field) {
            $this->isRaw($field);
        }

        $this->runtime['group'] = implode(',', $fields);
        unset($fields, $field);
        return $this;
    }

    public function order(array ...$orders): self
    {
        $param = $order = [];

        foreach ($orders as $val) {
            $param += $val;
        }

        foreach ($param as $col => $val) {
            $this->isRaw($col);
            $order[] = $col . ' ' . strtoupper($val);
        }

        $this->runtime['order'] = implode(',', $order);

        unset($orders, $param, $order, $col, $val);
        return $this;
    }

    public function limit(int $offset, int $length = 0): object
    {
        $this->runtime['limit'] = 0 < $length
            ? (string)$offset . ', ' . (string)$length
            : (string)$offset;

        unset($offset, $length);
        return $this;
    }

    public function execute(): bool
    {

    }

    public function fetchValue(): string
    {

    }

    public function fetchRow(): array
    {

    }

    public function fetchAll(): array
    {

    }

    public function fetchSTMT(): \PDOStatement
    {

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


    protected function isRaw(string &$raw_sql): bool
    {
        if (!isset($this->runtime['raw'])) {
            return false;
        }

        if (0 !== strpos($raw_sql, $this->runtime['raw'])) {
            return false;
        }

        $raw_sql = substr($raw_sql, strlen($this->runtime['raw']));
        return true;
    }

    protected function checkAction(): void
    {
        if (!isset($this->runtime['action'])) {
            return;
        }

        throw new \PDOException('"' . $this->runtime['action'] . '" action is NOT executed!' . PHP_EOL . 'SQL: ' . $this->getReadableSQL($this->{'build' . ucfirst($this->runtime['action'])}(), $this->runtime['bind']), E_USER_ERROR);
    }

    /**
     * Build real/prep SQL
     */
    public function buildSQL(): void
    {
        $this->runtime['sql'] = $this->{'build' . ucfirst($this->runtime['action'])}();

        $this->last_sql = $this->getReadableSQL($this->runtime['sql'], $this->runtime['bind']);
    }

    /**
     * Get current PDOStatement
     *
     * @return \PDOStatement
     */
    public function getSTMT(): \PDOStatement
    {
        $this->build_sql();

        try {
            $stmt = $this->pdo->prepare($this->runtime['sql']);
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        }

        return $stmt;
    }

    /**
     * Get last executed SQL
     *
     * @return string
     */
    public function getLastSQL(): string
    {
        return $this->last_sql;
    }

    /**
     * Get the number of rows affected by the last DELETE, INSERT, or UPDATE statement
     *
     * @return int
     */
    public function getAffectedRows(): int
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
    public function getLastInsertId(string $name = ''): int
    {
        return (int)$this->pdo->lastInsertId('' === $name ? null : $name);
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
     * Build SQL for INSERT
     *
     * @return string
     */
    public function buildInsert(): string
    {
        $sql = 'INSERT INTO ' . ($this->runtime['table'] ?? $this->table);
        $sql .= ' (' . implode(',', $this->runtime['cols']) . ')';
        $sql .= ' VALUES (' . implode(',', array_pad([], count($this->runtime['bind']), '?')) . ')';

        return $sql;
    }

    /**
     * Build SQL for SELECT
     *
     * @return string
     */
    public function buildSelect(): string
    {
        $sql = 'SELECT ' . ($this->runtime['cols'] ?? '*');
        $sql .= ' FROM ' . ($this->runtime['table'] ?? $this->table);

        return $this->appendCond($sql);
    }

    /**
     * Build SQL for UPDATE
     *
     * @return string
     */
    public function buildUpdate(): string
    {
        $sql = 'UPDATE ' . ($this->runtime['table'] ?? $this->table) . ' SET';

        $data = [];
        foreach ($this->runtime['cols'] as $col) {
            $data[] = $col . '=?';
        }

        $sql .= ' ' . implode(',', $data);

        unset($data, $col);
        return $this->appendCond($sql);
    }

    /**
     * Build SQL for DELETE
     *
     * @return string
     */
    public function buildDelete(): string
    {
        return $this->appendCond('DELETE FROM ' . ($this->runtime['table'] ?? $this->table));
    }

    protected function appendCond(string $sql): string
    {
        if (isset($this->runtime['join'])) {
            $sql .= ' ' . implode(' ', $this->runtime['join']);
        }

        if (isset($this->runtime['where'])) {
            $sql .= ' WHERE ' . implode(' ', $this->runtime['where']);
        }

        if (isset($this->runtime['group'])) {
            $sql .= ' GROUP BY ' . $this->runtime['group'];
        }

        if (isset($this->runtime['having'])) {
            $sql .= ' HAVING ' . implode(' ', $this->runtime['having']);
        }

        if (isset($this->runtime['order'])) {
            $sql .= ' ORDER BY ' . $this->runtime['order'];
        }

        if (isset($this->runtime['limit'])) {
            $sql .= ' LIMIT ' . $this->runtime['limit'];
        }

        if (isset($this->runtime['lock'])) {
            $sql .= ' FOR ' . $this->runtime['lock'];
        }

        return $sql;
    }

    protected function getReadableSQL(string $sql, array $params): string
    {
        $sql = str_replace('?', '%s', $sql);
        $sql = printf($sql, ...$params);

        unset($params);
        return $sql;
    }


    //=============================


    /**
     * Exec SQL and return affected rows
     *
     * @param string $sql
     *
     * @return int
     */
    public function de_exec(string $sql): int
    {
        try {
            $this->last_sql = &$sql;

            if (false === $this->rows = $this->pdo->exec($sql)) {
                $this->rows = -1;
            }
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
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
    public function de_query(string $sql, int $fetch_style = \PDO::FETCH_ASSOC, int $col_no = 0): \PDOStatement
    {
        try {
            $this->last_sql = &$sql;
            $sql_param      = [$sql, $fetch_style];

            if ($fetch_style === \PDO::FETCH_COLUMN) {
                $sql_param[] = &$col_no;
            }

            $stmt = $this->pdo->query(...$sql_param);

            $this->rows = $stmt->rowCount();
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        }

        unset($sql, $fetch_style, $col_no, $sql_param);
        return $stmt;
    }

    /**
     * Execute prepared SQL and return execute result
     *
     * @return bool
     */
    public function de_execute(): bool
    {
        $stmt = $this->getSTMT();

        try {
            $result = $stmt->execute($this->runtime['bind'] ?? []);

            $this->rows    = $stmt->rowCount();
            $this->runtime = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
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
    public function de_fetch_row(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $stmt = $this->getSTMT();

        try {
            $stmt->execute($this->runtime['bind'] ?? []);

            $this->rows    = $stmt->rowCount();
            $this->runtime = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
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
    public function de_fetch_all(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $stmt = $this->getSTMT();

        try {
            $stmt->execute($this->runtime['bind'] ?? []);

            $this->rows    = $stmt->rowCount();
            $this->runtime = [];
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        }

        $data = $stmt->fetchAll($fetch_style);

        unset($fetch_style, $stmt);
        return $data;
    }


    /**
     * Get condition data list
     *
     * @param array $cond_list
     * @param array $bind_list
     *
     * @return array
     */
    protected function de_get_cond(array $cond_list, array &$bind_list): array
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
    protected function de_build_act(string $action): void
    {
        //Check on going actions
        if (isset($this->runtime['action'])) {
            //Build SQL & throw PDOException
            $this->build_sql();
            throw new \PDOException('Another "' . $this->runtime['action'] . '" action is on going!' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        }

        $this->runtime['action'] = strtoupper($action);
        unset($action);
    }

    /**
     * Build SQL conditions
     * Complex condition compatible
     *
     * @param array  $values
     * @param string $refer_key
     */
    protected function buildCond(array $values, string $refer_key): void
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

                if ($this->isRaw($item)) {
                    $cond_list[$cond_key][] = $item;

                    if (!isset($this->runtime['bind'])) {
                        $this->runtime['bind'] = [];
                    }
                }

                continue;
            }

            //Field
            $field = array_shift($value);

            if (!$this->isRaw($field)) {
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
                    $this->runtime['bind'][] = $data;
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
                        $this->runtime['bind'][] = $item;
                    } elseif (!is_numeric($item)) {
                        $this->runtime['bind'][] = '"' . $item . '"';
                    } elseif (false === strpos($item, '.')) {
                        $this->runtime['bind'][] = (int)$item;
                    } else {
                        $this->runtime['bind'][] = (float)$item;
                    }
                }

                $cond_list[$cond_key][] = ')';
            }

            ++$cond_key;
        }

        $this->runtime[$refer_key] = array_merge($this->runtime[$refer_key], $cond_list);
        unset($values, $refer_key, $cond_key, $cond_list, $value, $item, $field, $data, $commas, $key);
    }

}