## libImage Description

`libImage` is an image processing extension that provides methods for resizing, cropping, rotating, and applying filters
to images. It extends `Factory`.

**Language:** English | [中文文档](./libImage-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Properties

### `$quality`

- **Type:** `int`
- **Default:** 85
- **Description:** JPEG quality (0-100).

### `$compression`

- **Type:** `int`
- **Default:** 6
- **Description:** PNG compression level (0-9).

## Methods

### `setQuality(int $quality): self`

Sets the JPEG quality for output images.

- **Parameters:**
    - `$quality`: Quality value from 0 to 100.
- **Returns:** `$this`.

### `setCompression(int $compression): self`

Sets the PNG compression level.

- **Parameters:**
    - `$compression`: Compression level from 0 to 9.
- **Returns:** `$this`.

### `resize(string $src, string $dst, int $width, int $height = 0): bool`

Resizes an image while maintaining aspect ratio if height is 0.

- **Parameters:**
    - `$src`: Source image path.
    - `$dst`: Destination image path.
    - `$width`: Target width in pixels.
    - `$height`: Target height in pixels (optional, maintains aspect ratio).
- **Returns:** `bool` (true on success).

### `crop(string $src, string $dst, int $x, int $y, int $width, int $height): bool`

Crops an image to specified dimensions.

- **Parameters:**
    - `$src`: Source image path.
    - `$dst`: Destination image path.
    - `$x`: X coordinate of crop area.
    - `$y`: Y coordinate of crop area.
    - `$width`: Crop width in pixels.
    - `$height`: Crop height in pixels.
- **Returns:** `bool` (true on success).

### `rotate(string $src, string $dst, float $angle): bool`

Rotates an image by a given angle.

- **Parameters:**
    - `$src`: Source image path.
    - `$dst`: Destination image path.
    - `$angle`: Rotation angle in degrees (counter-clockwise).
- **Returns:** `bool` (true on success).

### `filter(string $src, string $dst, int $filter_type): bool`

Applies a filter to an image.

- **Parameters:**
    - `$src`: Source image path.
    - `$dst`: Destination image path.
    - `$filter_type`: Filter type constant (e.g., `IMG_FILTER_GRAYSCALE`).
- **Returns:** `bool` (true on success).

### `watermark(string $src, string $wm_src, string $dst, int $x, int $y): bool`

Adds a watermark to an image.

- **Parameters:**
    - `$src`: Source image path.
    - `$wm_src`: Watermark image path.
    - `$dst`: Destination image path.
    - `$x`: X position for watermark.
    - `$y`: Y position for watermark.
- **Returns:** `bool` (true on success).

## Usage Example

```php
use Nervsys\Ext\libImage;

$image = new libImage();
$image->setQuality(90);

// Resize image
$image->resize('/path/to/original.jpg', '/path/to/resized.jpg', 800, 600);

// Crop image
$image->crop('/path/to/image.jpg', '/path/to/cropped.jpg', 100, 50, 400, 300);

// Rotate image
$image->rotate('/path/to/image.jpg', '/path/to/rotated.jpg', 45);

// Apply grayscale filter
$image->filter('/path/to/color.jpg', '/path/to/bw.jpg', IMG_FILTER_GRAYSCALE);

// Add watermark
$image->watermark('/path/to/photo.jpg', '/path/to/watermark.png', '/path/to/wm_photo.jpg', 10, 10);
```
