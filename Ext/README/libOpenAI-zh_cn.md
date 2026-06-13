## libOpenAI 描述

`libOpenAI` 是与 OpenAI 兼容 API（例如本地 LM Studio、OpenAI、DeepSeek）交互的扩展。支持三个端点的普通（非流式）和流式请求：
`/chat/completions`、`/v1/responses` 和 `/v1/messages`。同时提供模型列表获取和向量嵌入功能。

**语言:** 中文 | [English Doc](./libOpenAI-en_us.md)

## 命名空间

`Nervsys\Ext`

## 构造函数

### `__construct(string $api_url = '', string $api_key = '', string $org_id = '')`

- **`$api_url`** : API 的基础 URL（例如 `http://127.0.0.1:1234/v1`）。
- **`$api_key`** : API 密钥（Bearer token）。
- **`$org_id`** : 组织 ID（可选，用于 OpenAI 的 `OpenAI-Organization` 请求头）。

创建两个 `libHttp` 实例（`httpNormal` 和 `httpStream`），分别使用不同的 User-Agent 字符串，默认超时时间为 300 秒。

## 配置方法（可链式调用）

### `setOrgId(string $org_id): static`

设置组织 ID，并重新配置两个 HTTP 实例。

### `setApiModel(string $model): static`

设置默认模型名称（例如 `gpt-3.5-turbo`）。

### `setMaxTokens(int $max_completion_tokens): static`

设置补全请求的 `max_completion_tokens` 参数。

### `setTemperature(float $temperature): static`

设置 `temperature` 参数（创造性，0.0 到 2.0）。

### `setModelParams(array $params): static`

将额外的模型参数合并到默认参数中。

### `setTimeout(int $seconds): static`

设置两个 HTTP 实例的超时时间（秒）。

## 核心方法（统一流式支持）

三个主要方法均接受可选的 `$callback` 参数。当提供回调时自动启用流式；否则执行非流式请求并返回带有 `'success'` 键的解析后
JSON 数组。

###

`completions(array $messages, string $model = '', array $options = [], callable $callback = null, string $callback_key = ''): array`

执行聊天补全请求（POST `/chat/completions`）。

- **`$messages`** ：消息数组，例如 `[['role' => 'user', 'content' => '你好']]`。
- **`$model`** ：覆盖默认模型。
- **`$options`** ：额外参数（与默认模型参数合并）。
- **`$callback`** ：可选的流式回调。签名：`function($key, $data, $finished): void`
    - `$key` ：回调键（自动生成或传入）。
    - `$data` ：对于流式数据块，包含解析后的 JSON 数据块，带 `'success' => true`；对于错误，包含 `'success' => false`、
      `'error'` 和 `'data'`。
    - `$finished` ：`true` 表示流结束（此时 `$data` 为空数组），`false` 表示数据块。
- **`$callback_key`** ：可选的唯一回调键（为空时自动生成）。

**返回值**：

- 如果提供了 `$callback`：返回空数组（输出由回调处理）。
- 否则：返回带有 `'success'` 键的解析后 JSON 数组。

###

`responses(array $input, string $model = '', array $options = [], callable $callback = null, string $callback_key = ''): array`

向 Responses API 发送请求（POST `/v1/responses`）。`$input` 参数可以是字符串或消息数组（取决于 API 实现）。

- 参数含义与 `completions` 相同。

###

`messages(array $message, string $model = '', array $options = [], callable $callback = null, string $callback_key = ''): array`

向 Assistants API 发送单条消息（POST `/v1/messages`）。`$message` 参数应为包含 `'role'` 和 `'content'` 的关联数组。

- 参数含义与 `completions` 相同。

### `listModels(): array`

通过 `GET /models` 获取可用模型列表。返回带有 `'success'` 键的解析后 JSON 数组。

### `createEmbedding(string $input, string $model = 'text-embedding-bge-reranker-v2-m3'): array`

为输入文本创建向量嵌入。返回带有 `'success'` 键的解析后 JSON 数组。

## 非流式方法的返回格式

所有非流式方法返回的数组**始终包含 `'success'` 键**：

- 若 `success === true`：数组包含原始 API 响应的所有字段，外加 `'success' => true`。
- 若 `success === false`：数组包含 `'error'`（字符串）和 `'data'`（原始响应体）。

## 使用示例

```php
use Nervsys\Ext\libOpenAI;

$ai = new libOpenAI('http://127.0.0.1:1234/v1', '你的-api-key');

// 非流式聊天补全
$result = $ai->completions([['role' => 'user', 'content' => '你好']]);
if ($result['success']) {
    echo $result['choices'][0]['message']['content'];
} else {
    echo '错误：' . $result['error'];
}

// 流式聊天补全
$ai->completions(
    [['role' => 'user', 'content' => '讲一个小故事']],
    '',
    [],
    function($key, $data, $finished) {
        if ($finished) return;
        if ($data['success']) {
            $chunk = $data['choices'][0]['delta']['content'] ?? '';
            echo $chunk;
            flush();
        }
    }
);

// Responses API（非流式）
$resp = $ai->responses(['input' => 'Hello world']);
if ($resp['success']) {
    print_r($resp);
}

// Messages API（流式）
$ai->messages(
    ['role' => 'user', 'content' => 'Hi'],
    '',
    [],
    function($key, $data, $finished) {
        // 处理流式数据
    }
);

// 获取模型列表
$models = $ai->listModels();
if ($models['success']) {
    print_r($models['data']);
}
```