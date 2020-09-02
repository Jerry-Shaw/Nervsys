<?php

/**
 * Crypt Extension
 *
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

namespace Ext;

use Core\Factory;

/**
 * Class libCrypt
 *
 * @package Ext
 */
class libCrypt extends Factory
{
    //Crypt method
    public string $method = 'AES-256-CTR';

    /** @var \Ext\libCryptGen $crypt_gen */
    public string $crypt_gen = libCryptGen::class;

    //OpenSSL conf path
    public string $openssl_cnf = __DIR__ . DIRECTORY_SEPARATOR . 'openssl.cnf';

    /**
     * Set crypt method
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method): self
    {
        $this->method = &$method;

        unset($method);
        return $this;
    }

    /**
     * Set Crypt keygen class
     *
     * @param string $keygen
     *
     * @return $this
     */
    public function setCryptGen(string $keygen): self
    {
        $this->crypt_gen = &$keygen;

        unset($keygen);
        return $this;
    }

    /**
     * Set OpenSSL cnf file path
     *
     * @param string $file_path
     *
     * @return $this
     */
    public function setOpensslCnf(string $file_path): self
    {
        $this->openssl_cnf = &$file_path;

        unset($file_path);
        return $this;
    }

    /**
     * Get crypt key
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->crypt_gen::create();
    }

    /**
     * Get RSA Key-Pairs (Public Key & Private Key)
     *
     * @return array
     * @throws \Exception
     */
    public function getRsaKeys(): array
    {
        $keys   = ['public' => '', 'private' => ''];
        $config = ['config' => $this->openssl_cnf];

        if (false === $openssl = openssl_pkey_new($config)) {
            throw new \Exception('OpenSSL config ERROR!', E_USER_ERROR);
        }

        $public = openssl_pkey_get_details($openssl);

        if (false !== $public) {
            $keys['public'] = &$public['key'];
        }

        if (openssl_pkey_export($openssl, $private, null, $config)) {
            $keys['private'] = &$private;
        }

        openssl_pkey_free($openssl);

        unset($config, $openssl, $public, $private);
        return $keys;
    }

    /**
     * Encrypt string with key
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public function encrypt(string $string, string $key): string
    {
        $keys   = $this->getAesKeys($key);
        $string = $this->base64UrlEncode((string)openssl_encrypt($string, $this->method, $keys['key'], OPENSSL_RAW_DATA, $keys['iv']));

        unset($key, $keys);
        return $string;
    }

    /**
     * Decrypt string with key
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public function decrypt(string $string, string $key): string
    {
        $keys   = $this->getAesKeys($key);
        $string = (string)openssl_decrypt($this->base64UrlDecode($string), $this->method, $keys['key'], OPENSSL_RAW_DATA, $keys['iv']);

        unset($key, $keys);
        return $string;
    }

    /**
     * RSA Encrypt with PKey
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     * @throws \Exception
     */
    public function rsaEncrypt(string $string, string $key): string
    {
        $encrypt = 'public' === $this->getCerType($key)
            ? openssl_public_encrypt($string, $string, $key)
            : openssl_private_encrypt($string, $string, $key);

        if (!$encrypt) {
            return '';
        }

        unset($key, $encrypt);
        return $this->base64UrlEncode($string);
    }

    /**
     * RSA Decrypt with PKey
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     * @throws \Exception
     */
    public function rsaDecrypt(string $string, string $key): string
    {
        $string = $this->base64UrlDecode($string);

        $decrypt = 'private' === $this->getCerType($key)
            ? openssl_private_decrypt($string, $string, $key)
            : openssl_public_decrypt($string, $string, $key);

        if (!$decrypt) {
            return '';
        }

        unset($key, $decrypt);
        return $string;
    }

    /**
     * Check Password
     *
     * @param string $input
     * @param string $key
     * @param string $hash
     *
     * @return bool
     */
    public function checkPasswd(string $input, string $key, string $hash): bool
    {
        $result = $this->hashPasswd($input, $key) === $hash;

        unset($input, $key, $hash);
        return $result;
    }

    /**
     * Hash Password
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public function hashPasswd(string $string, string $key): string
    {
        if (32 > strlen($key)) {
            $key = str_pad($key, 32, $key);
        }

        $noises = str_split($key, 8);
        $string = 0 === (ord($key[0]) & 1)
            ? $noises[0] . ':' . $string . ':' . $noises[2]
            : $noises[1] . ':' . $string . ':' . $noises[3];

        $string = substr(hash('sha1', $string), 4, 32);

        unset($key, $noises);
        return $string;
    }

    /**
     * Sign signature
     *
     * @param string $string
     * @param string $rsa_key
     *
     * @return string
     * @throws \Exception
     */
    public function sign(string $string, string $rsa_key = ''): string
    {
        //Prepare key
        $key = $this->crypt_gen::create();
        $mix = $this->crypt_gen::obscure($key);

        //Encrypt signature
        $mix = '' === $rsa_key ? $this->base64UrlEncode($mix) : $this->rsaEncrypt($mix, $rsa_key);
        $sig = '' !== $mix ? $mix . '.' . $this->encrypt($string, $key) : '';

        unset($string, $rsa_key, $key, $mix);
        return $sig;
    }

    /**
     * Verify signature
     *
     * @param string $string
     * @param string $rsa_key
     *
     * @return string
     * @throws \Exception
     */
    public function verify(string $string, string $rsa_key = ''): string
    {
        //Check signature
        if (false === strpos($string, '.')) {
            return '';
        }

        [$mix, $enc] = explode('.', $string, 2);

        //Rebuild crypt keys
        $mix = '' === $rsa_key ? $this->base64UrlDecode($mix) : $this->rsaDecrypt($mix, $rsa_key);
        $key = $this->crypt_gen::rebuild($mix);

        //Decrypt signature
        $sig = $this->decrypt($enc, $key);

        unset($string, $rsa_key, $mix, $enc, $key);
        return $sig;
    }

    /**
     * Get AES Crypt keys
     *
     * @param string $key
     *
     * @return array
     */
    private function getAesKeys(string $key): array
    {
        //Get iv length
        $iv_len = openssl_cipher_iv_length($this->method);

        //Parse keys from key string
        $keys = $this->crypt_gen::extract($key);

        //Correct iv when length not match
        switch ($iv_len <=> strlen($keys['iv'])) {
            case -1:
                $keys['iv'] = substr($keys['iv'], 0, $iv_len);
                break;
            case 1:
                $keys['iv'] = str_pad($keys['iv'], $iv_len, $keys['iv']);
                break;
        }

        unset($key, $iv_len);
        return $keys;
    }

    /**
     * Get RSA Key type
     *
     * @param string $key
     *
     * @return string
     * @throws \Exception
     */
    private function getCerType(string $key): string
    {
        $start = strlen('-----BEGIN ');
        $end   = strpos($key, ' KEY-----', $start);

        if (false === $end) {
            throw new \Exception('RSA Key ERROR!', E_USER_ERROR);
        }

        $type = strtolower(substr($key, $start, $end - $start));

        if (!in_array($type, ['public', 'private'], true)) {
            throw new \Exception('RSA Key NOT support!', E_USER_ERROR);
        }

        unset($key, $start, $end);
        return $type;
    }

    /**
     * Encode data into base64 (url safe)
     *
     * @param string $string
     *
     * @return string
     */
    private function base64UrlEncode(string $string): string
    {
        return strtr(rtrim(base64_encode($string), '='), '+/', '-_');
    }

    /**
     * Decode data from base64 (url safe)
     *
     * @param string $string
     *
     * @return string
     */
    private function base64UrlDecode(string $string): string
    {
        $string   = strtr($string, '-_', '+/');
        $data_len = strlen($string);

        if (0 < $pad_len = $data_len % 4) {
            $string = str_pad($string, $data_len + $pad_len, '=', STR_PAD_RIGHT);
        }

        unset($data_len, $pad_len);
        return (string)base64_decode($string);
    }
}