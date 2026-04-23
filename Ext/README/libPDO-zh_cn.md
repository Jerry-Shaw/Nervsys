## libPDO 描述

`libPDO` 是一个 PDO 数据库扩展，提供预处理语句、查询执行和结果处理的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libPDO-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `query(string $sql, array $params = []): bool|int|array|null`

使用参数执行预处理的 SQL 语句。

- **参数:**
    - `$sql`: 带占位符的 SQL 语句（如 `:id`）。
    - `$params`: 参数值的关联数组。
- **返回:**
    - 非 SELECT 查询为 `bool`。
    - 受影响行数为 `int`。
    - SELECT 查询的结果为 `array`。

### `insert(string $table, array $data): int`

使用预处理语句插入一行。

- **参数:**
    - `$table`: 表名。
    - `$data`: 列 - 值对的关联数组。
- **返回:** 插入的 ID，失败为 0。

### `update(string $table, array $data, string $where, array $params = []): int`

使用预处理语句更新行。

- **参数:**
    - `$table`: 表名。
    - `$data`: 要更新的列 - 值对的关联数组。
    - `$where`: 带占位符的 WHERE 子句。
    - `$params`: WHERE 子句的参数值。
- **返回:** 受影响的行数，失败为 0。

### `delete(string $table, string $where, array $params = []): int`

使用预处理语句删除行。

- **参数:**
    - `$table`: 表名。
    - `$where`: 带占位符的 WHERE 子句。
    - `$params`: WHERE 子句的参数值。
- **返回:** 已删除的行数，失败为 0。

### `select(string $table, array $columns = ['*'], string $where = '', array $params = [], int $limit = 0): array`

使用预处理语句选择行。

- **参数:**
    - `$table`: 表名。
    - `$columns`: 列名数组或 `['*']`。
    - `$where`: 带占位符的 WHERE 子句。
    - `$params`: WHERE 子句的参数值。
    - `$limit`: 返回的最大行数。
- **返回:** 结果行数组。

## 使用示例

```php
use Nervsys\Ext\libPDO;

$db = new libPDO();

// 预处理语句插入
$userId = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);

// 参数化选择
$users = $db->select('users', ['id', 'name'], "status = :status", ['status' => 1], 10);

// 参数化更新
$db->update('users', ['email' => 'new@example.com'], "id = :id", ['id' => $userId]);

// 参数化删除
$db->delete('users', "id = :id", ['id' => $userId]);
```
