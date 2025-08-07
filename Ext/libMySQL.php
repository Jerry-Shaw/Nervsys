<?php

/**
 * MySQL Extension
 *
 * Copyright 2019-2025 秋水之冰 <27206617@qq.com>
 * Copyright 2021-2024 wwj <904428723@qq.com>
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

namespace Nervsys\Ext;

use Nervsys\Core\Factory;

class libMySQL extends Factory
{
    public \PDO          $pdo;
    public libPDO        $libPDO;
    public \PDOStatement $PDOStatement;

    public bool $force_execute = false;

    public int $retry_limit   = 3;
    public int $affected_rows = 0;

    public string $last_sql     = '';
    public string $table_prefix = '';

    public string|null $table = null;

    public array $runtime_data = [];
    public array $select_count = [];

    public static int $transactions = 0;

    /**
     * Bind libPDO object
     *
     * @param libPDO $libPDO
     *
     * @return $this
     * @throws \ReflectionException
     */
    public function bindLibPdo(libPDO $libPDO): self
    {
        $this->libPDO = $libPDO->pdo instanceof \PDO ? $libPDO : $libPDO->connect();
        $this->pdo    = $this->libPDO->pdo;

        unset($libPDO);
        return $this;
    }

    /**
     * Force executing SQL without WHERE condition
     *
     * @return $this
     */
    public function force(): self
    {
        $this->force_execute = true;
        return $this;
    }

    /**
     * Use SQL clause directly
     *
     * @param string $sql
     *
     * @return string
     */
    public function useSql(string $sql): string
    {
        $this->runtime_data['raw'] ??= hash('crc32b', uniqid(mt_rand(), true)) . ':';

        return $this->runtime_data['raw'] . $sql;
    }

    /**
     * Set string value as raw SQL part
     *
     * @param string $value
     *
     * @return string
     */
    public function asRaw(string $value): string
    {
        return $this->useSql($value);
    }

    /**
     * Clear runtime data
     *
     * @return void
     */
    public function clearRuntime(): void
    {
        //Reset runtime data
        $this->runtime_data  = [];
        $this->force_execute = false;
    }

    /**
     * Clear unfinished transaction
     *
     * @return void
     */
    public function clearTransaction(): void
    {
        //Rollback unfinished transaction
        if ($this->pdo->inTransaction()) {
            self::$transactions = 0;
            $this->pdo->rollBack();
        }
    }

    /**
     * Set auto cleanup on shutdown
     *
     * @return $this
     */
    public function autoCleanup(): self
    {
        register_shutdown_function([$this, 'clearRuntime']);
        register_shutdown_function([$this, 'clearTransaction']);

        return $this;
    }

    /**
     * Auto reconnect with limited times
     *
     * @param int $retry_times
     *
     * @return $this
     */
    public function autoReconnect(int $retry_times): self
    {
        $this->retry_limit = $retry_times;

        unset($retry_times);
        return $this;
    }

    /**
     * Force table name with higher priority
     *
     * @param string $table_name
     * @param bool   $with_prefix
     *
     * @return $this
     */
    public function forceTableName(string $table_name, bool $with_prefix = false): self
    {
        if ($with_prefix && !str_starts_with($table_name, $this->table_prefix)) {
            $table_name = $this->table_prefix . $table_name;
        }

        $this->table = $table_name;

        unset($table_name, $with_prefix);
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
        $this->table_prefix = $table_prefix;

        unset($table_prefix);
        return $this;
    }

    /**
     * Get table name from defined value or called class
     *
     * @param bool $with_alias
     *
     * @return string
     */
    public function getTableName(bool $with_alias = false): string
    {
        if (is_null($this->table)) {
            $table_name = get_class($this);
            $table_pos  = strrpos($table_name, '\\');

            if (false !== $table_pos) {
                $table_name = substr($table_name, $table_pos + 1);
            }

            $table_name = $this->table_prefix . $table_name;
            unset($table_pos);
        } else {
            $table_name = $with_alias
                ? $this->table
                : substr($this->table, 0, strpos($this->table, ' ') ?: strlen($this->table));
        }

        unset($with_alias);
        return $table_name;
    }

    /**
     * Fetch one row
     *
     * @param int $fetch_style
     *
     * @return array
     * @throws \ReflectionException
     */
    public function fetch(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $exec = $this->executeSQL($this->buildSQL(), $this->runtime_data['bind'] ?? []);
        $data = $exec ? $this->PDOStatement->fetch($fetch_style) : [];

        if (!is_array($data)) {
            $data = false !== $data ? [$data] : [];
        }

        unset($fetch_style, $exec);
        return $data;
    }

    /**
     * Fetch all rows
     *
     * @param int $fetch_style
     *
     * @return array
     * @throws \ReflectionException
     */
    public function fetchAll(int $fetch_style = \PDO::FETCH_ASSOC): array
    {
        $exec = $this->executeSQL($this->buildSQL(), $this->runtime_data['bind'] ?? []);
        $data = $exec ? $this->PDOStatement->fetchAll($fetch_style) : [];

        unset($fetch_style, $exec);
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
     * Get the number of found rows from the last select SQL
     *
     * @return int
     * @throws \ReflectionException
     */
    public function getLastFoundRows(): int
    {
        if (empty($this->select_count)) {
            return 0;
        }

        $exec  = $this->executeSQL($this->select_count['sql'], $this->select_count['bind'] ?? []);
        $count = $exec ? (int)($this->PDOStatement->fetch(\PDO::FETCH_ASSOC)['C'] ?? 0) : -1;

        unset($exec);
        return $count;
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
     * @param array $data
     *
     * @return $this
     */
    public function insert(array $data): self
    {
        $this->isReady();

        $this->runtime_data['action'] = 'insert';
        $this->runtime_data['cols']   = array_keys($data);
        $this->runtime_data['bind']   = array_values($data);

        unset($data);
        return $this;
    }

    /**
     * Update action
     *
     * @param array $data
     *
     * @return $this
     */
    public function update(array $data): self
    {
        $this->isReady();

        $this->runtime_data['action'] = 'update';
        $this->runtime_data['value']  = $data;

        unset($data);
        return $this;
    }

    /**
     * Replace action
     *
     * @param array $data
     * @param bool  $use_set
     *
     * @return $this
     */
    public function replace(array $data, bool $use_set = false): self
    {
        $this->isReady();

        if (!$use_set) {
            $this->runtime_data['action'] = 'replace';
            $this->runtime_data['cols']   = array_keys($data);
            $this->runtime_data['bind']   = array_values($data);
        } else {
            $this->runtime_data['action'] = 'replaceSet';
            $this->runtime_data['value']  = $data;
        }

        unset($data, $use_set);
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

        if (empty($column)) {
            $column = ['*'];
        }

        foreach ($column as $key => $item) {
            $column[$key] = $this->getRawSQL($item) ?? $item;
        }

        $this->runtime_data['action'] = 'select';
        $this->runtime_data['cols']   = implode(',', $column);

        unset($column, $key, $item);
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
     * Alias function of "forceTableName"
     *
     * @param string $table
     * @param bool   $with_prefix
     *
     * @return $this
     */
    public function to(string $table, bool $with_prefix = false): self
    {
        $this->forceTableName($table, $with_prefix);

        unset($table, $with_prefix);
        return $this;
    }

    /**
     * Alias function of "forceTableName"
     *
     * @param string $table
     * @param bool   $with_prefix
     *
     * @return $this
     */
    public function from(string $table, bool $with_prefix = false): self
    {
        $this->forceTableName($table, $with_prefix);

        unset($table, $with_prefix);
        return $this;
    }

    /**
     * Set join
     *
     * @param string $table
     * @param string $type
     * @param bool   $with_prefix
     *
     * @return $this
     */
    public function join(string $table, string $type = 'INNER', bool $with_prefix = true): self
    {
        if ($with_prefix) {
            $table = $this->table_prefix . $table;
        }

        $this->runtime_data['on']     = [];
        $this->runtime_data['join']   ??= [];
        $this->runtime_data['stage']  = 'join';
        $this->runtime_data['join'][] = $type . ' JOIN ' . $table;

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

        $this->runtime_data['on']   = $this->parseCond($where, 'on');
        $this->runtime_data['join'] = array_merge($this->runtime_data['join'], $this->runtime_data['on']);

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

        $where = array_filter($where);

        if (empty($where)) {
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

        $where = array_filter($where);

        if (empty($where)) {
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
        $where = array_filter($where);

        if (empty($where)) {
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
        $where = array_filter($where);

        if (empty($where)) {
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
        foreach ($fields as $key => $field) {
            $fields[$key] = $this->getRawSQL($field) ?? $field;
        }

        $this->runtime_data['group'] = implode(',', $fields);

        unset($fields, $key, $field);
        return $this;
    }

    /**
     * Set SQL orders
     *
     * @param array $orders
     *
     * @return $this
     */
    public function order(array $orders): self
    {
        $order = [];

        foreach ($orders as $col => $val) {
            $sql = $this->getRawSQL($val);

            if (is_string($sql)) {
                $order[] = $sql;
                continue;
            }

            $col = $this->getRawSQL($col) ?? $col;

            if (is_string($val) && in_array(strtoupper($val), ['ASC', 'DESC'], true)) {
                //By column
                $order[] = $col . ' ' . strtoupper($val);
            } else {
                //By FIELD()
                if (is_string($val)) {
                    $order[] = 'FIELD(' . $col . ', ' . $val . ')';
                } elseif (!empty($val)) {
                    $last_val = strtoupper(end($val));

                    if (!in_array($last_val, ['ASC', 'DESC'], true)) {
                        $order[] = 'FIELD(' . $col . ', ' . implode(',', $val) . ')';
                    } else {
                        array_pop($val);
                        $order[] = 'FIELD(' . $col . ', ' . implode(',', $val) . ') ' . $last_val;
                    }
                }
            }
        }

        $this->runtime_data['order'] = implode(',', $order);

        unset($orders, $order, $col, $val, $sql, $last_val);
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
     * @param int    $retry_times
     *
     * @return int
     * @throws \ReflectionException
     */
    public function exec(string $sql, int $retry_times = 0): int
    {
        try {
            $this->last_sql = $sql;

            if (false === $this->affected_rows = $this->pdo->exec($sql)) {
                $this->affected_rows = -1;
            }
        } catch (\PDOException $exception) {
            if ($this->reconnect(++$retry_times)) {
                unset($exception);
                return $this->exec($sql, $retry_times);
            }

            throw new \PDOException($exception->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $sql, E_USER_ERROR);
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $sql, E_USER_ERROR);
        }

        unset($sql, $retry_times);
        return $this->affected_rows;
    }

    /**
     * Query SQL and return PDOStatement
     *
     * @param string $sql
     * @param int    $fetch_style
     * @param int    $col_no
     * @param int    $retry_times
     *
     * @return \PDOStatement
     * @throws \ReflectionException
     */
    public function query(string $sql, int $fetch_style = \PDO::FETCH_ASSOC, int $col_no = 0, int $retry_times = 0): \PDOStatement
    {
        try {
            $this->last_sql = $sql;
            $sql_param      = [$sql, $fetch_style];

            if ($fetch_style === \PDO::FETCH_COLUMN) {
                $sql_param[] = $col_no;
            }

            $pdo_statement       = $this->pdo->query(...$sql_param);
            $this->affected_rows = $pdo_statement->rowCount();

            unset($sql_param);
        } catch (\PDOException $exception) {
            if ($this->reconnect(++$retry_times)) {
                unset($exception);
                return $this->query($sql, $fetch_style, $col_no, $retry_times);
            }

            throw new \PDOException($exception->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $sql, E_USER_ERROR);
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $sql, E_USER_ERROR);
        }

        unset($sql, $fetch_style, $col_no, $retry_times);
        return $pdo_statement;
    }

    /**
     * Execute SQL statement
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function execute(): bool
    {
        return $this->executeSQL($this->buildSQL(), $this->runtime_data['bind'] ?? []);
    }

    /**
     * Begin transaction
     *
     * @param int $retry_times
     *
     * @return void
     * @throws \ReflectionException
     */
    public function begin(int $retry_times = 0): void
    {
        if (0 < self::$transactions) {
            unset($retry_times);
            return;
        }

        try {
            $this->pdo->beginTransaction();
            ++self::$transactions;
            unset($retry_times);
        } catch (\PDOException $exception) {
            if ($this->reconnect(++$retry_times)) {
                $this->begin($retry_times);
                unset($exception);
                return;
            }

            throw new \PDOException($exception->getMessage() . '. ' . 'Start transaction failed!', E_USER_ERROR);
        } catch (\Throwable $throwable) {
            throw new \PDOException($throwable->getMessage() . '. ' . 'Start transaction failed!', E_USER_ERROR);
        }
    }

    /**
     * Commit transaction
     *
     * @return void
     */
    public function commit(): void
    {
        --self::$transactions;

        if (0 === self::$transactions) {
            $this->pdo->commit();
        }
    }

    /**
     * Rollback transaction
     *
     * @return void
     */
    public function rollback(): void
    {
        --self::$transactions;

        if (0 === self::$transactions) {
            $this->pdo->rollBack();
        }
    }

    /**
     * Build runtime SQL
     *
     * @return string
     */
    public function buildSQL(): string
    {
        return $this->{'build' . ucfirst($this->runtime_data['action'])}();
    }

    /**
     * Explain SQL
     *
     * @param string $readable_sql
     *
     * @return array [NULL, system, const, eq_ref, ref, range, index, ALL]
     * @throws \ReflectionException
     */
    public function explainSQL(string $readable_sql): array
    {
        return $this->query('EXPLAIN ' . $readable_sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Reconnect MySQL server
     *
     * @param int $retry_times
     *
     * @return bool
     * @throws \ReflectionException
     */
    protected function reconnect(int $retry_times = 0): bool
    {
        if (!in_array($this->pdo->errorInfo()[1] ?? 0, [2006, 2013], true)) {
            return false;
        }

        if (0 < $this->retry_limit && $retry_times > $this->retry_limit) {
            return false;
        }

        //Reconnect to PDO driver
        $this->libPDO = $this->libPDO->connect();
        $this->pdo    = $this->libPDO->pdo;

        unset($retry_times);
        return true;
    }

    /**
     * @param string $field
     *
     * @return string
     */
    protected function escapeField(string $field): string
    {
        if (str_contains($field, '`') || str_contains($field, '(') || str_contains($field, ')')) {
            return $field;
        }

        $field = trim($field, '`');

        if (!str_contains($field, '.')) {
            return '`' . $field . '`';
        }

        $fields = explode('.', $field, 2);
        $field  = $fields[0] . '.`' . $fields[1] . '`';

        unset($fields);
        return $field;
    }

    /**
     * Execute prepared SQL
     *
     * @param string $runtime_sql
     * @param array  $runtime_params
     * @param int    $retry_times
     *
     * @return bool
     * @throws \ReflectionException
     */
    protected function executeSQL(string $runtime_sql, array $runtime_params = [], int $retry_times = 0): bool
    {
        try {
            $this->last_sql     = $this->getReadableSql($runtime_sql, $runtime_params);
            $this->PDOStatement = $this->pdo->prepare($runtime_sql);

            $result = $this->PDOStatement->execute($runtime_params);

            $this->affected_rows = $this->PDOStatement->rowCount();
            $this->clearRuntime();
        } catch (\PDOException $exception) {
            if ($this->reconnect(++$retry_times)) {
                unset($exception);
                return $this->executeSQL($runtime_sql, $runtime_params, $retry_times);
            }

            $this->clearRuntime();
            throw new \PDOException($exception->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        } catch (\Throwable $throwable) {
            $this->clearRuntime();
            throw new \PDOException($throwable->getMessage() . '. ' . PHP_EOL . 'SQL: ' . $this->last_sql, E_USER_ERROR);
        }

        unset($runtime_sql, $runtime_params, $retry_times);
        return $result;
    }

    /**
     * Build SQL for INSERT
     *
     * @return string
     */
    protected function buildInsert(): string
    {
        $sql = 'INSERT INTO ' . $this->getTableName(true);
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
        $sql = 'SELECT ' . $this->runtime_data['cols'];
        $sql .= ' FROM ' . $this->getTableName(true);

        return $this->appendCond($sql);
    }

    /**
     * Build SQL for UPDATE
     *
     * @return string
     */
    protected function buildUpdate(): string
    {
        $this->isSafe();

        return $this->appendCond('UPDATE ' . $this->getTableName(true));
    }

    /**
     * Build SQL for REPLACE INTO
     *
     * @return string
     */
    protected function buildReplace(): string
    {
        $sql = 'REPLACE INTO ' . $this->getTableName(true);
        $sql .= ' (' . implode(',', $this->runtime_data['cols']) . ')';
        $sql .= ' VALUES (' . implode(',', array_pad([], count($this->runtime_data['bind']), '?')) . ')';

        return $sql;
    }

    /**
     * Build SQL for REPLACE INTO ... SET
     *
     * @return string
     */
    protected function buildReplaceSet(): string
    {
        return 'REPLACE INTO ' . $this->getTableName(true) . ' ' . $this->getSqlSet();
    }

    /**
     * Build SQL for DELETE
     *
     * @return string
     */
    protected function buildDelete(): string
    {
        $this->isSafe();

        return $this->appendCond('DELETE FROM ' . $this->getTableName(true));
    }

    /**
     * Check where clause, avoid mistakes in UPDATE and DELETE
     *
     * @return void
     */
    protected function isSafe(): void
    {
        if ($this->force_execute) {
            $this->force_execute = false;
            return;
        }

        if (!isset($this->runtime_data['where']) || empty($this->runtime_data['where'])) {
            $this->force_execute = true;

            throw new \PDOException('WARNING: WHERE clause is missing in SQL: ' . $this->getReadableSql($this->buildSQL(), $this->runtime_data['bind'] ?? []) . '. Using force() to bypass security checking.', E_USER_ERROR);
        }
    }

    /**
     * Check statement ready
     */
    protected function isReady(): void
    {
        if (isset($this->runtime_data['action'])) {
            throw new \PDOException(
                $this->getReadableSql($this->buildSQL(), $this->runtime_data['bind'] ?? []) . ' NOT execute!',
                E_USER_ERROR
            );
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
                if (in_array($item = strtoupper($value[0]), ['NOT', '!', 'AND', '&&', 'OR', '||', 'XOR'], true)) {
                    $cond_list[] = $item;
                    array_shift($value);
                } elseif (1 < count($cond_list)) {
                    $cond_list[] = 'AND';
                }
            } else {
                $item = (string)current($value);
                $item = $this->getRawSQL($item);

                if (is_string($item)) {
                    $cond_list[] = $item;
                }

                continue;
            }

            //Field
            $cond_list[] = $this->escapeField(array_shift($value));

            //Operator
            if (2 <= count($value)) {
                $item = strtoupper(array_shift($value));

                if (!in_array($item, ['+', '-', '*', '/', '%', '=', '<=>', '<', '>', '<=', '>=', '<>', '!=', '|', '&', '^', '~', '<<', '>>', 'MOD', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN'], true)) {
                    throw new \PDOException('Invalid operator: "' . $item . '"!', E_USER_ERROR);
                }

                $cond_list[] = $item;
            } elseif (!is_array($value[0])) {
                if (!in_array($item = strtoupper($value[0]), ['IS NULL', 'IS NOT NULL'], true)) {
                    $cond_list[] = '=';
                } else {
                    $cond_list[] = $item;
                    continue;
                }
            } else {
                $cond_list[] = 'IN';
            }

            //Avoid errors when data is missing
            if (empty($value)) {
                continue;
            }

            //Data
            if (!is_array($value[0])) {
                if ('join' !== $this->runtime_data['stage']) {
                    $idx = 0;

                    while (null !== ($data = array_shift($value))) {
                        if (0 === ($idx & 1)) {
                            if (!str_starts_with($data, '`') || !str_ends_with($data, '`')) {
                                $cond_list[] = '?';

                                $this->runtime_data[$bind_stage][] = $data;
                            } else {
                                $cond_list[] = $this->escapeField($data);
                            }
                        } else {
                            if (!in_array($data, ['+', '-', '*', '/', '%', '=', '<=>', '<', '>', '<=', '>=', '<>', '!=', '|', '&', '^', '~', '<<', '>>', 'MOD'], true)) {
                                throw new \PDOException('Invalid operator: "' . $data . '"!', E_USER_ERROR);
                            }

                            $cond_list[] = $data;
                        }

                        ++$idx;
                    }

                    unset($idx);
                } else {
                    $cond_list[] = array_shift($value);
                }
            } else {
                $data = array_values((array)array_shift($value));

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
        $clause   = '';
        $group_by = false;

        if (isset($this->runtime_data['join'])) {
            $clause .= ' ' . implode(' ', $this->runtime_data['join']);
        }

        if (isset($this->runtime_data['value'])) {
            $clause .= ' ' . $this->getSqlSet();
        }

        if (isset($this->runtime_data['where'])) {
            $this->runtime_data['bind'] ??= [];
            $this->runtime_data['bind'] = array_merge($this->runtime_data['bind'], $this->runtime_data['bind_where'] ?? []);

            $clause .= ' ' . implode(' ', $this->runtime_data['where']);
        }

        if (isset($this->runtime_data['group'])) {
            $clause   .= ' GROUP BY ' . $this->runtime_data['group'];
            $group_by = true;
        }

        if (isset($this->runtime_data['having'])) {
            $this->runtime_data['bind'] ??= [];
            $this->runtime_data['bind'] = array_merge($this->runtime_data['bind'], $this->runtime_data['bind_having'] ?? []);

            $clause .= ' ' . implode(' ', $this->runtime_data['having']);
        }

        if ('select' === $this->runtime_data['action']) {
            $this->select_count['sql'] = 'SELECT COUNT(*) AS C FROM ' . $this->getTableName(true) . $clause;

            if ($group_by) {
                $this->select_count['sql'] = 'SELECT COUNT(*) AS C FROM (' . $this->select_count['sql'] . ') AS source';
            }

            $this->select_count['bind'] = $this->runtime_data['bind'] ?? [];
        }

        if (isset($this->runtime_data['order'])) {
            $clause .= ' ORDER BY ' . $this->runtime_data['order'];
        }

        if (isset($this->runtime_data['limit'])) {
            $clause .= ' LIMIT ' . $this->runtime_data['limit'];
        }

        if (isset($this->runtime_data['lock'])) {
            $clause .= ' FOR ' . $this->runtime_data['lock'];
        }

        $sql .= $clause;

        unset($clause, $group_by);
        return $sql;
    }

    /**
     * @return string
     */
    private function getSqlSet(): string
    {
        $data = [];

        foreach ($this->runtime_data['value'] as $col => $val) {
            if (str_starts_with($val, $col)) {
                $raw = str_replace(' ', '', $val);
                $raw = substr($raw, strlen($col));

                foreach (['+', '-', '*', '/', '|', '&', '^', '~', '<<', '>>'] as $opt) {
                    if (!str_starts_with($raw, $opt)) {
                        continue;
                    }

                    $raw = substr($raw, strlen($opt));

                    if (!is_numeric($raw)) {
                        break;
                    }

                    $data[] = $col . '=' . $col . $opt . (string)(!str_contains($raw, '.') ? (int)$raw : (float)$raw);
                    continue 2;
                }

                unset($opt);
            }

            $raw = $this->getRawSQL($val);

            if (is_string($raw)) {
                $data[] = $col . '=' . $raw;
                continue;
            }

            $data[] = $col . '=?';

            $this->runtime_data['bind'][] = $val;
        }

        $sql = 'SET ' . implode(',', $data);

        unset($data, $col, $val, $raw);
        return $sql;
    }

    /**
     * Check raw SQL
     *
     * @param string $value
     *
     * @return string|null
     */
    protected function getRawSQL(string $value): null|string
    {
        if (!isset($this->runtime_data['raw'])) {
            return null;
        }

        if (!str_starts_with($value, $this->runtime_data['raw'])) {
            return null;
        }

        return substr($value, strlen($this->runtime_data['raw']));
    }

    /**
     * Build readable SQL with params
     *
     * @param string $runtime_sql
     * @param array  $bind_params
     *
     * @return string
     */
    protected function getReadableSql(string $runtime_sql, array $bind_params): string
    {
        $bind_params = array_map(
            function (int|float|string|null $value): int|float|string|null
            {
                if (is_string($value)) {
                    $value = $this->getRawSQL($value) ?? $value;

                    if (!is_numeric($value)) {
                        $value = '"' . addslashes($value) . '"';
                    }
                }

                return $value;
            },
            $bind_params
        );

        $runtime_sql = str_replace('?', '%s', $runtime_sql);
        $runtime_sql = sprintf($runtime_sql, ...$bind_params);

        unset($bind_params);
        return $runtime_sql;
    }
}