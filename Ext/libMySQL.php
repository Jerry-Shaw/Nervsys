<?php

/**
 * MySQL Extension
 *
 * Copyright 2018-2019 kristenzz <kristenzz1314@gmail.com>
 * Copyright 2019-2021 秋水之冰 <27206617@qq.com>
 * Copyright 2021 wwj <904428723@qq.com>
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
use Core\Lib\IOUnit;

/**
 * Class libMySQL
 *
 * @package Ext
 */
class libMySQL extends Factory
{
    /** @var \PDO $pdo */
    public \PDO $pdo;

    /** @var libPDO $lib_pdo */
    public libPDO $lib_pdo;

    public int $retry_times   = 3;
    public int $affected_rows = 0;

    public string $last_sql     = '';
    public string $table_name   = '';
    public string $table_prefix = '';

    //Runtime data container
    public array $runtime_data = [];

    public int   $explain_type  = 0;
    public array $explain_keeps = [];

    const EXPLAIN_LEVEL = ['NULL', 'system', 'const', 'eq_ref', 'ref', 'range', 'index', 'ALL'];

    /**
     * Bind to PDO connection
     *
     * @param \PDO $pdo
     *
     * @return $this
     */
    public function bindPdo(\PDO $pdo): self
    {
        $this->pdo = &$pdo;

        unset($pdo);
        return $this;
    }

    /**
     * Bind libPDO object
     *
     * @param libPDO $lib_pdo
     *
     * @return $this
     */
    public function bindLibPdo(libPDO $lib_pdo): self
    {
        $this->lib_pdo = &$lib_pdo;
        $this->pdo     = $lib_pdo->connect();

        unset($lib_pdo);
        return $this;
    }

    /**
     * Set SQL explain mode
     *
     * @param int    $explain_type  (0: disable; 1: output; 2: save log; 3: both)
     * @param string $explain_level (NULL, system, const, eq_ref, ref, range, index, ALL)
     *
     * @return $this
     */
    public function setExplainMode(int $explain_type, string $explain_level = 'range'): self
    {
        $keep_level = array_search($explain_level, self::EXPLAIN_LEVEL, true);

        $this->explain_keeps = false !== $keep_level
            ? array_slice(self::EXPLAIN_LEVEL, $keep_level)
            : self::EXPLAIN_LEVEL;

        $this->explain_type = &$explain_type;

        unset($explain_type, $explain_level, $keep_level);
        return $this;
    }

    /**
     * Set table prefix
     *
     * @param string $table_prefix
     *
     * @return $this
     */
    public function setTablePrefix(string $table_prefix): self
    {
        $this->table_prefix = &$table_prefix;

        unset($table_prefix);
        return $this;
    }

    /**
     * Set table name using prefix
     *
     * @param string $table_name
     *
     * @return $this
     */
    public function setTableName(string $table_name = ''): self
    {
        if ('' === $table_name) {
            if ('' !== $this->table_name) {
                return $this;
            }

            $table_name = get_class($this);
        }

        if (false !== $pos = strrpos($table_name, '\\')) {
            $table_name = substr($table_name, $pos + 1);
        }

        $this->table_name = $this->table_prefix . $table_name;

        unset($table_name, $pos);
        return $this;
    }

    /**
     * Set string value as raw SQL part
     *
     * @param string $value
     *
     * @return string
     */
    public function setAsRaw(string $value): string
    {
        if (!isset($this->runtime_data['raw'])) {
            $this->runtime_data['raw'] = hash('crc32b', uniqid(microtime() . mt_rand(), true)) . ':';
        }

        return $this->runtime_data['raw'] . $value;
    }

    /**
     * Get one row
     *
     * @param int $fetch_style
     *
     * @return array
     */
    public function getRow(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $data = ($this->execStmt()['stmt'])->fetch($fetch_style);

        if (!is_array($data)) {
            $data = false !== $data ? [$data] : [];
        }

        unset($fetch_style);
        return $data;
    }

    /**
     * Get all rows
     *
     * @param int $fetch_style
     *
     * @return array
     */
    public function getAll(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $data = ($this->execStmt()['stmt'])->fetchAll($fetch_style);

        unset($fetch_style);
        return $data;
    }

    /**
     * Get last executed SQL
     *
     * @return string
     */
    public function getLastSql(): string
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
        return $this->affected_rows;
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
     * Insert action
     *
     * @param array ...$data
     *
     * @return $this
     */
    public function insert(array ...$data): self
    {
        $this->isReady();

        $param = [];

        foreach ($data as $value) {
            $param += $value;
        }

        $this->runtime_data['action'] = 'insert';

        $this->runtime_data['cols'] = array_keys($param);
        $this->runtime_data['bind'] = array_values($param);

        unset($data, $param, $value);
        return $this;
    }

    /**
     * Update action
     *
     * @param array ...$data
     *
     * @return $this
     */
    public function update(array ...$data): self
    {
        $this->isReady();

        $this->runtime_data['value']  = [];
        $this->runtime_data['action'] = 'update';

        foreach ($data as $value) {
            $this->runtime_data['value'] += $value;
        }

        unset($data, $value);
        return $this;
    }

    /**
     * Select action
     *
     * @param string ...$column
     *
     * @return $this
     */
    public function select(string ...$column): self
    {
        $this->isReady();

        $this->runtime_data['action'] = 'select';
        $this->runtime_data['cols']   = !empty($column) ? implode(',', $column) : '*';

        unset($data);
        return $this;
    }

    /**
     * Delete action
     *
     * @return $this
     */
    public function delete(): self
    {
        $this->isReady();
        $this->runtime_data['action'] = 'delete';

        return $this;
    }

    /**
     * Set to table
     *
     * @param string $table
     *
     * @return $this
     */
    public function to(string $table): self
    {
        $this->runtime_data['table'] = $this->table_prefix . $table;

        unset($table);
        return $this;
    }

    /**
     * Set from table
     *
     * @param string $table
     *
     * @return $this
     */
    public function from(string $table): self
    {
        $this->runtime_data['table'] = $this->table_prefix . $table;

        unset($table);
        return $this;
    }

    /**
     * Set join
     *
     * @param string $table
     * @param string $type
     *
     * @return $this
     */
    public function join(string $table, string $type = 'INNER'): self
    {
        $this->runtime_data['on']     = [];
        $this->runtime_data['join']   ??= [];
        $this->runtime_data['stage']  = 'join';
        $this->runtime_data['join'][] = $type . ' JOIN ' . $this->table_prefix . $table;

        unset($table, $type);
        return $this;
    }

    /**
     * Set cond for "ON"
     *
     * @param array ...$where
     *
     * @return $this
     */
    public function on(array ...$where): self
    {
        if (empty($where)) {
            return $this;
        }

        $this->runtime_data['join'] = array_merge($this->runtime_data['join'], $this->runtime_data['on'] = $this->parseCond($where, 'on'));

        unset($where);
        return $this;
    }

    /**
     * Set where condition
     *
     * @param array ...$where
     *
     * @return $this
     */
    public function where(array ...$where): self
    {
        $this->runtime_data['where'] ??= [];
        $this->runtime_data['stage'] = 'where';

        if (empty($where = array_filter($where))) {
            return $this;
        }

        $this->runtime_data['where'] = array_merge($this->runtime_data['where'], $this->parseCond($where, 'where'));

        unset($where);
        return $this;
    }

    /**
     * Set having condition
     *
     * @param array ...$where
     *
     * @return $this
     */
    public function having(array ...$where): self
    {
        $this->runtime_data['having'] ??= [];
        $this->runtime_data['stage']  = 'having';

        if (empty($where = array_filter($where))) {
            return $this;
        }

        $this->runtime_data['having'] = array_merge($this->runtime_data['having'], $this->parseCond($where, 'having'));

        unset($where);
        return $this;
    }

    /**
     * Set cond for "AND"
     *
     * @param array ...$where
     *
     * @return $this
     */
    public function and(array ...$where): self
    {
        if (empty($where = array_filter($where))) {
            return $this;
        }

        $cond = $this->parseCond($where, 'and');

        array_unshift($cond, !empty($this->runtime_data[$this->runtime_data['stage']]) ? 'AND (' : '(');

        $cond[] = ')';

        $this->runtime_data[$this->runtime_data['stage']] = array_merge($this->runtime_data[$this->runtime_data['stage']], $cond);

        unset($where, $cond);
        return $this;
    }

    /**
     * Set cond for "OR"
     *
     * @param array ...$where
     *
     * @return $this
     */
    public function or(array ...$where): self
    {
        if (empty($where = array_filter($where))) {
            return $this;
        }

        $cond = $this->parseCond($where, 'or');

        array_unshift($cond, !empty($this->runtime_data[$this->runtime_data['stage']]) ? 'OR (' : '(');

        $cond[] = ')';

        $this->runtime_data[$this->runtime_data['stage']] = array_merge($this->runtime_data[$this->runtime_data['stage']], $cond);

        unset($where, $cond);
        return $this;
    }

    /**
     * Set group
     *
     * @param string ...$fields
     *
     * @return $this
     */
    public function group(string ...$fields): self
    {
        foreach ($fields as &$field) {
            $this->isRaw($field);
        }

        $this->runtime_data['group'] = implode(',', $fields);
        unset($fields, $field);
        return $this;
    }

    /**
     * Set orders
     *
     * @param array ...$orders
     *
     * @return $this
     */
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

        $this->runtime_data['order'] = implode(',', $order);

        unset($orders, $param, $order, $col, $val);
        return $this;
    }

    /**
     * Set SQL limitation
     *
     * @param int $offset
     * @param int $length
     *
     * @return $this
     */
    public function limit(int $offset, int $length = 0): self
    {
        $this->runtime_data['limit'] = 0 < $length
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
    public function lock(string ...$modes): self
    {
        $this->runtime_data['lock'] = implode(' ', $modes);

        unset($modes);
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
            $this->last_sql = &$sql;

            if (false === $this->affected_rows = $this->pdo->exec($sql)) {
                $this->affected_rows = -1;
            }
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        }

        unset($sql);
        return $this->affected_rows;
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
            $this->last_sql = &$sql;
            $sql_param      = [$sql, $fetch_style];

            if ($fetch_style === \PDO::FETCH_COLUMN) {
                $sql_param[] = &$col_no;
            }

            $stmt = $this->pdo->query(...$sql_param);

            $this->affected_rows = $stmt->rowCount();
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        }

        unset($sql, $fetch_style, $col_no, $sql_param);
        return $stmt;
    }

    /**
     * Execute SQL statement
     *
     * @return bool
     */
    public function execute(): bool
    {
        return $this->execStmt()['exec'];
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
     * Build runtime SQL
     *
     * @return string
     */
    public function buildSql(): string
    {
        $runtime_sql    = $this->{'build' . ucfirst($this->runtime_data['action'])}();
        $this->last_sql = $this->buildReadableSql($runtime_sql, $this->runtime_data['bind'] ?? []);

        0 < $this->explain_type && 'select' === $this->runtime_data['action'] && $this->explainSql($this->last_sql);

        return $runtime_sql;
    }

    /**
     * Explain SQL
     *
     * @param string $sql
     */
    public function explainSql(string $sql): void
    {
        $explain = $this->query('EXPLAIN ' . $sql)->fetchAll(\PDO::FETCH_ASSOC);

        //Keep needed types
        foreach ($explain as $key => $item) {
            if (!in_array($item['type'], $this->explain_keeps, true)) {
                unset($explain[$key]);
            }
        }

        if (!empty($explain)) {
            //Build result
            $result = ['sql' => &$sql, 'res' => &$explain];

            //Output result
            if (1 === (1 & $this->explain_type)) {
                IOUnit::new()->addMsgData('SQL_EXPLAIN', $result);
            }

            //Save result
            if (2 === (2 & $this->explain_type)) {
                libLog::new('SQL_EXPLAIN')->add($result)->save();
            }
        }

        unset($sql, $explain, $key, $item, $result);
    }

    /**
     * Execute prepared PDOStatement
     *
     * @param int $i
     *
     * @return array
     */
    protected function execStmt(int $i = 1): array
    {
        $result = [];

        try {
            $result['stmt'] = $this->pdo->prepare($this->buildSql());
            $result['exec'] = $result['stmt']->execute($this->runtime_data['bind'] ?? []);

            $this->runtime_data  = [];
            $this->affected_rows = $result['stmt']->rowCount();
        } catch (\Throwable $throwable) {
            if (!in_array($result['stmt']->errorCode(), ['2006', '2013'], true) || $i >= $this->retry_times) {
                $this->runtime_data = [];
                throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
            }

            //Reconnect to PDO server
            $this->pdo = $this->lib_pdo->connect();

            //Retry execute PDOStatement
            return $this->execStmt(++$i);
        }

        return $result;
    }

    /**
     * Build SQL for INSERT
     *
     * @return string
     */
    protected function buildInsert(): string
    {
        $sql = 'INSERT INTO ' . ($this->runtime_data['table'] ?? $this->table_name);
        $sql .= ' (' . implode(',', $this->runtime_data['cols']) . ')';
        $sql .= ' VALUES (' . implode(',', array_pad([], count($this->runtime_data['bind']), '?')) . ')';

        return $sql;
    }

    /**
     * Build SQL for SELECT
     *
     * @return string
     */
    protected function buildSelect(): string
    {
        $sql = 'SELECT ' . ($this->runtime_data['cols'] ?? '*');
        $sql .= ' FROM ' . ($this->runtime_data['table'] ?? $this->table_name);

        return $this->appendCond($sql);
    }

    /**
     * Build SQL for UPDATE
     *
     * @return string
     */
    protected function buildUpdate(): string
    {
        $sql = 'UPDATE ' . ($this->runtime_data['table'] ?? $this->table_name) . ' SET';

        $data = [];
        foreach ($this->runtime_data['value'] as $col => $val) {
            if (0 === strpos($val, $col)) {
                $raw = str_replace(' ', '', $val);
                $opt = substr($raw, $pos = strlen($col), 1);
                $num = substr($raw, $pos + 1);

                if (in_array($opt, ['+', '-', '*', '/'], true) && is_numeric($num)) {
                    $data[] = $col . '=' . $col . $opt . (string)(false === strpos($num, '.') ? (int)$num : (float)$num);
                    continue;
                }
            }

            $data[] = $col . '=?';

            $this->runtime_data['bind'][] = $val;
        }

        $sql .= ' ' . implode(',', $data);

        unset($data, $col, $val, $raw, $opt, $num);
        return $this->appendCond($sql);
    }

    /**
     * Build SQL for DELETE
     *
     * @return string
     */
    protected function buildDelete(): string
    {
        return $this->appendCond('DELETE FROM ' . ($this->runtime_data['table'] ?? $this->table_name));
    }

    /**
     * Build readable SQL with params
     *
     * @param string $sql
     * @param array  $params
     *
     * @return string
     */
    protected function buildReadableSql(string $sql, array $params): string
    {
        foreach ($params as &$param) {
            if (is_string($param)) {
                $this->isRaw($param);
                $param = '"' . addslashes($param) . '"';
            }
        }

        $sql = str_replace('?', '%s', $sql);
        $sql = sprintf($sql, ...$params);

        unset($params);
        return $sql;
    }

    /**
     * Check raw SQL
     *
     * @param string $raw_sql
     *
     * @return bool
     */
    protected function isRaw(string &$raw_sql): bool
    {
        if (!isset($this->runtime_data['raw'])) {
            return false;
        }

        if (0 !== strpos($raw_sql, $this->runtime_data['raw'])) {
            return false;
        }

        $raw_sql = substr($raw_sql, strlen($this->runtime_data['raw']));
        return true;
    }

    /**
     * Check statement ready
     */
    protected function isReady(): void
    {
        if (isset($this->runtime_data['action'])) {
            //Get blocked SQL
            $blocked_sql = $this->buildReadableSql($this->{'build' . ucfirst($this->runtime_data['action'])}(), $this->runtime_data['bind']);
            //Reset runtime data
            $this->runtime_data = [];
            //Throw PDOException
            throw new \PDOException($blocked_sql . ' NOT execute!', E_USER_ERROR);
        }
    }

    /**
     * Parse condition values
     *
     * @param array  $where
     * @param string $option
     *
     * @return array
     */
    protected function parseCond(array $where, string $option): array
    {
        $cond_list  = [];
        $in_group   = false;
        $bind_stage = 'bind_' . $this->runtime_data['stage'];

        $this->runtime_data[$bind_stage] ??= [];

        //option
        if (in_array($option, ['on', 'where', 'having'], true)) {
            $in_group    = true;
            $cond_list[] = (empty($this->runtime_data[$option]) ? strtoupper($option) : 'AND') . ' (';
        }

        foreach ($where as $value) {
            //Condition
            if (1 < count($value)) {
                if (in_array($item = strtoupper($value[0]), ['AND', '&&', 'OR', '||', 'XOR', '&', '~', '|', '^'], true)) {
                    $cond_list[] = $item;
                    array_shift($value);
                } elseif (1 < count($cond_list)) {
                    $cond_list[] = 'AND';
                }
            } else {
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

                if (!in_array($item, ['=', '<', '>', '<=', '>=', '<>', '!=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
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
            if (!is_array($data = array_shift($value))) {
                if ('join' !== $this->runtime_data['stage']) {
                    $cond_list[] = '?';

                    $this->runtime_data[$bind_stage][] = $data;
                } else {
                    $cond_list[] = $data;
                }
            } else {
                $data = array_values($data);

                if ('BETWEEN' !== end($cond_list)) {
                    if ('join' !== $this->runtime_data['stage']) {
                        $param = '';
                        $count = count($data) - 1;

                        foreach ($data as $key => $item) {
                            $param .= $key < $count ? '?,' : '?';

                            $this->runtime_data[$bind_stage][] = $item;
                        }
                    } else {
                        $param = implode(',', $data);
                    }

                    $cond_list[] = '(' . $param . ')';
                } else {
                    if ('join' !== $this->runtime_data['stage']) {
                        $cond_list[] = '? AND ?';

                        $this->runtime_data[$bind_stage][] = $data[0];
                        $this->runtime_data[$bind_stage][] = $data[1];
                    } else {
                        $cond_list[] = $data[0] . ' AND ' . $data[1];
                    }
                }
            }
        }

        if ($in_group) {
            $cond_list[] = ')';
        }

        unset($where, $option, $in_group, $bind_stage, $value, $item, $data, $param, $count, $key);
        return $cond_list;
    }

    /**
     * Append condition string
     *
     * @param string $sql
     *
     * @return string
     */
    protected function appendCond(string $sql): string
    {
        if (isset($this->runtime_data['join'])) {
            $sql .= ' ' . implode(' ', $this->runtime_data['join']);
        }

        if (isset($this->runtime_data['where'])) {
            $this->runtime_data['bind'] = array_merge($this->runtime_data['bind'] ?? [], $this->runtime_data['bind_where'] ?? []);

            $sql .= ' ' . implode(' ', $this->runtime_data['where']);
            unset($this->runtime_data['where'], $this->runtime_data['bind_where']);
        }

        if (isset($this->runtime_data['group'])) {
            $sql .= ' GROUP BY ' . $this->runtime_data['group'];
        }

        if (isset($this->runtime_data['having'])) {
            $this->runtime_data['bind'] = array_merge($this->runtime_data['bind'] ?? [], $this->runtime_data['bind_having'] ?? []);

            $sql .= ' ' . implode(' ', $this->runtime_data['having']);
            unset($this->runtime_data['having'], $this->runtime_data['bind_having']);
        }

        if (isset($this->runtime_data['order'])) {
            $sql .= ' ORDER BY ' . $this->runtime_data['order'];
        }

        if (isset($this->runtime_data['limit'])) {
            $sql .= ' LIMIT ' . $this->runtime_data['limit'];
        }

        if (isset($this->runtime_data['lock'])) {
            $sql .= ' FOR ' . $this->runtime_data['lock'];
        }

        return $sql;
    }
}