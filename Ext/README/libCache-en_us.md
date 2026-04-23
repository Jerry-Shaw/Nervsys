## libCache Description

`libCache` is a cache extension built on Redis. It provides simple key-value caching operations including set, get, and
delete. This class extends `Factory`.

**Language:** English | [中文文档](./libCache-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Constants

### `PREFIX`

The prefix used for all cache keys (`CAS:`).

## Properties

### `$redis`

- **Type:** `\Redis|libRedis`
- **Description:** Redis instance or libRedis object used for storage.

## Methods

### `bindRedis(\Redis|libRedis $redis): static`

Binds a Redis instance to the class.

- **Parameters:**
    - `$redis`: An instance of `\Redis` or `libRedis`.
- **Returns:** `$this`.

### `set(string $key, array $data, int $life = 600): bool`

Sets cache data with a key.

- **Parameters:**
    - `$key`: Cache key (without prefix).
    - `$data`: Data to store as an array.
    - `$life`: Expiration time in seconds (default: 600). Set to ≤0 for no expiration.
- **Returns:** `bool` (true on success, false on failure).
- **Throws:** `\RedisException` on Redis errors.

### `get(string $key): array`

Retrieves cached data by key.

- **Parameters:**
    - `$key`: Cache key (without prefix).
- **Returns:** Array of cached data, or empty array if not found/invalid.

### `del(string $key): int`

Deletes cache by key.

- **Parameters:**
    - `$key`: Cache key (without prefix).
- **Returns:** Number of deleted items (usually 1).
- **Throws:** `\RedisException` on Redis errors.

## Usage Example

```php
use Nervsys\Ext\libCache;

$cache = new libCache();
$cache->bindRedis($redis); // Use Redis or libRedis instance

// Set cache
$success = $cache->set('user:123', ['name' => 'John', 'email' => 'john@example.com'], 3600);

// Get cache
$data = $cache->get('user:123');
if (!empty($data)) {
    echo "User name: {$data['name']}";
}

// Delete cache
$deleted = $cache->del('user:123');
echo "Deleted: " . ($deleted ? 'yes' : 'no');
```
