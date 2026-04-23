## libQueue Description

`libQueue` is a message queue extension that provides methods for pushing, popping, and managing tasks in Redis-based
queues. It extends `Factory`.

**Language:** English | [中文文档](./libQueue-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `push(string $queue_name, array $data): bool`

Pushes data to a queue.

- **Parameters:**
    - `$queue_name`: Queue identifier.
    - `$data`: Data to push (associative array).
- **Returns:** `bool` (true on success).

### `pop(string $queue_name, int $timeout = 0): array|null`

Pops data from a queue.

- **Parameters:**
    - `$queue_name`: Queue identifier.
    - `$timeout`: Timeout in seconds (default: 0 for immediate).
- **Returns:** Array of popped data or null if empty/timeout.

### `size(string $queue_name): int`

Gets the number of items in a queue.

- **Parameters:**
    - `$queue_name`: Queue identifier.
- **Returns:** Number of items in queue.

## Usage Example

```php
use Nervsys\Ext\libQueue;

$queue = new libQueue();

// Push task to queue
$queue->push('email_queue', ['to' => 'user@example.com', 'subject' => 'Hello']);

// Get queue size
$count = $queue->size('email_queue');
echo "Tasks in queue: {$count}";

// Pop task from queue
$task = $queue->pop('email_queue', 5);
if ($task) {
    echo "Processing: {$task['subject']}";
}
```
