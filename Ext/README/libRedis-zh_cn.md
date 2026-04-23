## libRedis 描述

`libRedis` 是一个 Redis 包装器扩展，提供简化常见 Redis 操作的方法，包括字符串、哈希、列表、集合和有序集操作。此类继承自
`Factory`。

**语言:** 中文 | [English Doc](./libRedis-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `connect(string $host = '127.0.0.1', int $port = 6379): bool`

连接到 Redis 服务器。

- **参数:**
    - `$host`: Redis 主机（默认：`'127.0.0.1'`）。
    - `$port`: Redis 端口（默认：6379）。
- **返回:** `bool` (成功为 true)。

### `set(string $key, string $value, int $expire = 0): bool`

设置键值对。

- **参数:**
    - `$key`: 键名。
    - `$value`: 要存储的值。
    - `$expire`: 过期时间（秒），默认 0（永不过期）。
- **返回:** `bool` (成功为 true)。

### `get(string $key): string|null`

通过键获取值。

- **参数:**
    - `$key`: 键名。
- **返回:** 字符串形式的值，未找到则为 null。

### `hSet(string $hash, string $field, string $value): bool`

在哈希中设置字段。

- **参数:**
    - `$hash`: 哈希键名。
    - `$field`: 字段名。
    - `$value`: 字段值。
- **返回:** `bool` (成功为 true)。

### `hGet(string $hash, string $field): string|null`

从哈希中获取字段。

- **参数:**
    - `$hash`: 哈希键名。
    - `$field`: 字段名。
- **返回:** 字段值，未找到则为 null。

### `lPush(string $key, array $values): int`

将值推送到列表左侧。

- **参数:**
    - `$key`: 列表键名。
    - `$values`: 要推送的值数组。
- **返回:** 列表的新长度。

### `rPop(string $key): string|null`

从列表右侧弹出值。

- **参数:**
    - `$key`: 列表键名。
- **返回:** 弹出的值，如果为空则为 null。

## 使用示例

```php
use Nervsys\Ext\libRedis;

$redis = new libRedis();
$redis->connect('127.0.0.1', 6379);

// 字符串操作
$redis->set('user:123:name', 'John Doe', 3600);
$name = $redis->get('user:123:name');

// 哈希操作
$redis->hSet('user:123', 'email', 'john@example.com');
$email = $redis->hGet('user:123', 'email');

// 列表操作
$redis->lPush('queue:tasks', ['task1', 'task2']);
$task = $redis->rPop('queue:tasks');
```
