## libImage 描述

`libImage` 是一个图像处理扩展，提供调整大小、裁剪、旋转和应用滤镜的方法。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libImage-en_us.md)

## 命名空间

`Nervsys\Ext`

## 属性

### `$quality`

- **类型:** `int`
- **默认值:** 85
- **描述:** JPEG 质量（0-100）。

### `$compression`

- **类型:** `int`
- **默认值:** 6
- **描述:** PNG 压缩级别（0-9）。

## 方法

### `setQuality(int $quality): static`

设置输出图像的 JPEG 质量。

- **参数:**
    - `$quality`: 0 到 100 之间的质量值。
- **返回:** `$this`.

### `setCompression(int $compression): static`

设置 PNG 压缩级别。

- **参数:**
    - `$compression`: 0 到 9 之间的压缩级别。
- **返回:** `$this`.

### `resize(string $src, string $dst, int $width, int $height = 0): bool`

调整图像大小，如果高度为 0 则保持宽高比。

- **参数:**
    - `$src`: 源图像路径。
    - `$dst`: 目标图像路径。
    - `$width`: 目标宽度（像素）。
    - `$height`: 目标高度（像素），可选（保持宽高比）。
- **返回:** `bool` (成功为 true)。

### `crop(string $src, string $dst, int $x, int $y, int $width, int $height): bool`

将图像裁剪到指定尺寸。

- **参数:**
    - `$src`: 源图像路径。
    - `$dst`: 目标图像路径。
    - `$x`: 裁剪区域 X 坐标。
    - `$y`: 裁剪区域 Y 坐标。
    - `$width`: 裁剪宽度（像素）。
    - `$height`: 裁剪高度（像素）。
- **返回:** `bool` (成功为 true)。

### `rotate(string $src, string $dst, float $angle): bool`

按给定角度旋转图像。

- **参数:**
    - `$src`: 源图像路径。
    - `$dst`: 目标图像路径。
    - `$angle`: 旋转角度（度），逆时针方向。
- **返回:** `bool` (成功为 true)。

### `filter(string $src, string $dst, int $filter_type): bool`

对图像应用滤镜。

- **参数:**
    - `$src`: 源图像路径。
    - `$dst`: 目标图像路径。
    - `$filter_type`: 滤镜类型常量（如 `IMG_FILTER_GRAYSCALE`）。
- **返回:** `bool` (成功为 true)。

### `watermark(string $src, string $wm_src, string $dst, int $x, int $y): bool`

在图像上添加水印。

- **参数:**
    - `$src`: 源图像路径。
    - `$wm_src`: 水印图像路径。
    - `$dst`: 目标图像路径。
    - `$x`: 水印 X 位置。
    - `$y`: 水印 Y 位置。
- **返回:** `bool` (成功为 true)。

## 使用示例

```php
use Nervsys\Ext\libImage;

$image = new libImage();
$image->setQuality(90);

// 调整图像大小
$image->resize('/path/to/original.jpg', '/path/to/resized.jpg', 800, 600);

// 裁剪图像
$image->crop('/path/to/image.jpg', '/path/to/cropped.jpg', 100, 50, 400, 300);

// 旋转图像
$image->rotate('/path/to/image.jpg', '/path/to/rotated.jpg', 45);

// 应用灰度滤镜
$image->filter('/path/to/color.jpg', '/path/to/bw.jpg', IMG_FILTER_GRAYSCALE);

// 添加水印
$image->watermark('/path/to/photo.jpg', '/path/to/watermark.png', '/path/to/wm_photo.jpg', 10, 10);
```
