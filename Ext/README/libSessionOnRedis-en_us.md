## libSessionOnRedis Description

`libSessionOnRedis` is a session handler extension that stores PHP sessions in Redis for better scalability and shared
state across servers. It extends `Factory`.

**Language:** English | [中文文档](./libSessionOnRedis-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `start(): bool`

Starts the session.

- **Returns:** `bool` (true on success).

### `read(string $session_id): string|false`

Reads session data from Redis.

- **Parameters:**
    - `$session_id`: Session identifier.
- **Returns:** Serialized session data or false if not found.

### `write(): bool`

Writes session data to Redis.

- **Returns:** `bool` (true on success).

### `destroy(string $session_id): bool`

Destroys a session.

- **Parameters:**
    - `$session_id`: Session identifier.
- **Returns:** `bool` (true on success).

## Usage Example

```php
use Nervsys\Ext\libSessionOnRedis;

$redisSession = new libSessionOnRedis();
$redisSession->start();

// Set session data
$_SESSION['user_id'] = 123;
$_SESSION['username'] = 'john_doe';

// Get session data
echo "User ID: {$_SESSION['user_id']}";

// Destroy session
$redisSession->destroy(session_id());
```
