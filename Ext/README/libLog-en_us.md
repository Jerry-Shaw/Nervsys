## libLog Description

`libLog` is a logging extension that provides methods for writing log entries with different levels (debug, info,
warning, error). It extends `Factory`.

**Language:** English | [中文文档](./libLog-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `write(string $level, string $message): bool`

Writes a log entry.

- **Parameters:**
    - `$level`: Log level (`'DEBUG'`, `'INFO'`, `'WARNING'`, `'ERROR'`).
    - `$message`: Log message content.
- **Returns:** `bool` (true on success).

## Usage Example

```php
use Nervsys\Ext\libLog;

$log = new libLog();

// Write debug log
$log->write('DEBUG', 'Starting application');

// Write info log
$log->write('INFO', 'User logged in: user123');

// Write warning log
$log->write('WARNING', 'Database connection slow');

// Write error log
$log->write('ERROR', 'Failed to process order #456');
```
