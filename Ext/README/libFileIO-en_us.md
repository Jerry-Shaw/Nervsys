## libFileIO Description

`libFileIO` is a file I/O extension that provides utilities for directory operations, file listing, and batch file
management. It extends `Factory`.

**Language:** English | [中文文档](./libFileIO-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Methods

### `getExt(string $path): string`

Gets the file extension in lowercase.

- **Parameters:**
    - `$path`: File path.
- **Returns:** File extension (lowercase) or empty string if none.

### `mkPath(string $path, string $root = ''): string`

Creates a directory if it doesn't exist (root-based).

- **Parameters:**
    - `$path`: Directory path to create.
    - `$root`: Root path (defaults to app root).
- **Returns:** Full directory path.
- **Throws:** `\ReflectionException` on failure.

### `getFiles(string $path, bool $recursive = false): array`

Gets a list of files in a directory or recursively.

- **Parameters:**
    - `$path`: Directory path.
    - `$recursive`: Whether to search subdirectories.
- **Returns:** Array of file paths.

### `findFiles(string $path, string $pattern = '*', bool $recursive = false): array`

Finds files by pattern in a directory or recursively.

- **Parameters:**
    - `$path`: Directory path.
    - `$pattern`: File pattern (e.g., `*.php`).
    - `$recursive`: Whether to search subdirectories.
- **Returns:** Array of matching file paths.

### `getDirContents(string $path): array`

Gets directory contents with metadata.

- **Parameters:**
    - `$path`: Directory path.
- **Returns:** Array of files/directories with:
    - `'filename'`: File name.
    - `'filepath'`: Full path.
    - `'filesize'`: File size in bytes (0 for dirs).
    - `'isFile'`: Boolean indicating if it's a file.
    - `'urlPath'`: Relative URL path.

### `copyDir(string $src, string $dst): int`

Copies a directory recursively.

- **Parameters:**
    - `$src`: Source directory path.
    - `$dst`: Destination directory path.
- **Returns:** Number of files copied (-1 on failure).

### `delDir(string $path): int`

Deletes a directory and its contents.

- **Parameters:**
    - `$path`: Directory path to delete.
- **Returns:** Number of files removed (-1 on failure).

## Usage Example

```php
use Nervsys\Ext\libFileIO;

$io = new libFileIO();

// Get file extension
$ext = $io->getExt('/path/to/file.txt'); // Returns: "txt"

// Create directory
$path = $io->mkPath('uploads/images', '/var/www');

// List files in directory
$files = $io->getFiles($path);

// Find all PHP files recursively
$phpFiles = $io->findFiles($path, '*.php', true);

// Get directory contents with metadata
$contents = $io->getDirContents($path);
foreach ($contents as $item) {
    echo "{$item['filename']}: {$item['filesize']} bytes\n";
}

// Copy directory
$copied = $io->copyDir('/src/dir', '/dst/dir');
echo "Copied: {$copied} files\n";

// Delete directory
$removed = $io->delDir('/tmp/old_dir');
echo "Removed: {$removed} files\n";
```
