## libHttp Description

`libHttp` is an HTTP client extension that provides methods for making GET, POST, and other HTTP requests with support
for headers, cookies, timeouts, and file uploads. It extends `Factory`.

**Language:** English | [中文文档](./libHttp-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Properties

### `$timeout`

- **Type:** `int`
- **Default:** 30
- **Description:** Request timeout in seconds.

### `$headers`

- **Type:** `array`
- **Description:** Default headers to send with requests.

### `$cookies`

- **Type:** `array`
- **Description:** Cookies to send with requests.

## Methods

### `setTimeout(int $timeout): self`

Sets the request timeout in seconds.

- **Parameters:**
    - `$timeout`: Timeout value in seconds.
- **Returns:** `$this`.

### `setHeaders(array $headers): self`

Sets default headers for all requests.

- **Parameters:**
    - `$headers`: Associative array of header names and values.
- **Returns:** `$this`.

### `setCookies(array $cookies): self`

Sets cookies to send with requests.

- **Parameters:**
    - `$cookies`: Associative array of cookie name-value pairs.
- **Returns:** `$this`.

### `get(string $url, array $params = [], array $headers = []): string`

Makes a GET request.

- **Parameters:**
    - `$url`: Target URL.
    - `$params`: Query parameters to append.
    - `$headers`: Additional headers for this request.
- **Returns:** Response body as string.

### `post(string $url, array $data = [], array $headers = []): string`

Makes a POST request with form data or JSON.

- **Parameters:**
    - `$url`: Target URL.
    - `$data`: Associative array of form/JSON data.
    - `$headers`: Additional headers for this request.
- **Returns:** Response body as string.

### `put(string $url, array $data = [], array $headers = []): string`

Makes a PUT request.

- **Parameters:**
    - `$url`: Target URL.
    - `$data`: Associative array of data.
    - `$headers`: Additional headers for this request.
- **Returns:** Response body as string.

### `delete(string $url, array $params = [], array $headers = []): string`

Makes a DELETE request.

- **Parameters:**
    - `$url`: Target URL.
    - `$params`: Query parameters to append.
    - `$headers`: Additional headers for this request.
- **Returns:** Response body as string.

### `upload(string $url, array $files = [], array $data = []): string`

Uploads files via multipart/form-data POST.

- **Parameters:**
    - `$url`: Target URL.
    - `$files`: Associative array of file paths (key => filepath).
    - `$data`: Additional form data to send with files.
- **Returns:** Response body as string.

## Usage Example

```php
use Nervsys\Ext\libHttp;

$http = new libHttp();
$http->setTimeout(60);
$http->setHeaders(['User-Agent' => 'MyApp/1.0']);

// GET request with params
$response = $http->get('https://api.example.com/users', ['page' => 1]);

// POST request with JSON
$jsonData = ['name' => 'John', 'email' => 'john@example.com'];
$response = $http->post('https://api.example.com/users', $jsonData, ['Content-Type' => 'application/json']);

// PUT request
$updateData = ['id' => 123, 'name' => 'Updated Name'];
$response = $http->put('https://api.example.com/users/123', $updateData);

// DELETE request
$response = $http->delete('https://api.example.com/users/123');

// File upload
$files = ['file' => '/path/to/image.jpg'];
$data = ['description' => 'User profile picture'];
$response = $http->upload('https://api.example.com/upload', $files, $data);
```
