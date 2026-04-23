## libZip 描述

`libZip` 是一个 ZIP 归档扩展，提供创建、提取和管理 ZIP 文件的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libZip-en_us.md)

## 命名空间

`Nervsys\Ext`

## 常量

### `ERRNO`

错误消息数组，按 ZipArchive 错误码索引：

- `ER_EXISTS`: 文件已存在。
- `ER_INCONS`: ZIP 归档不一致。
- `ER_INVAL`: 无效参数。
- `ER_MEMORY`: malloc 失败。
- `ER_NOENT`: 文件不存在。
- `ER_NOZIP`: 不是 ZIP 归档。
- `ER_OPEN`: 无法打开文件。
- `ER_READ`: 读取错误。
- `ER_SEEK`: 定位错误。

## 属性

### `$store_path`

- **类型:** `string`
- **默认值:** `'zipFile'`（相对于应用根目录）
- **描述:** 存储 ZIP 文件的目录。

### `$target_file`

- **类型:** `array`
- **描述:** 要包含在归档中的文件/文件夹路径数组。

## 方法

### `setStorePath(string $path): static`

设置存储 ZIP 文件的目录。

- **参数:**
    - `$path`: 目录路径。
- **返回:** `$this`.

### `add(string $path): static`

将文件/文件夹添加到目标列表。

- **参数:**
    - `$path`: 文件或文件夹的路径。
- **返回:** `$this`.

### `zipTo(string $filename): array`

从添加的文件/文件夹创建 ZIP 归档。

- **参数:**
    - `$filename`: 输出 ZIP 文件的基名（不含扩展名）。
- **返回:** 包含以下内容的数组：
    - `'errno'`: 错误码（成功为 0）。
    - `'path'`: 创建的 ZIP 文件完整路径。
    - OR 包含 `'errno'` 和 `'message'` 的错误数组。

### `unzip(string $file, string $to): bool`

将 ZIP 归档提取到指定目录。

- **参数:**
    - `$file`: ZIP 文件路径。
    - `$to`: 目标目录路径。
- **返回:** `bool` (成功为 true)。

## 使用示例

```php
use Nervsys\Ext\libZip;

$zip = new libZip();
$zip->setStorePath('/path/to/zips');

// 将文件和文件夹添加到归档
$zip->add('/path/to/file1.txt')
    ->add('/path/to/folder1')
    ->add('/path/to/file2.jpg');

// 创建 ZIP 文件
$result = $zip->zipTo('backup_2024');
if ($result['errno'] === 0) {
    echo "Created: {$result['path']}";
} else {
    echo "Error: {$result['message']}";
}

// 提取 ZIP 文件
$success = $zip->unzip('/path/to/backup_2024.zip', '/path/to/extract');
if ($success) {
    echo "Extracted successfully!";
} else {
    echo "Extraction failed!";
}
```
