## libProfiler 描述

`libProfiler` 是一个性能分析器扩展，提供测量代码块执行时间和内存使用的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libProfiler-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `start(string $name = ''): void`

开始分析（可选名称）。

- **参数:**
    - `$name`: 可选的分析器名称（默认：空）。

### `stop(): array`

停止分析并返回指标。

- **返回:** 包含以下内容的数组：
    - `'time'`: 执行时间（秒）。
    - `'memory'`: 内存使用量（字节）。

## 使用示例

```php
use Nervsys\Ext\libProfiler;

$profiler = new libProfiler();

// 分析代码块
$profiler->start('my_operation');

// ... 耗时操作 ...

$result = $profiler->stop();
echo "Execution time: {$result['time']}s, Memory: {$result['memory']} bytes";
```
