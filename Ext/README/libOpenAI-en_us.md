## libOpenAI Description

`libOpenAI` is an extension for interacting with OpenAI-compatible APIs (e.g., local LM Studio, OpenAI, DeepSeek). It supports both normal (non-stream) and streaming chat completions, model listing, and embeddings. It internally uses two `libHttp` instances – one for normal requests and one for streaming.

**Language:** English | [中文文档](./libOpenAI-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Constructor

### `__construct(string $api_url = '', string $api_key = '', string $org_id = '')`

- **`$api_url`** : Base URL of the API (e.g., `http://127.0.0.1:1234/v1`).
- **`$api_key`** : API key (Bearer token).
- **`$org_id`** : Organization ID (optional, used for OpenAI `OpenAI-Organization` header).

Creates two `libHttp` instances (`httpNormal` and `httpStream`) with different User-Agent strings and a default timeout of 300 seconds.

## Configuration Methods (Chainable)

### `setOrgId(string $org_id): static`

Sets the organization ID and reconfigures both HTTP instances.

### `setApiModel(string $model): static`

Sets the default model name (e.g., `gpt-3.5-turbo`).

### `setMaxTokens(int $max_tokens): static`

Sets the `max_tokens` parameter for completion requests.

### `setTemperature(float $temperature): static`

Sets the `temperature` parameter (creativity, 0.0 to 2.0).

### `setModelParams(array $params): static`

Merges additional model parameters into the default parameters.

### `setTimeout(int $seconds): static`

Sets timeout (seconds) for both HTTP instances.

## Streaming

### `onStream(string $key, callable $callback): static`

Registers a callback for streaming responses. The callback signature is:

`function($key, $data, $finished)`

- **`$key`** : The key you provided.
- **`$data`** : An array containing the parsed JSON chunk (with `'success' => true`) or an error array (`'success' => false`, `'error'`, `'data'`). When `$finished` is `true`, `$data` is an empty array.
- **`$finished`** : `true` when the stream ends, `false` for data chunks.

## Core Methods

### `chat(array $messages, string $model = '', array $options = [], bool $stream = false): array`

Unified chat completion method.

- **`$messages`** : Array of messages, e.g. `[['role' => 'user', 'content' => 'Hi']]`.
- **`$model`** : Override default model.
- **`$options`** : Additional parameters (merged with `$model_params`).
- **`$stream`** : If `true`, perform streaming. Return value is an empty array (output handled by callbacks). If `false`, returns parsed JSON array with `'success'` key.

### `ask(string $prompt, string $system = '', string $model = '', array $options = [], bool $stream = false): array`

Shortcut for single-turn conversation.

- **`$prompt`** : User message.
- **`$system`** : Optional system message.
- Other parameters same as `chat()`.

### `listModels(): array`

Fetches the list of available models via `GET /models`. Returns parsed JSON array with `'success'` key.

### `createEmbedding(string $input, string $model = 'text-embedding-bge-reranker-v2-m3'): array`

Creates an embedding for the given input text. Returns parsed JSON array with `'success'` key.

## Return Format for Non‑stream Methods

All non‑stream methods return an array that **always contains a `'success'` key**:

- If `success === true`: The array contains all original API response fields plus `'success' => true`.
- If `success === false`: The array contains `'error'` (string) and `'data'` (raw response body).

## Usage Example

```php
use Nervsys\Ext\libOpenAI;

$ai = new libOpenAI('http://127.0.0.1:1234/v1', 'your-api-key');

// Non‑stream chat
$result = $ai->ask('Hello, how are you?');
if ($result['success']) {
    echo $result['choices'][0]['message']['content'];
} else {
    echo 'Error: ' . $result['error'];
}

// Stream chat
$ai->addStreamCallback('output', function($key, $data, $finished) {
    if ($finished) return;
    if ($data['success']) {
        $chunk = $data['choices'][0]['delta']['content'] ?? '';
        echo $chunk;
        flush();
    }
});
$ai->ask('Tell me a short story', '', '', [], true);

// List models
$models = $ai->listModels();
if ($models['success']) {
    print_r($models['data']);
}
```