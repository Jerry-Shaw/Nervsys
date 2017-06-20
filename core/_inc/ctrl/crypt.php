<?php

/**
 * Crypt Module
 *
 * Author Jerry Shaw <jerry-shaw@live.com>
 * Author 秋水之冰 <27206617@qq.com>
 *
 * Copyright 2017 Jerry Shaw
 * Copyright 2017 秋水之冰
 *
 * This file is part of NervSys.
 *
 * NervSys is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * NervSys is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with NervSys. If not, see <http://www.gnu.org/licenses/>.
 */

namespace core\ctrl;

class crypt
{
    //Crypt methods
    const method = ['AES-256-CTR', 'CAMELLIA-256-CFB'];

    /**
     * Get RSA Key-Pairs (Public Key & Private Key)
     *
     * @return array
     */
    public static function get_pkey(): array
    {
        $keys = ['public' => '', 'private' => ''];
        $openssl = openssl_pkey_new(OpenSSL_CFG);
        if (false !== $openssl) {
            $public = openssl_pkey_get_details($openssl);
            if (false !== $public) $keys['public'] = &$public['key'];
            if (openssl_pkey_export($openssl, $private, null, OpenSSL_CFG)) $keys['private'] = &$private;
            openssl_pkey_free($openssl);
            unset($public, $private);
        }
        unset($openssl);
        return $keys;
    }

    /**
     * Encrypt
     *
     * @param string $string
     * @param array $keys
     *
     * @return string
     */
    public static function encode(string $string, array $keys): string
    {
        $string = openssl_encrypt($string, self::method[$keys['alg']], $keys['key'], 0, $keys['iv']);
        unset($keys);
        return (string)$string;
    }

    /**
     * Decrypt
     *
     * @param string $string
     * @param array $keys
     *
     * @return string
     */
    public static function decode(string $string, array $keys): string
    {
        $string = openssl_decrypt($string, self::method[$keys['alg']], $keys['key'], 0, $keys['iv']);
        unset($keys);
        return (string)$string;
    }

    /**
     * Get the type of RSA Key
     *
     * @param string $key
     *
     * @return string
     */
    private static function get_type(string $key): string
    {
        $start = strlen('-----BEGIN ');
        $end = strpos($key, ' KEY-----', $start);
        $type = false !== $end ? strtolower(substr($key, $start, $end)) : '';
        unset($key, $start, $end);
        return $type;
    }

    /**
     * Encrypt with PKey
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function encrypt(string $string, string $key): string
    {
        $type = '' !== $key ? self::get_type($key) : '';
        if ('' !== $type && in_array($type, ['public', 'private'], true)) {
            $encrypt = 'openssl_' . $type . '_encrypt';
            if ('' === $string || !$encrypt($string, $string, $key)) $string = '';
            if ('' !== $string) $string = base64_encode($string);
            unset($encrypt);
        }
        unset($key, $type);
        return $string;
    }

    /**
     * Decrypt with PKey
     *
     * @param string $string
     * @param string $key
     *
     * @return string
     */
    public static function decrypt(string $string, string $key): string
    {
        $type = '' !== $key ? self::get_type($key) : '';
        if ('' !== $type && in_array($type, ['public', 'private'], true)) {
            $decrypt = 'openssl_' . $type . '_decrypt';
            if ('' !== $string) $string = base64_decode($string, true);
            if (false === $string || '' === $string || !$decrypt($string, $string, $key)) $string = '';
            unset($decrypt);
        }
        unset($key, $type);
        return $string;
    }

    /**
     * Hash Password
     *
     * @param string $string
     * @param string $codes
     *
     * @return string
     */
    public static function hash_pwd(string $string, string $codes): string
    {
        $noises = str_split($codes, 16);
        $string = 0 === ord(substr($codes, 0, 1)) & 1 ? $noises[0] . ':' . $string . ':' . $noises[2] : $noises[1] . ':' . $string . ':' . $noises[3];
        $string = substr(hash('sha1', $string), 4, 32);
        unset($codes, $noises);
        return $string;
    }

    /**
     * Check Password
     *
     * @param string $input
     * @param string $codes
     * @param string $hash
     *
     * @return bool
     */
    public static function check_pwd(string $input, string $codes, string $hash): bool
    {
        $result = self::hash_pwd($input, $codes) === $hash ? true : false;
        unset($input, $codes, $hash);
        return $result;
    }

    /**
     * Create encrypted content
     *
     * @param string $string
     * @param string $rsa_key
     *
     * @return string
     */
    public static function create_key(string $string, string $rsa_key = ''): string
    {
        if ('' !== $string) {
            $crypt = CRYPT_NAME;
            $key = $crypt::get_key();
            $keys = $crypt::get_keys($key);
            $mixed = $crypt::get_mixed($key);
            $mixed = '' !== $rsa_key ? self::encrypt($mixed, $rsa_key) : base64_encode($mixed);
            $signature = '' !== $mixed ? $mixed . '-' . self::encode($string, $keys) : '';
            unset($crypt, $key, $keys, $mixed);
        } else $signature = '';
        unset($string, $rsa_key);
        return $signature;
    }

    /**
     * Get decrypted content
     *
     * @param string $signature
     * @param string $rsa_key
     *
     * @return string
     */
    public static function validate_key(string $signature, string $rsa_key = ''): string
    {
        if (!empty($signature) && false !== strpos($signature, '-')) {
            $codes = explode('-', $signature, 2);
            $mixed = '' !== $rsa_key ? self::decrypt($codes[0], $rsa_key) : base64_decode($codes[0], true);
            if ('' !== $mixed) {
                $crypt = CRYPT_NAME;
                $key = $crypt::get_rebuilt($mixed);
                $keys = $crypt::get_keys($key);
                $data = self::decode($codes[1], $keys);
                unset($crypt, $key, $keys);
            } else $data = '';
            unset($codes, $mixed);
        } else $data = '';
        unset($signature, $rsa_key);
        return $data;
    }
}