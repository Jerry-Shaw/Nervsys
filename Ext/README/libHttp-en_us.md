## libHttp Description

`libHttp` is an HTTP client extension that provides low-level cURL-based request handling with support for headers, cookies, file uploads, custom cURL options, and stream callbacks. It extends `Factory`.

**Language:** English | [中文文档](./libHttp-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Properties

The class has several public properties that store raw and parsed response data:

- `$raw_header` : Raw response header string.
- `$raw_cookie` : Raw cookie string.
- `$http_body` : Response body string.
- `$http_header` : Parsed response headers as array.
- `$http_cookie` : Parsed cookies as array.
- `$curl_info` : cURL info array.
- `$curl_error` : cURL error string.

## Methods

### Configuration (Persistent)

These methods configure the instance for subsequent requests.

#### `setHttpMethod(string $http_method): static`

Sets the HTTP method (e.g., `GET`, `POST`, `PUT`, `DELETE`).

#### `setContentType(string $content_type): static`

Sets the Content-Type header (e.g., `application/json`). Affects how request body is encoded.

#### `setTimeout(int $timeout): static`

Sets request timeout in seconds.

#### `setUserAgent(string $user_agent): static`

Sets the User-Agent string.

#### `setReferer(string $referer): static`

Sets the Referer header.

#### `setAcceptEncoding(string $accept_encoding): static`

Sets the Accept-Encoding header (cURL option `CURLOPT_ENCODING`).

#### `setAcceptType(string $accept_type): static`

Sets the Accept header.

#### `setSslVerifyHost(int $ssl_verifyhost): static`

Sets cURL `CURLOPT_SSL_VERIFYHOST`.

#### `setSslVerifyPeer(bool $ssl_verifypeer): static`

Sets cURL `CURLOPT_SSL_VERIFYPEER`.

#### `setProxy(string $proxy, string $proxy_passwd): static`

Sets proxy and optional password.

#### `setMaxFollow(int $max_follow): static`

Sets maximum number of redirects to follow.

#### `setCookie(string $cookie): static`

Directly sets the cookie string (overwrites previous cookie). Use `addCookie()` to append.

#### `addCookie(array $cookie): static`

Appends cookie key-value pairs to the existing cookie string.

#### `addHeader(array $header): static`

Adds headers (associative array) to the request.

#### `addOptions(array $curl_opt_pair): static`

Adds custom cURL options (integer keys only). String keys are ignored.

#### `removeOptions(int ...$curl_opts): static`

Removes previously set cURL options (by constant).

#### `resetOptions(): static`

Resets all persistent configuration (both user config and cURL options).

### Request Data (Temporary)

These methods add data that is cleared after each `fetch()` call.

#### `addData(array $data): static`

Adds request data (form fields or JSON payload). Array is merged with previous data.

#### `addFile(string $key, string $filename, string $mime_type = '', string $posted_filename = ''): static`

Adds a file for upload (will automatically set Content-Type to `multipart/form-data`).

#### `withBody(bool $with_body): static`

Whether to fetch response body. Default `true`. Set to `false` to only fetch headers.

### Streaming

#### `setStreamCallback(callable $callback): static`

Sets a callback for streaming response data. The callback receives `($ch, $chunk)` and must return the number of bytes processed.

#### `removeStreamCallback(): static`

Removes the stream callback.

### Executing Requests

#### `fetch(string $url, string $to_file = '', bool $reset_options = false): string`

Executes the request.

- **`$url`** : Target URL.
- **`$to_file`** : Optional file path to save response body (instead of returning).
- **`$reset_options`** : If `true`, all persistent configuration is reset after this request.
- **Returns**: Response body (or `''` when stream callback is used, or file path when saving).

### Response Getters

- `getHttpCode(): int`
- `getDownSize(): float`
- `getBodySize(): float`
- `getTotalTime(): float`
- `getHttpBody(): string`
- `getHttpError(): string`
- `getHttpHeader(): array`
- `getHttpCookie(): array`
- `parseRawCookie(string $cookie): array` — Utility to parse a cookie string.

## Usage Example

```php
use Nervsys\Ext\libHttp;

// Create instance with default User-Agent and timeout
$http = new libHttp('MyApp/1.0', 60);

// Set common headers
$http->addHeader(['X-API-Key' => 'abc123']);

// POST JSON request
$http->setHttpMethod('POST');
$http->setContentType(libHttp::CONTENT_TYPE_JSON);
$http->addData(['name' => 'John', 'email' => 'john@example.com']);

$response = $http->fetch('https://api.example.com/users');
$data = json_decode($response, true);

// GET request with query parameters (manually build URL or use addData with GET method)
$http->setHttpMethod('GET');
$http->addData(['page' => 1, 'limit' => 10]);
$response = $http->fetch('https://api.example.com/users?' . http_build_query(['page' => 1, 'limit' => 10]));

// File upload
$http->setHttpMethod('POST');
$http->addFile('file', '/path/to/image.jpg');
$http->addData(['description' => 'Profile picture']);
$response = $http->fetch('https://api.example.com/upload');

// Stream response (e.g., OpenAI stream)
$http->setHttpMethod('POST');
$http->setContentType(libHttp::CONTENT_TYPE_JSON);
$http->addData(['stream' => true, 'model' => 'gpt-3.5-turbo', 'messages' => [...]]);
$http->setStreamCallback(function($ch, $chunk) {
    echo $chunk;
    flush();
    return strlen($chunk);
});
$http->fetch('https://api.openai.com/v1/chat/completions');
$http->removeStreamCallback();
```