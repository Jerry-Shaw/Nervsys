## libMySQL 描述

`libMySQL` 是一个 MySQL 数据库扩展，提供查询执行、事务管理和结果处理的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libMySQL-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `query(string $sql): bool|int|array|null`

执行 SQL 查询。

- **参数:**
    - `$sql`: 要执行的 SQL 语句。
- **返回:**
    - 非 SELECT 查询为 `bool` (成功为 true)。
    - UPDATE/DELETE 的受影响行数为 `int`。
    - SELECT 查询的结果数组为 `array`。
    - 无结果时为 `null`。

### `insert(string $table, array $data): int`

向表中插入一行。

- **参数:**
    - `$table`: 表名。
    - `$data`: 列 - 值对的关联数组。
- **返回:** 插入的 ID，失败为 0。

### `update(string $table, array $data, string $where): int`

更新表中的行。

- **参数:**
    - `$table`: 表名。
    - `$data`: 要更新的列 - 值对的关联数组。
    - `$where`: WHERE 子句（不含 'WHERE' 关键字）。
- **返回:** 受影响的行数，失败为 0。

### `delete(string $table, string $where): int`

从表中删除行。

- **参数:**
    - `$table`: 表名。
    - `$where`: WHERE 子句（不含 'WHERE' 关键字）。
- **返回:** 已删除的行数，失败为 0。

### `select(string $table, array $columns = ['*'], string $where = '', int $limit = 0): array`

从表中选择行。

- **参数:**
    - `$table`: 表名。
    - `$columns`: 列名数组或 `['*']`。
    - `$where`: WHERE 子句（不含 'WHERE' 关键字）。
    - `$limit`: 返回的最大行数。
- **返回:** 结果行数组。

### `beginTransaction(): bool`

开始数据库事务。

- **返回:** `bool` (成功为 true)。

### `commit(): bool`

提交当前事务。

- **返回:** `bool` (成功为 true)。

### `rollback(): bool`

回滚当前事务。

- **返回:** `bool` (成功为 true)。

## 使用示例

```php
use Nervsys\Ext\libMySQL;

$db = new libMySQL();

// 插入一行
$userId = $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);

// 选择用户
$users = $db->select('users', ['id', 'name'], "status = 1", 10);
foreach ($users as $user) {
    echo "User: {$user['name']}\n";
}

// 更新用户
$db->update('users', ['email' => 'new@example.com'], "id = {$userId}");

// 删除用户
$db->delete('users', "id = {$userId}");

// 事务示例
$db->beginTransaction();
try {
    $db->insert('orders', ['user_id' => $userId, 'amount' => 100]);
    $db->update('users', ['balance' => 50], "id = {$userId}");
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
}
```
