<?php

/**
 * OpenAI API Extension
 *
 * Copyright 2026 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Nervsys\Ext;

use Nervsys\Core\Factory;

class libOpenAI extends Factory
{
    public libHttp $httpNormal;
    public libHttp $httpStream;

    public \Shmop|null $shmop = null;

    public string $org_id     = '';
    public string $api_url    = 'http://127.0.0.1:1234/v1';
    public string $api_key    = '';
    public string $api_model  = '';
    public string $end_marker = '[DONE]';
    public string $sse_buffer = '';

    public array $model_params = [
        'max_completion_tokens' => 32768,
        'temperature'           => 1.0,
        'min_p'                 => 0,
        'top_p'                 => 0.95,
        'top_k'                 => 40,
        'frequency_penalty'     => 0,
        'presence_penalty'      => 1,
        'repetition_penalty'    => 1,
        'stop'                  => [
            '<|im_end|>',
            '<|endoftext|>',
        ]
    ];

    public array $stream_callbacks = [];

    /**
     * Constructor
     *
     * @param string $api_url    API base URL
     * @param string $api_key    API key
     * @param string $end_marker API stream end marker, default: [DONE]
     */
    public function __construct(string $api_url = '', string $api_key = '', string $end_marker = '')
    {
        if ('' !== $api_url) {
            $this->api_url = rtrim($api_url, '/');
        }

        if ('' !== $api_key) {
            $this->api_key = $api_key;
        }

        if ('' !== $end_marker) {
            $this->end_marker = $end_marker;
        }

        // Create two independent libHttp instances with different User-Agent and timeout
        $this->httpNormal = new libHttp('Nervsys/OpenAI', 300);
        $this->httpStream = new libHttp('Nervsys/OpenAI-Stream', 300);

        // Configure common headers for both instances
        $this->configure($this->httpNormal);
        $this->configure($this->httpStream);

        unset($api_url, $api_key, $end_marker);
    }

    /**
     * Set organization ID
     *
     * @param string $org_id
     *
     * @return $this
     */
    public function setOrgId(string $org_id): static
    {
        $this->org_id = $org_id;

        $this->configure($this->httpNormal);
        $this->configure($this->httpStream);

        unset($org_id);
        return $this;
    }

    /**
     * Set model
     *
     * @param string $model
     *
     * @return $this
     */
    public function setApiModel(string $model): static
    {
        $this->api_model = $model;

        unset($model);
        return $this;
    }

    /**
     * Set model max_completion_tokens
     *
     * @param int $max_completion_tokens
     *
     * @return $this
     */
    public function setMaxTokens(int $max_completion_tokens): static
    {
        $this->model_params['max_completion_tokens'] = $max_completion_tokens;

        unset($max_completion_tokens);
        return $this;
    }

    /**
     * Set model temperature (float value)
     *
     * @param float $temperature
     *
     * @return $this
     */
    public function setTemperature(float $temperature): static
    {
        $this->model_params['temperature'] = $temperature;

        unset($temperature);
        return $this;
    }

    /**
     * Set model parameters
     *
     * @param array $params
     *
     * @return $this
     */
    public function setModelParams(array $params): static
    {
        $this->model_params = $params;

        unset($params);
        return $this;
    }

    /**
     * Set timeout (seconds) for both instances
     *
     * @param int $seconds
     *
     * @return $this
     */
    public function setTimeout(int $seconds): static
    {
        $this->httpNormal->setTimeout($seconds);
        $this->httpStream->setTimeout($seconds);

        unset($seconds);
        return $this;
    }

    /**
     * @param int $shm_key
     *
     * @return $this
     */
    public function openShmop(int $shm_key): static
    {
        $shmop = shmop_open($shm_key, 'c', 0644, 1);

        if (false === $shmop) {
            throw new \RuntimeException('Failed to create shared memory');
        }

        $this->shmop = $shmop;

        unset($shm_key, $shmop);
        return $this;
    }

    /**
     * Abort stream output
     *
     * @return libOpenAI
     */
    public function abortStream(): static
    {
        shmop_write($this->shmop, "\x01", 0);
        return $this;
    }

    /**
     * Resume stream output
     *
     * @return libOpenAI
     */
    public function resumeStream(): static
    {
        shmop_write($this->shmop, "\x00", 0);
        return $this;
    }

    /**
     * List available models (GET request)
     *
     * @return array  Parsed JSON array with 'success' key
     * @throws \ReflectionException
     */
    public function listModels(): array
    {
        $response  = $this->httpNormal->setHttpMethod('GET')->fetch($this->api_url . '/models');
        $json_data = json_decode($response, true);

        if (null !== $json_data) {
            if (isset($json_data['data'])) {
                $result = [
                    'status' => 'success',
                    'data'   => $json_data['data']
                ];
            } else {
                $result = [
                    'status' => 'error',
                    'error'  => $json_data['error'] ?? $json_data,
                ];
            }
        } else {
            $result = [
                'status' => 'error',
                'error'  => 'JSON Decode Failed!',
                'data'   => $response
            ];
        }

        unset($response, $json_data);
        return $result;
    }

    /**
     * Create embeddings
     *
     * @param string $input
     * @param string $model
     *
     * @return array  Parsed JSON array with 'success' key
     * @throws \ReflectionException
     */
    public function createEmbedding(string $input, string $model = 'text-embedding-bge-reranker-v2-m3'): array
    {
        $result = $this->sendRequest('/embeddings',
            [
                'input' => $input,
                'model' => $model
            ]
        );

        unset($input, $model);
        return $result;
    }

    /**
     * Chat completions (POST /chat/completions)
     *
     * @param array         $messages     List of messages (role/content pairs)
     * @param string        $model        Model name (optional, uses default if empty)
     * @param array         $options      Additional parameters (temperature, max_completion_tokens, tools, etc.)
     * @param callable|null $callback     Stream callback (if provided, enables streaming)
     * @param string        $callback_key Unique key for callback (auto‑generated if empty)
     *
     * @return array Parsed JSON array with 'success' key (empty when streaming)
     * @throws \ReflectionException
     */
    public function completions(
        array         $messages,
        string        $model = '',
        array         $options = [],
        callable|null $callback = null,
        string        $callback_key = ''
    ): array
    {
        $payload = array_merge(
            $this->model_params,
            $options,
            [
                'model'    => '' === $model ? $this->api_model : $model,
                'messages' => $messages,
                'stream'   => null !== $callback,
            ]
        );

        $result = [];

        if (null !== $callback) {
            $key = '' !== $callback_key ? $callback_key : 'completions_stream_' . uniqid('', true);

            $this->stream_callbacks[$key] = $callback;
            $this->sendStream('/chat/completions', $payload);

            unset($this->stream_callbacks[$key], $key);
        } else {
            $result = $this->sendRequest('/chat/completions', $payload);
        }

        unset($messages, $model, $options, $callback, $callback_key, $payload);
        return $result;
    }

    /**
     * Responses API (POST /v1/responses)
     *
     * @param array         $input        Input messages or text (structure depends on API)
     * @param string        $model        Model name (optional, uses default if empty)
     * @param array         $options      Additional parameters (temperature, max_completion_tokens, tools, etc.)
     * @param callable|null $callback     Stream callback (if provided, enables streaming)
     * @param string        $callback_key Unique key for callback (auto‑generated if empty)
     *
     * @return array Parsed JSON array with 'success' key (empty when streaming)
     * @throws \ReflectionException
     */
    public function responses(
        array         $input,
        string        $model = '',
        array         $options = [],
        callable|null $callback = null,
        string        $callback_key = ''
    ): array
    {
        $payload = array_merge(
            $options,
            [
                'model'  => '' === $model ? $this->api_model : $model,
                'input'  => $input,
                'stream' => null !== $callback,
            ]
        );

        $result = [];

        if (null !== $callback) {
            $key = '' !== $callback_key ? $callback_key : 'responses_stream_' . uniqid('', true);

            $this->stream_callbacks[$key] = $callback;
            $this->sendStream('/v1/responses', $payload);

            unset($this->stream_callbacks[$key], $key);
        } else {
            $result = $this->sendRequest('/v1/responses', $payload);
        }

        unset($input, $model, $options, $callback, $callback_key, $payload);
        return $result;
    }

    /**
     * Messages API (POST /v1/messages) – Assistants
     *
     * @param array         $message      Single message (role/content)
     * @param string        $model        Model name (optional, uses default if empty)
     * @param array         $options      Additional parameters (thread_id, temperature, etc.)
     * @param callable|null $callback     Stream callback (if provided, enables streaming)
     * @param string        $callback_key Unique key for callback (auto‑generated if empty)
     *
     * @return array Parsed JSON array with 'success' key (empty when streaming)
     * @throws \ReflectionException
     */
    public function messages(
        array         $message,
        string        $model = '',
        array         $options = [],
        callable|null $callback = null,
        string        $callback_key = ''
    ): array
    {
        $payload = array_merge(
            $options,
            [
                'model'   => '' === $model ? $this->api_model : $model,
                'message' => $message,
                'stream'  => null !== $callback,
            ]
        );

        $result = [];

        if (null !== $callback) {
            $key = '' !== $callback_key ? $callback_key : 'messages_stream_' . uniqid('', true);

            $this->stream_callbacks[$key] = $callback;
            $this->sendStream('/v1/messages', $payload);

            unset($this->stream_callbacks[$key], $key);
        } else {
            $result = $this->sendRequest('/v1/messages', $payload);
        }

        unset($message, $model, $options, $callback, $callback_key, $payload);
        return $result;
    }

    /**
     * Configure a libHttp instance with common headers and content type
     *
     * @param libHttp $http
     *
     * @return void
     */
    private function configure(libHttp $http): void
    {
        $http->setContentType(libHttp::CONTENT_TYPE_JSON);

        if ('' !== $this->api_key) {
            $http->addHeader(['Authorization' => 'Bearer ' . $this->api_key]);
        }

        if ('' !== $this->org_id) {
            $http->addHeader(['OpenAI-Organization' => $this->org_id]);
        }

        unset($http);
    }

    /**
     * Send a normal (non-stream) request and return parsed JSON array with 'success' key
     *
     * @param string $endpoint
     * @param array  $payload
     * @param string $method
     *
     * @return array  Array with 'success' key (true = parsed data, false = error)
     * @throws \ReflectionException
     */
    private function sendRequest(string $endpoint, array $payload, string $method = 'POST'): array
    {
        $response = $this->httpNormal->setHttpMethod($method)->addData($payload)->fetch($this->api_url . $endpoint);
        $result   = json_decode($response, true);

        if (null !== $result) {
            $result['success'] = true;
        } else {
            $result = [
                'success' => false,
                'error'   => 'JSON Decode Failed!',
                'data'    => $response
            ];
        }

        unset($endpoint, $payload, $method, $response);
        return $result;
    }

    /**
     * Send a stream request using libHttp with callback
     *
     * @param string $endpoint
     * @param array  $payload
     *
     * @return void
     * @throws \ReflectionException
     */
    private function sendStream(string $endpoint, array $payload): void
    {
        $this->sse_buffer = '';

        $this->httpStream->setHttpMethod('POST');
        $this->httpStream->addData($payload);

        $this->httpStream->setStreamCallback(
            function (\CurlHandle $cURL_handle, string $chunk): int
            {
                return $this->handleStreamChunk($chunk);
            }
        );

        $this->httpStream->fetch($this->api_url . $endpoint);
        $this->httpStream->removeStreamCallback();

        unset($endpoint, $payload);
    }

    /**
     * Process a chunk of stream data: extract SSE lines, parse JSON, pass to callbacks.
     *
     * @param string $chunk
     *
     * @return int
     */
    private function handleStreamChunk(string $chunk): int
    {
        if (!str_starts_with($chunk, 'data: ')) {
            $data = json_decode(trim($chunk), true);

            if (is_array($data)) {
                $this->callStreamCallbacks($data, true);
            } else {
                $this->callStreamCallbacks([
                    'status'    => 'error',
                    'message'   => 'Invalid response format',
                    'json_data' => $chunk
                ], true);
            }

            $this->sse_buffer = '';

            unset($data);
            return strlen($chunk);
        }

        $this->sse_buffer .= $chunk;

        $length = strlen($chunk);

        while (false !== ($event_end = strpos($this->sse_buffer, "\n\n"))) {
            if (!is_null($this->shmop) && "\x01" === shmop_read($this->shmop, 0, 1)) {
                $this->sse_buffer = '';
                $this->callStreamCallbacks(['status' => 'aborted'], true);
                unset($chunk, $event_end, $sse_event, $data_pos, $data_line, $data);
                return 0;
            }

            $sse_event        = substr($this->sse_buffer, 0, $event_end);
            $this->sse_buffer = substr($this->sse_buffer, $event_end + 2);
            $this->sse_buffer = ltrim($this->sse_buffer, "\r\n");

            $data_pos = strpos($sse_event, 'data: ');

            if (false === $data_pos) {
                continue;
            }

            $data_line = substr($sse_event, $data_pos + 6);
            $data_line = rtrim($data_line, "\r\n");

            if ($data_line === $this->end_marker) {
                $this->callStreamCallbacks([], true);
                $this->sse_buffer = '';

                unset($chunk, $event_end, $sse_event, $data_pos, $data_line);
                return $length;
            }

            $data = json_decode($data_line, true);

            if (is_array($data)) {
                $data['status'] = 'success';
                $this->callStreamCallbacks($data, false);
            } else {
                $this->callStreamCallbacks([
                    'status'    => 'error',
                    'message'   => 'JSON Decode Failed!',
                    'json_data' => $data_line
                ], false);
            }
        }

        unset($chunk, $event_end, $sse_event, $data_pos, $data_line, $data);
        return $length;
    }

    /**
     * Trigger all registered stream callbacks
     *
     * @param array $data     Parsed data with 'success' key (or empty array when finished)
     * @param bool  $finished True on stream end
     *
     * @return void
     */
    private function callStreamCallbacks(array $data, bool $finished): void
    {
        foreach ($this->stream_callbacks as $key => $callback) {
            call_user_func($callback, $key, $data, $finished);
        }

        unset($data, $finished, $key, $callback);
    }
}