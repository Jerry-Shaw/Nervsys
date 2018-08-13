<?php

/**
 * Crypt Extension
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
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

namespace ext;

use core\handler\factory;

class crypt extends factory
{
    //OpenSSL config file path
    public static $conf = '/ssl/openssl.cnf';

    //Crypt method
    public static $method = 'AES-256-CTR';

    //Keygen class
    private static $keygen = '\\ext\\lib\\keygen';

    /**
     * Get AES Crypt keys
     *
     * @param string $key
     *
     * @return array
     */
    private static function aes_keys(string $key): array
    {
        //Get iv length
        $iv_len = openssl_cipher_iv_length(self::$method);

        //Parse keys from key string
        $keys = forward_static_call([self::$keygen, 'extract'], $key);

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
    private static function rsa_type(string $key): string
    {
        $start = strlen('-----BEGIN ');
        $end   = strpos($key, ' KEY-----', $start);

        if (false === $end) {
            throw new \Exception('RSA Key ERROR!', E_USER_WARNING);
        }

        $type = strtolower(substr($key, $start, $end - $start));

        if (!in_array($type, ['public', 'private'], true)) {
            throw new \Exception('RSA Key NOT support!', E_USER_WARNING);
        }

        unset($key, $start, $end);
        return $type;
    }

    /**
     * Set keygen class
     *
     * @param string $class
     */
    public static function keygen(string $class): void
    {
        self::$keygen = parent::build_name($class);
        unset($class);
    }

    /**
     * Get RSA Key-Pairs (Public Key & Private Key)
     *
     * @return array
     * @throws \Exception
     */
    public static function rsa_keys(): array
    {
        $keys   = ['public' => '', 'private' => ''];
        $config = ['config' => self::$conf];

        $openssl = openssl_pkey_new($config);

        if (false === $openssl) {
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
    public static function encrypt(string $string, string $key): string
    {
        $keys = self::aes_keys($key);

        $string = (string)openssl_encrypt($string, self::$method, $keys['key'], OPENSSL_ZERO_PADDING, $keys['iv']);

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
    public static function decrypt(string $string, string $key): string
    {
        $keys = self::aes_keys($key);

        $string = (string)openssl_decrypt($string, self::$method, $keys['key'], OPENSSL_ZERO_PADDING, $keys['iv']);

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
    public static function rsa_encrypt(string $string, string $key): string
    {
        $encrypt = 'public' === self::rsa_type($key)
            ? openssl_public_encrypt($string, $string, $key)
            : openssl_private_encrypt($string, $string, $key);

        if (!$encrypt) {
            return '';
        }

        $string = (string)base64_encode($string);

        unset($key, $encrypt);
        return $string;
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
    public static function rsa_decrypt(string $string, string $key): string
    {
        $string = (string)base64_decode($string, true);

        $decrypt = 'private' === self::rsa_type($key)
            ? openssl_private_decrypt($string, $string, $key)
            : openssl_public_decrypt($string, $string, $key);

        if (!$decrypt) {
            return '';
        }

        unset($key, $decrypt);
        return $string;
    }

    /**
     * Hash Password
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function hash_pwd(string $string, string $key): string
    {
        if (32 > strlen($key)) {
            $key = str_pad($key, 32, $key);
        }

        $noises = str_split($key, 8);

        $string = 0 === ord(substr($key, 0, 1)) & 1
            ? $noises[0] . ':' . $string . ':' . $noises[2]
            : $noises[1] . ':' . $string . ':' . $noises[3];

        $string = substr(hash('sha1', $string), 4, 32);

        unset($key, $noises);
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
    public static function check_pwd(string $input, string $key, string $hash): bool
    {
        $result = self::hash_pwd($input, $key) === $hash;

        unset($input, $key, $hash);
        return $result;
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
    public static function sign(string $string, string $rsa_key = ''): string
    {
        //Prepare key
        $key = forward_static_call([self::$keygen, 'create']);
        $mix = forward_static_call([self::$keygen, 'obscure'], $key);

        //Encrypt signature
        $mix = '' === $rsa_key ? (string)base64_encode($mix) : self::rsa_encrypt($mix, $rsa_key);
        $sig = '' !== $mix ? $mix . '-' . self::encrypt($string, $key) : '';

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
    public static function verify(string $string, string $rsa_key = ''): string
    {
        //Check signature
        if (false === strpos($string, '-')) {
            return '';
        }

        list($mix, $enc) = explode('-', $string, 2);

        //Rebuild crypt keys
        $mix = '' === $rsa_key ? (string)base64_decode($mix, true) : self::rsa_decrypt($mix, $rsa_key);
        $key = forward_static_call([self::$keygen, 'rebuild'], $mix);

        //Decrypt signature
        $sig = self::decrypt($enc, $key);

        unset($string, $rsa_key, $mix, $enc, $key);
        return $sig;
    }
}