## libOpenAI 描述

`libOpenAI` 是与 OpenAI 兼容 API（例如本地 LM Studio、OpenAI、DeepSeek）交互的扩展。支持普通（非流式）和流式聊天补全、模型列表获取以及向量嵌入。内部使用两个 `libHttp` 实例 – 一个用于普通请求，一个用于流式请求。

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

### `setMaxTokens(int $max_tokens): static`

设置补全请求的 `max_tokens` 参数。

### `setTemperature(float $temperature): static`

设置 `temperature` 参数（创造性，0.0 到 2.0）。

### `setModelParams(array $params): static`

将额外的模型参数合并到默认参数中。

### `setTimeout(int $seconds): static`

设置两个 HTTP 实例的超时时间（秒）。

## 流式支持

### `onStream(string $key, callable $callback): static`

注册流式响应的回调函数。回调签名：

`function($key, $data, $finished)`

- **`$key`** ：你提供的标识键。
- **`$data`** ：包含解析后的 JSON 数据块（带 `'success' => true`）或错误数组（`'success' => false`、`'error'`、`'data'`）。当 `$finished` 为 `true` 时，`$data` 为空数组。
- **`$finished`** ：`true` 表示流结束，`false` 表示数据块。

## 核心方法

### `chat(array $messages, string $model = '', array $options = [], bool $stream = false): array`

统一的聊天补全方法。

- **`$messages`** ：消息数组，例如 `[['role' => 'user', 'content' => '你好']]`。
- **`$model`** ：覆盖默认模型。
- **`$options`** ：额外参数（与 `$model_params` 合并）。
- **`$stream`** ：若为 `true` 则进行流式请求，返回空数组（输出由回调处理）；若为 `false` 则返回带有 `'success'` 键的解析后 JSON 数组。

### `ask(string $prompt, string $system = '', string $model = '', array $options = [], bool $stream = false): array`

单轮对话的快捷方法。

- **`$prompt`** ：用户消息。
- **`$system`** ：可选的系统消息。
- 其他参数同 `chat()`。

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

// 非流式聊天
$result = $ai->ask('你好，最近怎么样？');
if ($result['success']) {
    echo $result['choices'][0]['message']['content'];
} else {
    echo '错误：' . $result['error'];
}

// 流式聊天
$ai->onStream('output', function($key, $data, $finished) {
    if ($finished) return;
    if ($data['success']) {
        $chunk = $data['choices'][0]['delta']['content'] ?? '';
        echo $chunk;
        flush();
    }
});
$ai->ask('讲一个小故事', '', '', [], true);

// 获取模型列表
$models = $ai->listModels();
if ($models['success']) {
    print_r($models['data']);
}
```