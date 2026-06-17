## libSQLite Description

`libSQLite` is a SQLite database extension that provides a fluent query builder interface, transaction management, and
result handling. It extends `Factory`.

**Language:** English | [中文文档](./libSQLite-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `table(string $table_name, bool $with_prefix = false): static`

Specifies a table for the current operation (one‑time use).

- **Parameters:**
    - `$table_name`: Table name.
    - `$with_prefix`: Whether to prepend the table prefix (global `table_prefix`).
- **Returns:** The current instance for chaining.

### `setTable(string $table_name, bool $with_prefix = false): static`

Sets a permanent table name for the object. Subsequent operations will use this table unless overridden by `table()`.

- **Parameters:** Same as `table()`.
- **Returns:** The current instance.

### `select(string ...$column): static`

Begins a `SELECT` query with specified columns.

- **Parameters:** List of column names (defaults to `*` if none given).
- **Returns:** The current instance.

### `insert(array $data): static`

Begins an `INSERT` query.

- **Parameters:** Associative array of column‑value pairs.
- **Returns:** The current instance.

### `update(array $data): static`

Begins an `UPDATE` query.

- **Parameters:** Associative array of column‑value pairs to update.
- **Returns:** The current instance.

### `delete(): static`

Begins a `DELETE` query.

- **Returns:** The current instance.

### `where(array ...$conditions): static`

Adds `WHERE` conditions. Each condition is an array: `[field, operator, value]` or `[field, value]` (defaults to `=`).

- **Parameters:** Variable number of condition arrays.
- **Returns:** The current instance.

### `order(array $orders): static`

Adds `ORDER BY` clause. The array keys are column names, values can be `'ASC'`/`'DESC'` or a list of values for custom
ordering.

- **Parameters:** Associative array of column → order/direction.
- **Returns:** The current instance.

### `limit(int $offset, int $length = 0): static`

Adds a `LIMIT` clause.

- **Parameters:** `$offset` (starting row), `$length` (number of rows; `0` means no limit, i.e. only `LIMIT offset`).
- **Returns:** The current instance.

### `fetch(int $fetch_style = PDO::FETCH_ASSOC): array`

Executes the built `SELECT` query and returns the first row.

- **Parameters:** Fetch style (default `PDO::FETCH_ASSOC`).
- **Returns:** Associative array of the first row, or an empty array if none.

### `fetchAll(int $fetch_style = PDO::FETCH_ASSOC): array`

Executes the built `SELECT` query and returns all rows.

- **Parameters:** Fetch style.
- **Returns:** Array of rows (each as associative array).

### `execute(): bool`

Executes an `INSERT`, `UPDATE`, or `DELETE` query that was built.

- **Returns:** `true` on success, `false` on failure.

### `begin(int $retry_times = 0): void`

Begins a transaction. Nested calls are ignored (only the first begins).

- **Parameters:** Retry attempts (ignored for SQLite, kept for interface compatibility).
- **Returns:** void.

### `commit(): void`

Commits the current transaction (if it is the outermost level).

### `rollback(): void`

Rolls back the current transaction (if it is the outermost level).

### `getLastInsertId(string $name = ''): int`

Returns the last inserted ID from an auto‑increment column.

- **Parameters:** Sequence name (ignored for SQLite, kept for compatibility).
- **Returns:** The ID as integer.

### `getAffectedRows(): int`

Returns the number of rows affected by the last executed `INSERT`, `UPDATE`, or `DELETE`.

## Usage Example

```php
use Nervsys\Ext\libPDO;
use Nervsys\Ext\libSQLite;

// Setup PDO connection (SQLite in-memory)
$pdo = new libPDO('sqlite', ':memory:');
$pdo->connect();

// Create libSQLite instance and bind PDO
$db = new libSQLite();
$db->bindLibPdo($pdo);

// Insert a record
$db->table('users')->insert(['name' => 'John', 'email' => 'john@example.com'])->execute();
$userId = $db->getLastInsertId();

// Select with conditions
$users = $db->table('users')
            ->select('id', 'name')
            ->where(['status', '=', 1])
            ->order(['name' => 'ASC'])
            ->limit(0, 10)
            ->fetchAll();

// Update
$db->table('users')
   ->update(['email' => 'new@example.com'])
   ->where(['id', '=', $userId])
   ->execute();

// Delete
$db->table('users')
   ->delete()
   ->where(['id', '=', $userId])
   ->execute();

// Transaction
$db->begin();
try {
    $db->table('accounts')->update(['balance' => 100])->where(['id' => 1])->execute();
    $db->table('accounts')->update(['balance' => 50])->where(['id' => 2])->execute();
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
}
```
