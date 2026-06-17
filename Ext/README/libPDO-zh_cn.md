## libPDO 描述

`libPDO` 是一个 PDO 连接器类，负责构建 DSN、设置连接选项并建立 PDO 实例。它继承自 `Factory`。

**语言:** 中文 | [English Doc](./libPDO-en_us.md)

## 命名空间

`Nervsys\Ext`

## 公共属性

### `public \PDO|null $pdo`

底层的 PDO 实例。在调用 `connect()` 之前为 `null`。

## 构造函数

###
`__construct(string $type = 'mysql', string $host = '127.0.0.1', int $port = 3306, string $user = 'root', string $pwd = '', string $db = '', int $timeout = 10, bool $persist = true, string $charset = 'utf8mb4')`

创建新的 `libPDO` 实例，构建 DSN 和选项，但**不会立即连接**。

- **参数:**
    - `$type`: 数据库类型 – `mysql`, `mssql`, `pgsql`, `oci` 或 `sqlite`。
    - `$host`: 主机名/IP（SQLite 中为文件路径或 `':memory:'`）。
    - `$port`: 端口号（SQLite 不使用）。
    - `$user`: 用户名（SQLite 不使用）。
    - `$pwd`: 密码（SQLite 不使用）。
    - `$db`: 数据库名（SQLite 不使用）。
    - `$timeout`: 连接超时秒数（SQLite 中会转换为毫秒作为忙超时）。
    - `$persist`: 启用持久连接。
    - `$charset`: 字符集（SQLite 中用于 `PRAGMA encoding`；PostgreSQL 不使用）。

## 方法

### `connect(): static`

使用先前构建的 DSN 和选项创建实际的 PDO 连接。对于 SQLite，还会启用外键约束（`PRAGMA foreign_keys = ON`）。

- **返回:** 当前实例（流畅接口）。

### 使用示例

```php
use Nervsys\Ext\libPDO;

// MySQL
$pdo = new libPDO('mysql', 'localhost', 3306, 'root', 'password', 'my_db');
$pdo->connect();
$dbh = $pdo->pdo; // 此时获得 PDO 对象

// SQLite（内存数据库）
$pdo = new libPDO('sqlite', ':memory:');
$pdo->connect();
// 使用 $pdo->pdo 进行原生 PDO 操作，
// 或者将其传递给 libSQLite/libMySQL 以使用流畅查询构建器。
```
