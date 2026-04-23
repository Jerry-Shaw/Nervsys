## libLog 描述

`libLog` 是一个日志扩展，提供写入不同级别（debug、info、warning、error）的日志条目的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libLog-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `write(string $level, string $message): bool`

写入日志条目。

- **参数:**
    - `$level`: 日志级别（`'DEBUG'`, `'INFO'`, `'WARNING'`, `'ERROR'`）。
    - `$message`: 日志消息内容。
- **返回:** `bool` (成功为 true)。

## 使用示例

```php
use Nervsys\Ext\libLog;

$log = new libLog();

// 写入调试日志
$log->write('DEBUG', 'Starting application');

// 写入信息日志
$log->write('INFO', 'User logged in: user123');

// 写入警告日志
$log->write('WARNING', 'Database connection slow');

// 写入错误日志
$log->write('ERROR', 'Failed to process order #456');
```
