## libZip Description

`libZip` is a ZIP archive extension that provides methods for creating, extracting, and managing ZIP files. It extends
`Factory`.

**Language:** English | [中文文档](./libZip-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Constants

### `ERRNO`

Error message array indexed by ZipArchive error codes:

- `ER_EXISTS`: File already exists.
- `ER_INCONS`: Zip archive inconsistent.
- `ER_INVAL`: Invalid argument.
- `ER_MEMORY`: Malloc failure.
- `ER_NOENT`: No such file.
- `ER_NOZIP`: Not a zip archive.
- `ER_OPEN`: Can't open file.
- `ER_READ`: Read error.
- `ER_SEEK`: Seek error.

## Properties

### `$store_path`

- **Type:** `string`
- **Default:** `'zipFile'` (relative to app root)
- **Description:** Directory where ZIP files are stored.

### `$target_file`

- **Type:** `array`
- **Description:** Array of file/folder paths to include in the archive.

## Methods

### `setStorePath(string $path): self`

Sets the directory for storing ZIP files.

- **Parameters:**
    - `$path`: Directory path.
- **Returns:** `$this`.

### `add(string $path): self`

Adds a file or folder to the target list.

- **Parameters:**
    - `$path`: Path to file or folder.
- **Returns:** `$this`.

### `zipTo(string $filename): array`

Creates a ZIP archive from added files/folders.

- **Parameters:**
    - `$filename`: Base name for the output ZIP file (without extension).
- **Returns:** Array with:
    - `'errno'`: Error code (0 on success).
    - `'path'`: Full path to created ZIP file.
    - OR error array with `'errno'` and `'message'`.

### `unzip(string $file, string $to): bool`

Extracts a ZIP archive to the specified directory.

- **Parameters:**
    - `$file`: Path to ZIP file.
    - `$to`: Destination directory path.
- **Returns:** `bool` (true on success).

## Usage Example

```php
use Nervsys\Ext\libZip;

$zip = new libZip();
$zip->setStorePath('/path/to/zips');

// Add files and folders to archive
$zip->add('/path/to/file1.txt')
    ->add('/path/to/folder1')
    ->add('/path/to/file2.jpg');

// Create ZIP file
$result = $zip->zipTo('backup_2024');
if ($result['errno'] === 0) {
    echo "Created: {$result['path']}";
} else {
    echo "Error: {$result['message']}";
}

// Extract ZIP file
$success = $zip->unzip('/path/to/backup_2024.zip', '/path/to/extract');
if ($success) {
    echo "Extracted successfully!";
} else {
    echo "Extraction failed!";
}
```
