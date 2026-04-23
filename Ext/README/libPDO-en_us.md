## libPDO Description

`libPDO` is a PDO database extension that provides methods for prepared statements, query execution, and result
handling. It extends `Factory`.

**Language:** English | [中文文档](./libPDO-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `query(string $sql, array $params = []): bool|int|array|null`

Executes a prepared SQL statement with parameters.

- **Parameters:**
    - `$sql`: SQL statement with placeholders (e.g., `:id`).
    - `$params`: Associative array of parameter values.
- **Returns:**
    - `bool` for non-select queries.
    - `int` for affected rows.
    - `array` of results for SELECT queries.

### `insert(string $table, array $data): int`

Inserts a row using prepared statement.

- **Parameters:**
    - `$table`: Table name.
    - `$data`: Associative array of column-value pairs.
- **Returns:** Inserted ID or 0 on failure.

### `update(string $table, array $data, string $where, array $params = []): int`

Updates rows using prepared statement.

- **Parameters:**
    - `$table`: Table name.
    - `$data`: Associative array of column-value pairs to update.
    - `$where`: WHERE clause with placeholders.
    - `$params`: Parameter values for WHERE clause.
- **Returns:** Number of affected rows or 0 on failure.

### `delete(string $table, string $where, array $params = []): int`

Deletes rows using prepared statement.

- **Parameters:**
    - `$table`: Table name.
    - `$where`: WHERE clause with placeholders.
    - `$params`: Parameter values for WHERE clause.
- **Returns:** Number of deleted rows or 0 on failure.

### `select(string $table, array $columns = ['*'], string $where = '', array $params = [], int $limit = 0): array`

Selects rows using prepared statement.

- **Parameters:**
    - `$table`: Table name.
    - `$columns`: Array of column names or `['*']`.
    - `$where`: WHERE clause with placeholders.
    - `$params`: Parameter values for WHERE clause.
    - `$limit`: Maximum number of rows to return.
- **Returns:** Array of result rows.

## Usage Example

```php
use Nervsys\Ext\libPDO;

$db = new libPDO();

// Insert with prepared statement
$userId = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);

// Select with parameters
$users = $db->select('users', ['id', 'name'], "status = :status", ['status' => 1], 10);

// Update with parameters
$db->update('users', ['email' => 'new@example.com'], "id = :id", ['id' => $userId]);

// Delete with parameters
$db->delete('users', "id = :id", ['id' => $userId]);
```
