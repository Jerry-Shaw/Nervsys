## libHttp 描述

`libHttp` 是一个 HTTP 客户端扩展，提供基于 cURL 的低级请求处理，支持 Header、Cookie、文件上传、自定义 cURL 选项以及流式回调。此类继承自 `Factory`。

**语言:** 中文 | [English Doc](./libHttp-en_us.md)

## 命名空间

`Nervsys\Ext`

## 属性

该类具有多个公共属性，存储原始和解析后的响应数据：

- `$raw_header` : 原始响应头字符串。
- `$raw_cookie` : 原始 Cookie 字符串。
- `$http_body` : 响应体字符串。
- `$http_header` : 解析后的响应头数组。
- `$http_cookie` : 解析后的 Cookie 数组。
- `$curl_info` : cURL 信息数组。
- `$curl_error` : cURL 错误字符串。

## 方法

### 配置（持久化）

以下方法用于配置实例，配置会持续用于后续请求。

#### `setHttpMethod(string $http_method): static`

设置 HTTP 方法（例如 `GET`、`POST`、`PUT`、`DELETE`）。

#### `setContentType(string $content_type): static`

设置 Content-Type 头部（例如 `application/json`）。会影响请求体的编码方式。

#### `setTimeout(int $timeout): static`

设置请求超时时间（秒）。

#### `setUserAgent(string $user_agent): static`

设置 User-Agent 字符串。

#### `setReferer(string $referer): static`

设置 Referer 头部。

#### `setAcceptEncoding(string $accept_encoding): static`

设置 Accept-Encoding 头部（cURL 选项 `CURLOPT_ENCODING`）。

#### `setAcceptType(string $accept_type): static`

设置 Accept 头部。

#### `setSslVerifyHost(int $ssl_verifyhost): static`

设置 cURL 选项 `CURLOPT_SSL_VERIFYHOST`。

#### `setSslVerifyPeer(bool $ssl_verifypeer): static`

设置 cURL 选项 `CURLOPT_SSL_VERIFYPEER`。

#### `setProxy(string $proxy, string $proxy_passwd): static`

设置代理及可选密码。

#### `setMaxFollow(int $max_follow): static`

设置最大重定向次数。

#### `setCookie(string $cookie): static`

直接设置 Cookie 字符串（会覆盖之前的 Cookie）。如需追加请使用 `addCookie()`。

#### `addCookie(array $cookie): static`

追加 Cookie 键值对到现有的 Cookie 字符串。

#### `addHeader(array $header): static`

添加请求头（关联数组）。

#### `addOptions(array $curl_opt_pair): static`

添加自定义 cURL 选项（仅整数键有效，字符串键将被忽略）。

#### `removeOptions(int ...$curl_opts): static`

移除之前设置的 cURL 选项（通过常量指定）。

#### `resetOptions(): static`

重置所有持久化配置（包括用户配置和 cURL 选项）。

### 临时请求数据

这些方法添加的数据在每次 `fetch()` 调用后会被清空。

#### `addData(array $data): static`

添加请求数据（表单字段或 JSON 负载）。会与之前的数据合并。

#### `addFile(string $key, string $filename, string $mime_type = '', string $posted_filename = ''): static`

添加上传文件（会自动将 Content-Type 设置为 `multipart/form-data`）。

#### `withBody(bool $with_body): static`

设置是否获取响应体。默认为 `true`，设置为 `false` 则只获取响应头。

### 流式处理

#### `setStreamCallback(callable $callback): static`

设置流式响应回调。回调接收 `($ch, $chunk)`，必须返回已处理的字节数。

#### `removeStreamCallback(): static`

移除流式回调。

### 执行请求

#### `fetch(string $url, string $to_file = '', bool $reset_options = false): string`

执行请求。

- **`$url`** : 目标 URL。
- **`$to_file`** : 可选，保存响应体的文件路径（代替返回字符串）。
- **`$reset_options`** : 若为 `true`，则请求结束后重置所有持久化配置。
- **返回**: 响应体（当使用流式回调时返回空字符串），或保存文件的路径。

### 响应信息获取

- `getHttpCode(): int`
- `getDownSize(): float`
- `getBodySize(): float`
- `getTotalTime(): float`
- `getHttpBody(): string`
- `getHttpError(): string`
- `getHttpHeader(): array`
- `getHttpCookie(): array`
- `parseRawCookie(string $cookie): array` — 工具方法，解析 Cookie 字符串。

## 使用示例

```php
use Nervsys\Ext\libHttp;

// 创建实例，可指定默认 User-Agent 和超时
$http = new libHttp('MyApp/1.0', 60);

// 设置通用请求头
$http->addHeader(['X-API-Key' => 'abc123']);

// POST JSON 请求
$http->setHttpMethod('POST');
$http->setContentType(libHttp::CONTENT_TYPE_JSON);
$http->addData(['name' => '张三', 'email' => 'zhangsan@example.com']);

$response = $http->fetch('https://api.example.com/users');
$data = json_decode($response, true);

// GET 请求带查询参数（手动构造 URL 或使用 addData 配合 GET 方法）
$http->setHttpMethod('GET');
$http->addData(['page' => 1, 'limit' => 10]);
$response = $http->fetch('https://api.example.com/users?' . http_build_query(['page' => 1, 'limit' => 10]));

// 文件上传
$http->setHttpMethod('POST');
$http->addFile('file', '/path/to/image.jpg');
$http->addData(['description' => '头像']);
$response = $http->fetch('https://api.example.com/upload');

// 流式响应（例如 OpenAI 流式 API）
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