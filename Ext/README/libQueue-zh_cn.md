## libQueue 描述

`libQueue` 是一个消息队列扩展，提供在基于 Redis 的队列中推送、弹出和管理任务的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libQueue-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `push(string $queue_name, array $data): bool`

将数据推送到队列。

- **参数:**
    - `$queue_name`: 队列标识符。
    - `$data`: 要推送的数据（关联数组）。
- **返回:** `bool` (成功为 true)。

### `pop(string $queue_name, int $timeout = 0): array|null`

从队列弹出数据。

- **参数:**
    - `$queue_name`: 队列标识符。
    - `$timeout`: 超时时间（秒），默认 0（立即）。
- **返回:** 弹出的数据数组，如果为空或超时则为 null。

### `size(string $queue_name): int`

获取队列中的项目数。

- **参数:**
    - `$queue_name`: 队列标识符。
- **返回:** 队列中的项目数量。

## 使用示例

```php
use Nervsys\Ext\libQueue;

$queue = new libQueue();

// 将任务推送到队列
$queue->push('email_queue', ['to' => 'user@example.com', 'subject' => 'Hello']);

// 获取队列大小
$count = $queue->size('email_queue');
echo "Tasks in queue: {$count}";

// 从队列弹出任务
$task = $queue->pop('email_queue', 5);
if ($task) {
    echo "Processing: {$task['subject']}";
}
```
