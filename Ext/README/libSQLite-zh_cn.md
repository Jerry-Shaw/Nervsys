## libSQLite 描述

`libSQLite` 是一个 SQLite 数据库扩展，提供流畅的查询构建器接口、事务管理和结果处理。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libSQLite-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `table(string $table_name, bool $with_prefix = false): static`

为当前操作指定表（一次性使用）。

- **参数:**
    - `$table_name`: 表名。
    - `$with_prefix`: 是否自动添加表前缀（全局 `table_prefix`）。
- **返回:** 当前实例以支持链式调用。

### `setTable(string $table_name, bool $with_prefix = false): static`

为对象永久设置表名。后续操作将使用该表，除非被 `table()` 覆盖。

- **参数:** 同 `table()`。
- **返回:** 当前实例。

### `select(string ...$column): static`

开始一个 `SELECT` 查询，指定要查询的列。

- **参数:** 列名列表（默认 `*`）。
- **返回:** 当前实例。

### `insert(array $data): static`

开始一个 `INSERT` 查询。

- **参数:** 列‑值对的关联数组。
- **返回:** 当前实例。

### `update(array $data): static`

开始一个 `UPDATE` 查询。

- **参数:** 要更新的列‑值对的关联数组。
- **返回:** 当前实例。

### `delete(): static`

开始一个 `DELETE` 查询。

- **返回:** 当前实例。

### `where(array ...$conditions): static`

添加 `WHERE` 条件。每个条件是一个数组：`[字段, 操作符, 值]` 或 `[字段, 值]`（默认操作符为 `=`）。

- **参数:** 可变数量的条件数组。
- **返回:** 当前实例。

### `order(array $orders): static`

添加 `ORDER BY` 子句。数组键为列名，值为 `'ASC'`/`'DESC'` 或用于自定义排序的值列表。

- **参数:** 列 → 排序方向的关联数组。
- **返回:** 当前实例。

### `limit(int $offset, int $length = 0): static`

添加 `LIMIT` 子句。

- **参数:** `$offset`（起始行），`$length`（行数，0 表示不限制，即仅 `LIMIT offset`）。
- **返回:** 当前实例。

### `fetch(int $fetch_style = PDO::FETCH_ASSOC): array`

执行已构建的 `SELECT` 查询并返回第一行。

- **参数:** 获取样式（默认 `PDO::FETCH_ASSOC`）。
- **返回:** 第一行的关联数组，若无则返回空数组。

### `fetchAll(int $fetch_style = PDO::FETCH_ASSOC): array`

执行已构建的 `SELECT` 查询并返回所有行。

- **参数:** 获取样式。
- **返回:** 行数组（每个为关联数组）。

### `execute(): bool`

执行已构建的 `INSERT`、`UPDATE` 或 `DELETE` 查询。

- **返回:** 成功返回 `true`，失败返回 `false`。

### `begin(int $retry_times = 0): void`

开始事务。嵌套调用将被忽略（只有最外层真正开始）。

- **参数:** 重试次数（SQLite 忽略，仅为接口兼容）。
- **返回:** void。

### `commit(): void`

提交当前事务（若为最外层）。

### `rollback(): void`

回滚当前事务（若为最外层）。

### `getLastInsertId(string $name = ''): int`

返回最后插入的自增列 ID。

- **参数:** 序列名称（SQLite 忽略，仅为兼容）。
- **返回:** ID 整数值。

### `getAffectedRows(): int`

返回最后执行的 `INSERT`、`UPDATE` 或 `DELETE` 所影响的行数。

## 使用示例

```php
use Nervsys\Ext\libPDO;
use Nervsys\Ext\libSQLite;

// 建立 PDO 连接（SQLite 内存数据库）
$pdo = new libPDO('sqlite', ':memory:');
$pdo->connect();

// 创建 libSQLite 实例并绑定 PDO
$db = new libSQLite();
$db->bindLibPdo($pdo);

// 插入一条记录
$db->table('users')->insert(['name' => 'John', 'email' => 'john@example.com'])->execute();
$userId = $db->getLastInsertId();

// 带条件查询
$users = $db->table('users')
            ->select('id', 'name')
            ->where(['status', '=', 1])
            ->order(['name' => 'ASC'])
            ->limit(0, 10)
            ->fetchAll();

// 更新
$db->table('users')
   ->update(['email' => 'new@example.com'])
   ->where(['id', '=', $userId])
   ->execute();

// 删除
$db->table('users')
   ->delete()
   ->where(['id', '=', $userId])
   ->execute();

// 事务示例
$db->begin();
try {
    $db->table('accounts')->update(['balance' => 100])->where(['id' => 1])->execute();
    $db->table('accounts')->update(['balance' => 50])->where(['id' => 2])->execute();
    $db->commit();
} catch (\Exception $e) {
    $db->rollback();
}
```
