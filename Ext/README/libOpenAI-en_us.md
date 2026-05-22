## libOpenAI Description

`libOpenAI` is an extension for interacting with OpenAI-compatible APIs (e.g., local LM Studio, OpenAI, DeepSeek). It
supports both normal (non-stream) and streaming requests for three endpoints: `/chat/completions`, `/v1/responses`, and
`/v1/messages`. It also provides model listing and embedding creation.

**Language:** English | [中文文档](./libOpenAI-zh_cn.md)

## Namespace

`Nervsys\Ext`

## Constructor

### `__construct(string $api_url = '', string $api_key = '', string $org_id = '')`

- **`$api_url`** : Base URL of the API (e.g., `http://127.0.0.1:1234/v1`).
- **`$api_key`** : API key (Bearer token).
- **`$org_id`** : Organization ID (optional, used for OpenAI `OpenAI-Organization` header).

Creates two `libHttp` instances (`httpNormal` and `httpStream`) with different User-Agent strings and a default timeout
of 300 seconds.

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

## Core Methods (Unified Streaming)

All three main methods accept an optional `$callback` parameter. When a callback is provided, streaming is automatically
enabled; otherwise, a non‑streaming request is performed and the parsed JSON result is returned (with `'success'` key).

###
`completions(array $messages, string $model = '', array $options = [], callable $callback = null, string $callback_key = ''): array`

Performs a chat completion request (POST `/chat/completions`).

- **`$messages`** : Array of messages, e.g. `[['role' => 'user', 'content' => 'Hi']]`.
- **`$model`** : Override default model.
- **`$options`** : Additional parameters (merged with default model parameters).
- **`$callback`** : Optional callback for streaming. Signature: `function($key, $data, $finished): void`
    - `$key` : The callback key (auto‑generated or provided).
    - `$data` : For streaming chunks, an array containing the parsed JSON chunk with `'success' => true`; for errors, an
      array with `'success' => false`, `'error'`, and `'data'`.
    - `$finished` : `true` when the stream ends (data is empty array), `false` for data chunks.
- **`$callback_key`** : Optional unique key for the callback (auto‑generated if empty).

**Return**:

- If `$callback` is provided: returns empty array (output handled by callback).
- Otherwise: returns parsed JSON array with `'success'` key.

###
`responses(array $input, string $model = '', array $options = [], callable $callback = null, string $callback_key = ''): array`

Performs a request to the Responses API (POST `/v1/responses`). The `$input` parameter can be a string or an array of
messages (depending on the API implementation).

- Parameters have the same meaning as in `completions`.

###
`messages(array $message, string $model = '', array $options = [], callable $callback = null, string $callback_key = ''): array`

Sends a single message to the Assistants API (POST `/v1/messages`). The `$message` parameter should be an associative
array with `'role'` and `'content'`.

- Parameters have the same meaning as in `completions`.

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

// Non‑stream chat completion
$result = $ai->completions([['role' => 'user', 'content' => 'Hello']]);
if ($result['success']) {
    echo $result['choices'][0]['message']['content'];
} else {
    echo 'Error: ' . $result['error'];
}

// Stream chat completion
$ai->completions(
    [['role' => 'user', 'content' => 'Tell a short story']],
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

// Responses API (non‑stream)
$resp = $ai->responses(['input' => 'Hello world']);
if ($resp['success']) {
    print_r($resp);
}

// Messages API (stream)
$ai->messages(
    ['role' => 'user', 'content' => 'Hi'],
    '',
    [],
    function($key, $data, $finished) {
        // handle streaming
    }
);

// List models
$models = $ai->listModels();
if ($models['success']) {
    print_r($models['data']);
}
```