## libFileIO 描述

`libFileIO` 是一个文件 I/O 扩展，提供目录操作、文件列表和批量文件管理工具。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libFileIO-en_us.md)

## 命名空间

`Nervsys\Ext`

## 方法

### `getExt(string $path): string`

获取小写的文件扩展名。

- **参数:**
    - `$path`: 文件路径。
- **返回:** 文件扩展名（小写），无扩展时返回空字符串。

### `mkPath(string $path, string $root = ''): string`

如果目录不存在则创建目录（基于根路径）。

- **参数:**
    - `$path`: 要创建的目录路径。
    - `$root`: 根路径（默认为应用根目录）。
- **返回:** 完整的目录路径。
- **异常:** 失败时抛出 `\ReflectionException`。

### `getFiles(string $path, bool $recursive = false): array`

获取目录中的文件列表或递归获取。

- **参数:**
    - `$path`: 目录路径。
    - `$recursive`: 是否搜索子目录。
- **返回:** 文件路径数组。

### `findFiles(string $path, string $pattern = '*', bool $recursive = false): array`

通过模式在目录中查找文件或递归查找。

- **参数:**
    - `$path`: 目录路径。
    - `$pattern`: 文件模式（如 `*.php`）。
    - `$recursive`: 是否搜索子目录。
- **返回:** 匹配的文件路径数组。

### `getDirContents(string $path): array`

获取带元数据的目录内容。

- **参数:**
    - `$path`: 目录路径。
- **返回:** 包含以下内容的文件/目录数组：
    - `'filename'`: 文件名。
    - `'filepath'`: 完整路径。
    - `'filesize'`: 文件大小（字节），目录为 0。
    - `'isFile'`: 布尔值，指示是否为文件。
    - `'urlPath'`: 相对 URL 路径。

### `copyDir(string $src, string $dst): int`

递归复制目录。

- **参数:**
    - `$src`: 源目录路径。
    - `$dst`: 目标目录路径。
- **返回:** 已复制的文件数（失败为 -1）。

### `delDir(string $path): int`

删除目录及其内容。

- **参数:**
    - `$path`: 要删除的目录路径。
- **返回:** 已删除的文件数（失败为 -1）。

## 使用示例

```php
use Nervsys\Ext\libFileIO;

$io = new libFileIO();

// 获取文件扩展名
$ext = $io->getExt('/path/to/file.txt'); // 返回："txt"

// 创建目录
$path = $io->mkPath('uploads/images', '/var/www');

// 列出目录中的文件
$files = $io->getFiles($path);

// 递归查找所有 PHP 文件
$phpFiles = $io->findFiles($path, '*.php', true);

// 获取带元数据的目录内容
$contents = $io->getDirContents($path);
foreach ($contents as $item) {
    echo "{$item['filename']}: {$item['filesize']} bytes\n";
}

// 复制目录
$copied = $io->copyDir('/src/dir', '/dst/dir');
echo "Copied: {$copied} files\n";

// 删除目录
$removed = $io->delDir('/tmp/old_dir');
echo "Removed: {$removed} files\n";
```
