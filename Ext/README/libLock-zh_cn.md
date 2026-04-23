## libLock 描述

`libLock` 是一个分布式锁扩展，提供基于 Redis 的互斥锁定机制。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libLock-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `lock(string $key, int $timeout = 10): bool`

获取指定键的锁。

- **参数:**
    - `$key`: 锁标识符。
    - `$timeout`: 超时时间（秒），默认 10。
- **返回:** `bool` (成功为 true)。

### `unlock(string $key): bool`

释放指定键的锁。

- **参数:**
    - `$key`: 锁标识符。
- **返回:** `bool` (成功为 true)。

## 使用示例

```php
use Nervsys\Ext\libLock;

$lock = new libLock();

// 获取锁
if ($lock->lock('my_lock', 30)) {
    // 临界区
    echo "Processing...";
    
    // 释放锁
    $lock->unlock('my_lock');
} else {
    echo "Failed to acquire lock.";
}
```
