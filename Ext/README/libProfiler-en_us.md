## libProfiler Description

`libProfiler` is a performance profiler extension that provides methods for measuring execution time and memory usage of
code blocks. It extends `Factory`.

**Language:** English | [中文文档](./libProfiler-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `start(string $name = ''): void`

Starts profiling with an optional name.

- **Parameters:**
    - `$name`: Optional profiler name (default: empty).

### `stop(): array`

Stops profiling and returns metrics.

- **Returns:** Array containing:
    - `'time'`: Execution time in seconds.
    - `'memory'`: Memory usage in bytes.

## Usage Example

```php
use Nervsys\Ext\libProfiler;

$profiler = new libProfiler();

// Profile a code block
$profiler->start('my_operation');

// ... expensive operation ...

$result = $profiler->stop();
echo "Execution time: {$result['time']}s, Memory: {$result['memory']} bytes";
```
