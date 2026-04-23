## libMySQL Description

`libMySQL` is a MySQL database extension that provides methods for query execution, transaction management, and result
handling. It extends `Factory`.

**Language:** English | [中文文档](./libMySQL-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `query(string $sql): bool|int|array|null`

Executes an SQL query.

- **Parameters:**
    - `$sql`: SQL statement to execute.
- **Returns:**
    - `bool` for non-select queries (true on success).
    - `int` for affected rows in UPDATE/DELETE.
    - `array` of results for SELECT queries.
    - `null` if no result.

### `insert(string $table, array $data): int`

Inserts a row into a table.

- **Parameters:**
    - `$table`: Table name.
    - `$data`: Associative array of column-value pairs.
- **Returns:** Inserted ID or 0 on failure.

### `update(string $table, array $data, string $where): int`

Updates rows in a table.

- **Parameters:**
    - `$table`: Table name.
    - `$data`: Associative array of column-value pairs to update.
    - `$where`: WHERE clause (without 'WHERE' keyword).
- **Returns:** Number of affected rows or 0 on failure.

### `delete(string $table, string $where): int`

Deletes rows from a table.

- **Parameters:**
    - `$table`: Table name.
    - `$where`: WHERE clause (without 'WHERE' keyword).
- **Returns:** Number of deleted rows or 0 on failure.

### `select(string $table, array $columns = ['*'], string $where = '', int $limit = 0): array`

Selects rows from a table.

- **Parameters:**
    - `$table`: Table name.
    - `$columns`: Array of column names or `['*']`.
    - `$where`: WHERE clause (without 'WHERE' keyword).
    - `$limit`: Maximum number of rows to return.
- **Returns:** Array of result rows.

### `beginTransaction(): bool`

Begins a database transaction.

- **Returns:** `bool` (true on success).

### `commit(): bool`

Commits the current transaction.

- **Returns:** `bool` (true on success).

### `rollback(): bool`

Rolls back the current transaction.

- **Returns:** `bool` (true on success).

## Usage Example

```php
use Nervsys\Ext\libMySQL;

$db = new libMySQL();

// Insert a row
$userId = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);

// Select users
$users = $db->select('users', ['id', 'name'], "status = 1", 10);
foreach ($users as $user) {
    echo "User: {$user['name']}\n";
}

// Update user
$db->update('users', ['email' => 'new@example.com'], "id = {$userId}");

// Delete user
$db->delete('users', "id = {$userId}");

// Transaction example
$db->beginTransaction();
try {
    $db->insert('orders', ['user_id' => $userId, 'amount' => 100]);
    $db->update('users', ['balance' => 50], "id = {$userId}");
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
}
```
