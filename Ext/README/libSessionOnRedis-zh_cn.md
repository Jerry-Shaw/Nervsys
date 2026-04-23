## libSessionOnRedis 描述

`libSessionOnRedis` 是一个会话处理器扩展，将 PHP 会话存储在 Redis 中以实现更好的可扩展性和跨服务器的共享状态。此类继承自
`Factory`。

**语言:** 中文 | [English Doc](./libSessionOnRedis-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `start(): bool`

启动会话。

- **返回:** `bool` (成功为 true)。

### `read(string $session_id): string|false`

从 Redis 读取会话数据。

- **参数:**
    - `$session_id`: 会话标识符。
- **返回:** 序列化的会话数据，未找到则为 false。

### `write(): bool`

将会话数据写入 Redis。

- **返回:** `bool` (成功为 true)。

### `destroy(string $session_id): bool`

销毁会话。

- **参数:**
    - `$session_id`: 会话标识符。
- **返回:** `bool` (成功为 true)。

## 使用示例

```php
use Nervsys\Ext\libSessionOnRedis;

$redisSession = new libSessionOnRedis();
$redisSession->start();

// 设置会话数据
$_SESSION['user_id'] = 123;
$_SESSION['username'] = 'john_doe';

// 获取会话数据
echo "User ID: {$_SESSION['user_id']}";

// 销毁会话
$redisSession->destroy(session_id());
```
