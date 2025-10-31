<?php

/**
 * Signature Extension
 *
 * Copyright 2025-2025 秋水之冰 <27206617@qq.com>
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
use Nervsys\Core\Lib\IOData;

class libSignature extends Factory
{
    public string $debug_key = 'dbg_str';

    /**
     * @param string $key
     *
     * @return $this
     */
    public function setDebugKey(string $key): self
    {
        $this->debug_key = $key;

        unset($key);
        return $this;
    }

    /**
     * Verify data signature
     *
     * @param string        $app_key
     * @param string        $app_secret
     * @param string        $sign
     * @param array|null    $input_data
     * @param callable|null $sign_handler
     * @param callable|null $error_handler
     *
     * @return bool
     * @throws \ReflectionException
     */
    public function verify(
        string        $app_key,
        string        $app_secret,
        string        $sign,
        array|null    $input_data = null,
        callable|null $sign_handler = null,
        callable|null $error_handler = null
    ): bool
    {
        if (is_null($input_data)) {
            $input_data = IOData::new()->getInputData();
        }

        $client_str = $input_data[$this->debug_key] ?? 'NOT Found!';

        unset($input_data[$this->debug_key], $input_data['sign']);

        $input_data = $this->filterData($input_data);

        $input_data['appKey']    = $app_key;
        $input_data['appSecret'] = $app_secret;

        $input_data['timestamp'] ??= mt_rand(0, 9);
        $input_data['nonceStr']  ??= hash('md5', uniqid(microtime(), true));

        if (is_callable($sign_handler)) {
            $input_data = $sign_handler($input_data);
        }

        ksort($input_data);

        $server_str  = $this->buildQuery($input_data);
        $server_sign = hash('md5', $server_str);

        unset($app_key, $app_secret, $sign_handler, $input_data);

        if ($server_sign !== $sign) {
            if (is_callable($error_handler)) {
                $error_handler($server_sign, $server_str, $sign, $client_str);
            }

            unset($sign, $error_handler, $client_str, $server_str, $server_sign);
            return false;
        }

        unset($sign, $error_handler, $client_str, $server_str, $server_sign);
        return true;
    }

    /**
     * Calculate signature and add needed data to source
     *
     * @param array         $data
     * @param string        $app_key
     * @param string        $app_secret
     * @param callable|null $sign_handler
     *
     * @return array
     */
    public function sign(array $data, string $app_key, string $app_secret, callable|null $sign_handler = null): array
    {
        unset($data['sign']);

        $now_time  = time();
        $sign_data = $this->filterData($data);
        $nonce_str = hash('md5', uniqid(microtime(), true));

        $sign_data['appKey']    = $app_key;
        $sign_data['appSecret'] = $app_secret;
        $sign_data['timestamp'] = $now_time;
        $sign_data['nonceStr']  = $nonce_str;

        if (is_callable($sign_handler)) {
            $sign_data = $sign_handler($sign_data);
        }

        ksort($sign_data);

        $server_str = $this->buildQuery($sign_data);

        $data['sign']      = hash('md5', $server_str);
        $data['appKey']    = $app_key;
        $data['timestamp'] = $now_time;
        $data['nonceStr']  = $nonce_str;

        unset($app_key, $app_secret, $sign_handler, $now_time, $sign_data, $nonce_str, $server_str);
        return $data;
    }

    /**
     * Do NOT escape data content
     *
     * @param array $data
     *
     * @return string
     */
    public function buildQuery(array $data): string
    {
        $data_list = [];

        foreach ($data as $k => $v) {
            $data_list[] = $k . '=' . $v;
        }

        $query_str = implode('&', $data_list);

        unset($data, $data_list, $k, $v);
        return $query_str;
    }

    /**
     * Skip array data and null data
     *
     * @param array $data
     *
     * @return array
     */
    protected function filterData(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v) || is_null($v)) {
                unset($data[$k]);
            }
        }

        unset($k, $v);
        return $data;
    }
}