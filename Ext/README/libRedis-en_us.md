## libRedis Description

`libRedis` is a Redis wrapper extension that provides simplified methods for common Redis operations including string,
hash, list, set, and sorted set operations. It extends `Factory`.

**Language:** English | [中文文档](./libRedis-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `connect(string $host = '127.0.0.1', int $port = 6379): bool`

Connects to a Redis server.

- **Parameters:**
    - `$host`: Redis host (default: `'127.0.0.1'`).
    - `$port`: Redis port (default: 6379).
- **Returns:** `bool` (true on success).

### `set(string $key, string $value, int $expire = 0): bool`

Sets a key-value pair.

- **Parameters:**
    - `$key`: Key name.
    - `$value`: Value to store.
    - `$expire`: Expiration time in seconds (default: 0 for no expiration).
- **Returns:** `bool` (true on success).

### `get(string $key): string|null`

Gets a value by key.

- **Parameters:**
    - `$key`: Key name.
- **Returns:** Value as string or null if not found.

### `hSet(string $hash, string $field, string $value): bool`

Sets a field in a hash.

- **Parameters:**
    - `$hash`: Hash key name.
    - `$field`: Field name.
    - `$value`: Field value.
- **Returns:** `bool` (true on success).

### `hGet(string $hash, string $field): string|null`

Gets a field from a hash.

- **Parameters:**
    - `$hash`: Hash key name.
    - `$field`: Field name.
- **Returns:** Field value or null if not found.

### `lPush(string $key, array $values): int`

Pushes values to the left of a list.

- **Parameters:**
    - `$key`: List key name.
    - `$values`: Array of values to push.
- **Returns:** New length of list.

### `rPop(string $key): string|null`

Pops a value from the right of a list.

- **Parameters:**
    - `$key`: List key name.
- **Returns:** Popped value or null if empty.

## Usage Example

```php
use Nervsys\Ext\libRedis;

$redis = new libRedis();
$redis->connect('127.0.0.1', 6379);

// String operations
$redis->set('user:123:name', 'John Doe', 3600);
$name = $redis->get('user:123:name');

// Hash operations
$redis->hSet('user:123', 'email', 'john@example.com');
$email = $redis->hGet('user:123', 'email');

// List operations
$redis->lPush('queue:tasks', ['task1', 'task2']);
$task = $redis->rPop('queue:tasks');
```
