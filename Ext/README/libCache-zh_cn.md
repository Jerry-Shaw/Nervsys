## libCache 描述

`libCache` 是基于 Redis 的缓存扩展。它提供简单的键值缓存操作，包括设置、获取和删除。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libCache-en_us.md)

## 命名空间

`Nervsys\Ext`

## 常量

### `PREFIX`

所有缓存键的前缀 (`CAS:`)。

## 属性

### `$redis`

- **类型:** `\Redis|libRedis`
- **描述:** 用于存储的 Redis 实例或 libRedis 对象。

## 方法

### `bindRedis(\Redis|libRedis $redis): self`

绑定一个 Redis 实例到该类。

- **参数:**
    - `$redis`: `\Redis` 或 `libRedis` 实例。
- **返回:** `$this`.

### `set(string $key, array $data, int $life = 600): bool`

通过键设置缓存数据。

- **参数:**
    - `$key`: 缓存键（不含前缀）。
    - `$data`: 要存储的数组数据。
    - `$life`: 过期时间（秒），默认 600。≤0 表示永不过期。
- **返回:** `bool` (成功为 true，失败为 false)。
- **异常:** Redis 错误时抛出 `\RedisException`。

### `get(string $key): array`

通过键获取缓存数据。

- **参数:**
    - `$key`: 缓存键（不含前缀）。
- **返回:** 缓存数据的数组，未找到或无效时返回空数组。

### `del(string $key): int`

通过键删除缓存。

- **参数:**
    - `$key`: 缓存键（不含前缀）。
- **返回:** 已删除的项目数量（通常为 1）。
- **异常:** Redis 错误时抛出 `\RedisException`。

## 使用示例

```php
use Nervsys\Ext\libCache;

$cache = new libCache();
$cache->bindRedis($redis); // 使用 Redis 或 libRedis 实例

// 设置缓存
$success = $cache->set('user:123', ['name' => 'John', 'email' => 'john@example.com'], 3600);

// 获取缓存
$data = $cache->get('user:123');
if (!empty($data)) {
    echo "用户名：{$data['name']}";
}

// 删除缓存
$deleted = $cache->del('user:123');
echo "已删除：" . ($deleted ? '是' : '否');
```
