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

    public string $org_id    = '';
    public string $api_url   = 'http://127.0.0.1:1234/v1';
    public string $api_key   = '';
    public string $api_model = 'qwen3.6-35b-a3b';

    public array $model_params = [
        'temperature'       => 0.2,
        'max_tokens'        => 16384,
        'top_p'             => 1.0,
        'frequency_penalty' => 0,
        'presence_penalty'  => 0
    ];

    public array $stream_callbacks = [];

    /**
     * Constructor
     *
     * @param string $api_url API base URL
     * @param string $api_key API key
     * @param string $org_id  Organization ID (optional)
     */
    public function __construct(string $api_url = '', string $api_key = '', string $org_id = '')
    {
        if ('' !== $api_url) {
            $this->api_url = rtrim($api_url, '/');
        }

        if ('' !== $api_key) {
            $this->api_key = $api_key;
        }

        if ('' !== $org_id) {
            $this->org_id = $org_id;
        }

        // Create two independent libHttp instances with different User-Agent and timeout
        $this->httpNormal = new libHttp('Nervsys/OpenAI', 300);
        $this->httpStream = new libHttp('Nervsys/OpenAI-Stream', 300);

        // Configure common headers for both instances
        $this->configure($this->httpNormal);
        $this->configure($this->httpStream);

        unset($api_url, $api_key, $org_id);
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
     * Set model max_tokens
     *
     * @param int $max_tokens
     *
     * @return $this
     */
    public function setMaxTokens(int $max_tokens): static
    {
        $this->model_params['max_tokens'] = $max_tokens;

        unset($max_tokens);
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
        $this->model_params = array_merge($this->model_params, $params);

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
     * Register a stream callback
     *
     * @param string   $key
     * @param callable $callback function($key, $data, $finished)
     *                           $data: array with 'success' key (and raw data or error info)
     *                           $finished: true on stream end (with empty data in array)
     *
     * @return $this
     */
    public function onStream(string $key, callable $callback): static
    {
        $this->stream_callbacks[$key] = $callback;

        unset($key, $callback);
        return $this;
    }

    /**
     * Chat completion (unified entry)
     *
     * @param array  $messages
     * @param string $model
     * @param array  $options
     * @param bool   $stream
     *
     * @return array  Parsed JSON array with 'success' key (empty on error)
     * @throws \ReflectionException
     */
    public function chat(array $messages, string $model = '', array $options = [], bool $stream = false): array
    {
        $payload = array_merge($this->model_params,
            $options,
            [
                'model'    => '' === $model ? $this->api_model : $model,
                'messages' => $messages,
                'stream'   => $stream,
            ]
        );

        if ($stream) {
            $this->sendStream('/chat/completions', $payload);
            $result = [];
        } else {
            $result = $this->sendRequest('/chat/completions', $payload);
        }

        unset($messages, $model, $options, $stream, $payload);
        return $result;
    }

    /**
     * Quick ask (shortcut)
     *
     * @param string $prompt
     * @param string $system
     * @param string $model
     * @param array  $options
     * @param bool   $stream
     *
     * @return array  Parsed JSON array with 'success' key
     * @throws \ReflectionException
     */
    public function ask(string $prompt, string $system = '', string $model = '', array $options = [], bool $stream = false): array
    {
        $messages = [];

        if ('' !== $system) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        $result = $this->chat($messages, $model, $options, $stream);

        unset($prompt, $system, $model, $options, $stream, $messages);
        return $result;
    }

    /**
     * List available models (GET request)
     *
     * @return array  Parsed JSON array with 'success' key
     * @throws \ReflectionException
     */
    public function listModels(): array
    {
        $response = $this->httpNormal->setHttpMethod('GET')->fetch($this->api_url . '/models');
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

        unset($response);
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
        $length = strlen($chunk);
        $lines  = explode("\n", $chunk);

        foreach ($lines as $line) {
            $line = trim($line);

            if ('' === $line) {
                continue;
            }

            if (!str_starts_with($line, 'data: ')) {
                continue;
            }

            $json = substr($line, 6);

            if ('[DONE]' === $json) {
                $this->callStreamCallbacks([], true);
                break;
            }

            $data = json_decode($json, true);

            if (null !== $data) {
                $data['success'] = true;
                $this->callStreamCallbacks($data, false);
            } else {
                $this->callStreamCallbacks([
                    'success' => false,
                    'error'   => 'JSON Decode Failed!',
                    'data'    => $json
                ], false);
            }
        }

        unset($chunk, $lines, $line, $json, $data);
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