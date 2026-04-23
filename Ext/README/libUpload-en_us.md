## libUpload Description

`libUpload` is a file upload extension that provides methods for handling single and sliced (chunked) uploads with
support for base64 data, MIME type validation, and file merging. It extends `Factory`.

**Language:** English | [中文文档](./libUpload-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Constants

### `UPLOAD_ERROR`

Array of error messages indexed by error code:

- `0`: Upload succeeded.
- `1`: File too large (INI size).
- `2`: File too large (form size).
- `3`: Partially uploaded.
- `4`: No file uploaded.
- `5`: File type NOT allowed.
- `6`: Path NOT found.
- `7`: Failed to save file.
- `8`: Stopped by extension.

## Properties

### `$IOData`

- **Type:** `IOData`
- **Description:** Input data object for handling uploads.

### `$libFileIO`

- **Type:** `libFileIO`
- **Description:** File I/O helper object.

### `$upload_path`

- **Type:** `string`
- **Description:** Base upload directory path.

### `$temp_dir`

- **Type:** `string`
- **Default:** `'SlicedTempDir'`
- **Description:** Temporary directory for sliced uploads.

### `$max_size`

- **Type:** `int`
- **Default:** 0 (no limit)
- **Description:** Maximum file size in bytes.

### `$file_perm`

- **Type:** `int`
- **Default:** 0666
- **Description:** File permissions for uploaded files.

### `$allowed_ext`

- **Type:** `array`
- **Description:** Array of allowed file extensions.

### `$mime_types`

- **Type:** `array`
- **Description:** Associative array mapping MIME types to extensions.

## Methods

### `addMimeType(string $mime, string $ext): static`

Adds a custom MIME type to extension mapping.

- **Parameters:**
    - `$mime`: MIME type (e.g., `'application/pdf'`).
    - `$ext`: File extension (e.g., `'pdf'`).
- **Returns:** `$this`.

### `setFilePerm(int $file_perm): static`

Sets file permissions for uploaded files.

- **Parameters:**
    - `$file_perm`: Permission value (e.g., 0644).
- **Returns:** `$this`.

### `setAllowedExt(string ...$allowed_ext): static`

Sets allowed file extensions.

- **Parameters:**
    - `$allowed_ext`: One or more extensions.
- **Returns:** `$this`.

### `setSliceTempDir(string $temp_dir): static`

Sets the temporary directory for sliced uploads.

- **Parameters:**
    - `$temp_dir`: Directory path.
- **Returns:** `$this`.

### `setMaxSizeInBytes(int $max_size): static`

Sets maximum file size in bytes.

- **Parameters:**
    - `$max_size`: Maximum size in bytes (0 for unlimited).
- **Returns:** `$this`.

### `saveFile(string $io_data_key, string $save_dir = '', string $save_name = ''): array`

Saves a single uploaded file.

- **Parameters:**
    - `$io_data_key`: Key in IOData source input.
    - `$save_dir`: Subdirectory within upload path.
    - `$save_name`: Custom filename (optional).
- **Returns:** Array with error code, file details, and result message.

### `saveSlice(string $io_data_key, string $ticket_id, int $slice_id): array`

Saves a single slice of a chunked upload.

- **Parameters:**
    - `$io_data_key`: Key in IOData source input.
    - `$ticket_id`: Unique ticket identifier for the upload session.
    - `$slice_id`: Slice number (0-based).
- **Returns:** Array with error code and slice details.

### `mergeSlice(string $ticket_id, string $save_dir, string $save_name): array`

Merges all slices into a single file.

- **Parameters:**
    - `$ticket_id`: Ticket identifier from upload session.
    - `$save_dir`: Subdirectory within upload path.
    - `$save_name`: Final filename.
- **Returns:** Array with error code and merged file details.

### `removeSlice(string $ticket_id): void`

Removes all slices for a ticket.

- **Parameters:**
    - `$ticket_id`: Ticket identifier to remove.

## Usage Example

```php
use Nervsys\Ext\libUpload;

$upload = new libUpload('/path/to/uploads');
$upload->setAllowedExt('jpg', 'png', 'pdf');
$upload->setMaxSizeInBytes(10 * 1024 * 1024); // 10MB

// Single file upload
$result = $upload->saveFile('user_file', 'images', 'profile.jpg');
if ($result['error'] === UPLOAD_ERR_OK) {
    echo "Saved to: {$result['file_url']}";
}

// Sliced upload (for large files)
$sliceResult = $upload->saveSlice('chunk_data', 'ticket_123', 0);
// ... save more slices ...

// Merge slices
$merged = $upload->mergeSlice('ticket_123', 'videos', 'movie.mp4');
if ($merged['error'] === UPLOAD_ERR_OK) {
    echo "Merged to: {$merged['file_url']}";
}

// Remove temporary slices
$upload->removeSlice('ticket_123');
```
