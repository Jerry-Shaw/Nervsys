## libUpload 描述

`libUpload` 是一个文件上传扩展，提供处理单个和切片（分块）上传的方法，支持 Base64 数据、MIME 类型验证和文件合并。此类继承自
`Factory`。

**语言:** 中文 | [English Doc](./libUpload-en_us.md)

## 命名空间

`Nervsys\Ext`

## 常量

### `UPLOAD_ERROR`

错误消息数组，按错误码索引：

- `0`: 上传成功。
- `1`: 文件太大（INI 大小）。
- `2`: 文件太大（表单大小）。
- `3`: 部分上传。
- `4`: 未上传文件。
- `5`: 文件类型不允许。
- `6`: 路径未找到。
- `7`: 保存失败。
- `8`: 被扩展停止。

## 属性

### `$IOData`

- **类型:** `IOData`
- **描述:** 处理上传的输入数据对象。

### `$libFileIO`

- **类型:** `libFileIO`
- **描述:** 文件 I/O 辅助对象。

### `$upload_path`

- **类型:** `string`
- **描述:** 基础上传目录路径。

### `$temp_dir`

- **类型:** `string`
- **默认值:** `'SlicedTempDir'`
- **描述:** 切片上传的临时目录。

### `$max_size`

- **类型:** `int`
- **默认值:** 0（无限制）
- **描述:** 最大文件大小（字节）。

### `$file_perm`

- **类型:** `int`
- **默认值:** 0666
- **描述:** 上传文件的权限。

### `$allowed_ext`

- **类型:** `array`
- **描述:** 允许的文件扩展名数组。

### `$mime_types`

- **类型:** `array`
- **描述:** 映射 MIME 类型到扩展名的关联数组。

## 方法

### `addMimeType(string $mime, string $ext): self`

添加自定义 MIME 类型到扩展名映射。

- **参数:**
    - `$mime`: MIME 类型（如 `'application/pdf'`）。
    - `$ext`: 文件扩展名（如 `'pdf'`）。
- **返回:** `$this`.

### `setFilePerm(int $file_perm): self`

设置上传文件的权限。

- **参数:**
    - `$file_perm`: 权限值（如 0644）。
- **返回:** `$this`.

### `setAllowedExt(string ...$allowed_ext): self`

设置允许的文件扩展名。

- **参数:**
    - `$allowed_ext`: 一个或多个扩展名。
- **返回:** `$this`.

### `setSliceTempDir(string $temp_dir): self`

设置切片上传的临时目录。

- **参数:**
    - `$temp_dir`: 目录路径。
- **返回:** `$this`.

### `setMaxSizeInBytes(int $max_size): self`

设置最大文件大小（字节）。

- **参数:**
    - `$max_size`: 最大大小（字节），0 表示无限制。
- **返回:** `$this`.

### `saveFile(string $io_data_key, string $save_dir = '', string $save_name = ''): array`

保存单个上传的文件。

- **参数:**
    - `$io_data_key`: IOData 源输入中的键。
    - `$save_dir`: 上传路径内的子目录。
    - `$save_name`: 自定义文件名（可选）。
- **返回:** 包含错误码、文件详情和结果消息的数组。

### `saveSlice(string $io_data_key, string $ticket_id, int $slice_id): array`

保存分块上传的单个切片。

- **参数:**
    - `$io_data_key`: IOData 源输入中的键。
    - `$ticket_id`: 上传会话的唯一票证标识符。
    - `$slice_id`: 切片编号（从 0 开始）。
- **返回:** 包含错误码和切片详情的数组。

### `mergeSlice(string $ticket_id, string $save_dir, string $save_name): array`

合并所有切片为单个文件。

- **参数:**
    - `$ticket_id`: 上传会话的票证标识符。
    - `$save_dir`: 上传路径内的子目录。
    - `$save_name`: 最终文件名。
- **返回:** 包含错误码和合并文件详情的数组。

### `removeSlice(string $ticket_id): void`

删除票证的所有切片。

- **参数:**
    - `$ticket_id`: 要删除的票证标识符。

## 使用示例

```php
use Nervsys\Ext\libUpload;

$upload = new libUpload('/path/to/uploads');
$upload->setAllowedExt('jpg', 'png', 'pdf');
$upload->setMaxSizeInBytes(10 * 1024 * 1024); // 10MB

// 单个文件上传
$result = $upload->saveFile('user_file', 'images', 'profile.jpg');
if ($result['error'] === UPLOAD_ERR_OK) {
    echo "Saved to: {$result['file_url']}";
}

// 切片上传（用于大文件）
$sliceResult = $upload->saveSlice('chunk_data', 'ticket_123', 0);
// ... 保存更多切片 ...

// 合并切片
$merged = $upload->mergeSlice('ticket_123', 'videos', 'movie.mp4');
if ($merged['error'] === UPLOAD_ERR_OK) {
    echo "Merged to: {$merged['file_url']}";
}

// 删除临时切片
$upload->removeSlice('ticket_123');
```
