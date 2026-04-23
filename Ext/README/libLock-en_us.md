## libLock Description

`libLock` is a distributed lock extension that provides mutex locking mechanisms for Redis-based synchronization. It
extends `Factory`.

**Language:** English | [中文文档](./libLock-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `lock(string $key, int $timeout = 10): bool`

Acquires a lock with the specified key.

- **Parameters:**
    - `$key`: Lock identifier.
    - `$timeout`: Timeout in seconds (default: 10).
- **Returns:** `bool` (true if lock acquired).

### `unlock(string $key): bool`

Releases the lock for the specified key.

- **Parameters:**
    - `$key`: Lock identifier.
- **Returns:** `bool` (true if unlocked).

## Usage Example

```php
use Nervsys\Ext\libLock;

$lock = new libLock();

// Acquire lock
if ($lock->lock('my_lock', 30)) {
    // Critical section
    echo "Processing...";
    
    // Release lock
    $lock->unlock('my_lock');
} else {
    echo "Failed to acquire lock.";
}
```
