## libHttp 描述

`libHttp` 是一个 HTTP 客户端扩展，提供 GET、POST 和其他 HTTP 请求的方法，支持头部、Cookie、超时和文件上传。此类继承自
`Factory`。

**语言:** 中文 | [English Doc](./libHttp-en_us.md)

## 命名空间

`Nervsys\Ext`

## 属性

### `$timeout`

- **类型:** `int`
- **默认值:** 30
- **描述:** 请求超时时间（秒）。

### `$headers`

- **类型:** `array`
- **描述:** 请求时发送的默认头部。

### `$cookies`

- **类型:** `array`
- **描述:** 请求时发送的 Cookie。

## 方法

### `setTimeout(int $timeout): self`

设置请求超时时间（秒）。

- **参数:**
    - `$timeout`: 超时值（秒）。
- **返回:** `$this`.

### `setHeaders(array $headers): self`

为所有请求设置默认头部。

- **参数:**
    - `$headers`: 关联数组，包含头部名称和值。
- **返回:** `$this`.

### `setCookies(array $cookies): self`

设置请求时发送的 Cookie。

- **参数:**
    - `$cookies`: 关联数组，包含 Cookie 名称 - 值对。
- **返回:** `$this`.

### `get(string $url, array $params = [], array $headers = []): string`

发起 GET 请求。

- **参数:**
    - `$url`: 目标 URL。
    - `$params`: 要附加的查询参数。
    - `$headers`: 此请求的其他头部。
- **返回:** 响应体字符串。

### `post(string $url, array $data = [], array $headers = []): string`

使用表单数据或 JSON 发起 POST 请求。

- **参数:**
    - `$url`: 目标 URL。
    - `$data`: 关联数组形式的表单/JSON 数据。
    - `$headers`: 此请求的其他头部。
- **返回:** 响应体字符串。

### `put(string $url, array $data = [], array $headers = []): string`

发起 PUT 请求。

- **参数:**
    - `$url`: 目标 URL。
    - `$data`: 关联数组形式的数据。
    - `$headers`: 此请求的其他头部。
- **返回:** 响应体字符串。

### `delete(string $url, array $params = [], array $headers = []): string`

发起 DELETE 请求。

- **参数:**
    - `$url`: 目标 URL。
    - `$params`: 要附加的查询参数。
    - `$headers`: 此请求的其他头部。
- **返回:** 响应体字符串。

### `upload(string $url, array $files = [], array $data = []): string`

通过 multipart/form-data POST 上传文件。

- **参数:**
    - `$url`: 目标 URL。
    - `$files`: 关联数组形式的文件路径（键 => 文件路径）。
    - `$data`: 随文件发送的其他表单数据。
- **返回:** 响应体字符串。

## 使用示例

```php
use Nervsys\Ext\libHttp;

$http = new libHttp();
$http->setTimeout(60);
$http->setHeaders(['User-Agent' => 'MyApp/1.0']);

// GET 请求带参数
$response = $http->get('https://api.example.com/users', ['page' => 1]);

// POST 请求 JSON
$jsonData = ['name' => 'John', 'email' => 'john@example.com'];
$response = $http->post('https://api.example.com/users', $jsonData, ['Content-Type' => 'application/json']);

// PUT 请求
$updateData = ['id' => 123, 'name' => 'Updated Name'];
$response = $http->put('https://api.example.com/users/123', $updateData);

// DELETE 请求
$response = $http->delete('https://api.example.com/users/123');

// 文件上传
$files = ['file' => '/path/to/image.jpg'];
$data = ['description' => 'User profile picture'];
$response = $http->upload('https://api.example.com/upload', $files, $data);
```
